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

# Câmara
ROTATE_CAMERA = 0           # ALTERA AQUI: 0, 90 (horário), 270 (anti-horário)
SHOW_FULLSCREEN = False     # True se quiseres ecrã inteiro

# ROI (Zona onde a matrícula deve aparecer)
# Define em percentagem do ecrã (x_inicio, y_inicio, x_fim, y_fim)
ROI = {
    "x1": 0.10, "y1": 0.40,
    "x2": 0.90, "y2": 0.70
}

# Tempos
PLATE_COOLDOWN = 20         # Segundos entre aberturas da mesma matrícula
OCR_INTERVAL   = 40         # Frames entre cada leitura OCR (~2 segundos)
APP_INTERVAL   = 20         # Frames entre cada verificação da App
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

def processar_ocr(frame):
    global status_msg, status_color, last_plate, last_plate_time, total_api_calls, cooldown_msg
    
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
                    return

                # Cooldown: mesma matrícula recente?
                if plate == last_plate and (agora - last_plate_time) < PLATE_COOLDOWN:
                    restante = int(PLATE_COOLDOWN - (agora - last_plate_time))
                    cooldown_msg = f"COOLDOWN: {restante}s"
                    return
                else:
                    cooldown_msg = ""

                # Verificar no Supabase
                headers = {"apikey": SUPABASE_KEY, "Authorization": f"Bearer {SUPABASE_KEY}"}
                check = requests.get(
                    f"{SUPABASE_URL}/rest/v1/cars?plate=eq.{plate}&select=id",
                    headers=headers, timeout=10
                )

                if check.status_code == 200 and check.json():
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
            else:
                status_msg = "A AGUARDAR..."
                status_color = (255, 255, 255)
    except Exception as e:
        status_msg = "ERRO API"
        print(f"Erro OCR: {e}")

# Inicializar
PLATE_REC_TOKEN = "41382f0532af769b6b38b10e9cf6df72f4dbe496"
source = CAMERA_URL if CAMERA_URL else 0
print(f"🎥 A ligar à câmara: {source}")

cap = cv2.VideoCapture(source)
if not cap.isOpened():
    print("❌ Erro ao abrir câmara!")
    exit()

if SHOW_FULLSCREEN:
    cv2.namedWindow("ABRE JA - Monitor", cv2.WND_PROP_FULLSCREEN)
    cv2.setWindowProperty("ABRE JA - Monitor", cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)

count = 0
start_time = time.time()

while True:
    ret, frame = cap.read()
    if not ret:
        print("⚠️ Ligação perdida. A tentar reconectar...")
        cap.release()
        time.sleep(2)
        cap = cv2.VideoCapture(source)
        continue

    count += 1

    # 1. Rodar a câmara se necessário
    if ROTATE_CAMERA == 90:
        frame = cv2.rotate(frame, cv2.ROTATE_90_CLOCKWISE)
    elif ROTATE_CAMERA == 270:
        frame = cv2.rotate(frame, cv2.ROTATE_90_COUNTERCLOCKWISE)
    elif ROTATE_CAMERA == 180:
        frame = cv2.rotate(frame, cv2.ROTATE_180)

    h, w = frame.shape[:2]

    # 2. Desenhar ROI (Zona de leitura)
    x1, y1 = int(ROI["x1"] * w), int(ROI["y1"] * h)
    x2, y2 = int(ROI["x2"] * w), int(ROI["y2"] * h)
    cv2.rectangle(frame, (x1, y1), (x2, y2), (255, 0, 255), 3)
    cv2.putText(frame, "ZONA OCR", (x1, y1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 0, 255), 2)

    # 3. Tarefas periódicas
    if count % APP_INTERVAL == 0:
        verificar_app()
    if count % OCR_INTERVAL == 0:
        processar_ocr(frame)

    # 4. HUD (Interface)
    # Barra de fundo
    cv2.rectangle(frame, (0, 0), (w, 110), (0, 0, 0), -1)
    cv2.rectangle(frame, (0, h-60), (w, h), (0, 0, 0), -1)

    # Texto superior
    cv2.putText(frame, f"ABRE JA | {datetime.now().strftime('%H:%M:%S')}", 
                (20, 35), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
    cv2.putText(frame, f"API: {total_api_calls} | APP: {app_status} | {cooldown_msg}", 
                (20, 75), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 0), 1)

    # Texto inferior (Estado)
    cv2.putText(frame, status_msg, (20, h-20), 
                cv2.FONT_HERSHEY_SIMPLEX, 0.9, status_color, 2)

    # 5. Mostrar
    cv2.imshow("ABRE JA - Monitor", frame)

    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

cap.release()
cv2.destroyAllWindows()
GPIO.cleanup()
print("🛑 Sistema desligado.")
