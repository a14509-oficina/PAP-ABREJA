import cv2
import requests
import os
import time
from datetime import datetime
from dotenv import load_dotenv
import RPi.GPIO as GPIO

# ╔═══════════════════════════════════════════════════════╗
# ║              CONFIGURAÇÕES DO SISTEMA                 ║
# ╚═══════════════════════════════════════════════════════╝
load_dotenv()

SUPABASE_URL = os.getenv("SUPABASE_URL")
SUPABASE_KEY = os.getenv("SUPABASE_KEY")
CAMERA_URL   = os.getenv("CAMERA_URL")

# Hardware
RELAY_PIN   = int(os.getenv("RELAY_PIN", "17"))
RELAY_TIME  = float(os.getenv("RELAY_TIME", "1.0"))

# OCR
PLATE_REC_TOKEN = os.getenv("PLATE_REC_TOKEN", "")
if not PLATE_REC_TOKEN:
    print("❌ PLATE_REC_TOKEN não definido no .env")
    exit()

# Tempos (segundos)
PLATE_COOLDOWN = int(os.getenv("COOLDOWN", "20"))        # intervalo entre aberturas da mesma matrícula
PROCESS_INTERVAL = float(os.getenv("PROCESS_INTERVAL", "2.0"))  # segundos entre OCR
APP_INTERVAL   = 1.0                                     # verifica app a cada 1s
CACHE_TTL      = int(os.getenv("CACHE_TTL", "300"))      # tempo para re-verificar matrícula
CONFIRMAR_EM   = int(os.getenv("CONFIRMAR_EM", "3"))     # leituras iguais consecutivas necessárias

# Vídeo
ROTATE_CAMERA = int(os.getenv("ROTATE", "0"))            # 0, 90, 180, 270
SHOW_FULLSCREEN = os.getenv("FULLSCREEN", "false").lower() == "true"

# ROI (Zona onde a matrícula deve aparecer)
ROI = {
    "x1": 0.10, "y1": 0.40,
    "x2": 0.90, "y2": 0.70
}
# ═══════════════════════════════════════════════════════

# Setup GPIO
GPIO.setmode(GPIO.BCM)
GPIO.setup(RELAY_PIN, GPIO.OUT, initial=GPIO.HIGH)

# Estado Global
status_msg    = "A AGUARDAR..."
status_color  = (255, 255, 255)
app_status    = "OK"
last_plate    = ""
last_plate_time = 0
cooldown_msg  = ""
total_api_calls = 0

# Cache de matrículas autorizadas (evita chamadas repetidas ao Supabase)
plate_cache = {}   # plate -> timestamp

# Contador de confirmações consecutivas da mesma matrícula
confirm_count = 0
confirm_plate = ""

def acionar_rele(origem="DESCONHECIDO"):
    global status_msg, status_color
    print(f"🔓 RELÉ ATIVADO: {origem}")
    status_msg = f"ABERTO: {origem}"
    status_color = (0, 255, 0)
    GPIO.output(RELAY_PIN, GPIO.LOW)
    time.sleep(RELAY_TIME)
    GPIO.output(RELAY_PIN, GPIO.HIGH)

def verificar_app():
    global app_status
    url = f"{SUPABASE_URL}/rest/v1/open_requests?status=eq.pending&select=id"
    headers = {"apikey": SUPABASE_KEY, "Authorization": f"Bearer {SUPABASE_KEY}"}
    try:
        r = requests.get(url, headers=headers, timeout=10)
        if r.status_code == 200:
            pedidos = r.json()
            if pedidos:
                app_status = f"{len(pedidos)} PEDIDO(S)"
                for p in pedidos:
                    acionar_rele("APP")
                    requests.patch(f"{SUPABASE_URL}/rest/v1/open_requests?id=eq.{p['id']}",
                                   headers=headers, json={"status": "done"}, timeout=5)
            else:
                app_status = "OK"
        else:
            app_status = f"ERRO {r.status_code}"
    except:
        app_status = "OFFLINE"

def plate_no_cache(plate):
    """Verifica se matrícula está em cache e ainda válida"""
    if plate in plate_cache:
        if time.time() - plate_cache[plate] < CACHE_TTL:
            return True
    return False

def processar_ocr(frame):
    global status_msg, status_color, last_plate, last_plate_time, total_api_calls, cooldown_msg
    global confirm_count, confirm_plate

    agora = time.time()

    # Pré-processamento: cortar a ROI
    h, w = frame.shape[:2]
    x1, y1 = int(ROI["x1"] * w), int(ROI["y1"] * h)
    x2, y2 = int(ROI["x2"] * w), int(ROI["y2"] * h)
    roi_frame = frame[y1:y2, x1:x2]

    if roi_frame.size == 0:
        return

    _, img_encoded = cv2.imencode('.jpg', roi_frame)
    total_api_calls += 1

    try:
        res = requests.post(
            "https://api.platerecognizer.com/v1/plate-reader/",
            data={"regions": "pt"},
            files={"upload": img_encoded},
            headers={"Authorization": f"Token {PLATE_REC_TOKEN}"},
            timeout=15
        )

        if res.status_code in [200, 201]:
            data = res.json()
            if data.get('results'):
                plate = data['results'][0]['plate'].upper()
                confidence = data['results'][0]['score']

                # Só aceita se confiança for alta
                if confidence < 0.75:
                    status_msg = "MATRICULA INCERTA"
                    status_color = (0, 165, 255)
                    confirm_count = 0
                    return

                # Sistema de confirmação consecutiva
                if plate == confirm_plate:
                    confirm_count += 1
                else:
                    confirm_count = 1
                    confirm_plate = plate

                if confirm_count < CONFIRMAR_EM:
                    status_msg = f"CONFIRMAR {confirm_count}/{CONFIRMAR_EM}: {plate}"
                    status_color = (255, 165, 0)
                    return

                # Confirmado! Processa a matrícula
                confirm_count = 0

                # Cooldown: mesma matrícula recente?
                if plate == last_plate and (agora - last_plate_time) < PLATE_COOLDOWN:
                    restante = int(PLATE_COOLDOWN - (agora - last_plate_time))
                    cooldown_msg = f"COOLDOWN: {restante}s"
                    return
                else:
                    cooldown_msg = ""

                # Verificar cache primeiro
                if plate_no_cache(plate):
                    acionar_rele(f"AUTO:{plate}")
                    last_plate = plate
                    last_plate_time = agora
                    return

                # Verificar no Supabase
                headers = {"apikey": SUPABASE_KEY, "Authorization": f"Bearer {SUPABASE_KEY}"}
                check = requests.get(
                    f"{SUPABASE_URL}/rest/v1/cars?plate=eq.{plate}&select=id",
                    headers=headers, timeout=10
                )

                if check.status_code == 200 and check.json():
                    # Guardar em cache
                    plate_cache[plate] = agora
                    acionar_rele(f"AUTO:{plate}")
                    last_plate = plate
                    last_plate_time = agora
                    # Registar acesso permitido
                    try:
                        car_id = check.json()[0]['id']
                        requests.post(
                            f"{SUPABASE_URL}/rest/v1/access_logs",
                            headers={"apikey": SUPABASE_KEY, "Authorization": f"Bearer {SUPABASE_KEY}", "Content-Type": "application/json", "Prefer": "return=representation"},
                            json={"gate_id": None, "plate": plate, "method": "plate", "user_id": None},
                            timeout=5
                        )
                    except:
                        pass
                else:
                    status_msg = f"NEGADO: {plate}"
                    status_color = (0, 0, 255)
                    last_plate = plate
                    last_plate_time = agora
                    # Registar acesso negado
                    try:
                        requests.post(
                            f"{SUPABASE_URL}/rest/v1/access_logs",
                            headers={"apikey": SUPABASE_KEY, "Authorization": f"Bearer {SUPABASE_KEY}", "Content-Type": "application/json", "Prefer": "return=representation"},
                            json={"gate_id": None, "plate": plate, "method": "plate_denied", "user_id": None},
                            timeout=5
                        )
                    except:
                        pass

                # Limpar contagem de confirmação
                confirm_plate = ""
            else:
                status_msg = "A AGUARDAR..."
                status_color = (255, 255, 255)
                confirm_count = 0
    except Exception as e:
        status_msg = "ERRO API"
        print(f"Erro OCR: {e}")

# Inicializar
source = CAMERA_URL if CAMERA_URL else 0
print(f"🎥 A ligar à câmara: {source}")

cap = cv2.VideoCapture(source)
if not cap.isOpened():
    print("❌ Erro ao abrir câmara!")
    exit()

if SHOW_FULLSCREEN:
    cv2.namedWindow("ABRE JA - Monitor", cv2.WND_PROP_FULLSCREEN)
    cv2.setWindowProperty("ABRE JA - Monitor", cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)

last_ocr_time = 0
last_app_time = 0

while True:
    ret, frame = cap.read()
    if not ret:
        print("⚠️ Ligação perdida. A tentar reconectar...")
        cap.release()
        time.sleep(2)
        cap = cv2.VideoCapture(source)
        continue

    agora = time.time()

    # Rodar a câmara se necessário
    if ROTATE_CAMERA == 90:
        frame = cv2.rotate(frame, cv2.ROTATE_90_CLOCKWISE)
    elif ROTATE_CAMERA == 270:
        frame = cv2.rotate(frame, cv2.ROTATE_90_COUNTERCLOCKWISE)
    elif ROTATE_CAMERA == 180:
        frame = cv2.rotate(frame, cv2.ROTATE_180)

    h, w = frame.shape[:2]

    # Desenhar ROI
    x1, y1 = int(ROI["x1"] * w), int(ROI["y1"] * h)
    x2, y2 = int(ROI["x2"] * w), int(ROI["y2"] * h)
    cv2.rectangle(frame, (x1, y1), (x2, y2), (255, 0, 255), 3)
    cv2.putText(frame, "ZONA OCR", (x1, y1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 0, 255), 2)

    # Tarefas periódicas (baseadas em tempo real)
    if agora - last_app_time >= APP_INTERVAL:
        verificar_app()
        last_app_time = agora
    if agora - last_ocr_time >= PROCESS_INTERVAL:
        processar_ocr(frame)
        last_ocr_time = agora

    # HUD (Interface)
    cv2.rectangle(frame, (0, 0), (w, 110), (0, 0, 0), -1)
    cv2.rectangle(frame, (0, h-60), (w, h), (0, 0, 0), -1)

    cv2.putText(frame, f"ABRE JA | {datetime.now().strftime('%H:%M:%S')}",
                (20, 35), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
    cv2.putText(frame, f"API: {total_api_calls} | APP: {app_status} | {cooldown_msg}",
                (20, 75), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 0), 1)

    cv2.putText(frame, status_msg, (20, h-20),
                cv2.FONT_HERSHEY_SIMPLEX, 0.9, status_color, 2)

    cv2.imshow("ABRE JA - Monitor", frame)

    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

cap.release()
cv2.destroyAllWindows()
GPIO.cleanup()
print("🛑 Sistema desligado.")
