# TODO - Abre Já

## ✅ Feito
- [x] SendGrid configurado (email de recuperação)
- [x] Reset de password sem email (link no ecrã)
- [x] SSL_VERIFYPEER ativado
- [x] Service key centralizada em config.php
- [x] `.env` loader 
- [x] Rate limiting (login 5/5min, forgot 3/5min)
- [x] Error logging
- [x] Pesquisa em carros e portões
- [x] Ordenação em carros e portões
- [x] Paginação nos logs (admin e detalhe do portão)
- [x] Login por email ou nome de utilizador
- [x] Permissão ao abrir portão (só dono, admin, ou partilhado)
- [x] Auto-refresh dos logs no modal do portão
- [x] ipcam.py regista matrículas em access_logs
- [x] CSRF tokens
- [x] Remember me (Manter sessão)
- [x] Exportar logs para CSV
- [x] Política de privacidade
- [x] Validação de matrícula portuguesa (AA-00-AA)
- [x] README atualizado com SQL das tabelas

## 🔴 Pendente - Raspberry Pi (amanhã)
- [ ] Mover token do Plate Recognizer (`ipcam.py:151`) para `.env` do Pi
- [ ] Verificar `.env` do Pi (SUPABASE_URL, SUPABASE_KEY, CAMERA_URL, RELAY_PIN)
- [ ] Modificar script para usar relay_id por portão (múltiplos relés)
- [ ] Fazer git pull no Pi

## 🟡 Melhorias futuras
- [ ] Unificar auth do admin com sistema principal
- [ ] Modo escuro/claro (já existe)
- [ ] Notificações real-time quando alguém abre o portão
