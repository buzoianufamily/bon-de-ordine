#!/usr/bin/env bash
# Test HTTP end-to-end al stratului de rutare (index.php) cu serverul integrat PHP.
# Porneste php -S, instaleaza schema, ruleaza cereri reale (login/CSRF, emitere bon, exporturi,
# pagini admin, logout) si verifica codurile/raspunsurile. Iese !=0 daca pica ceva.
# Config DB din env: BDO_DB_HOST/PORT/NAME/USER/PASS (ca tests/integration.php).
set -u
# python3 e folosit pentru a parsa raspunsuri JSON (API v1, sloturi) — fara el, testele ar fi
# sarite silentios si ar da "green" fals; esueaza explicit daca lipseste.
command -v python3 >/dev/null 2>&1 || { echo "FATAL: python3 lipseste (necesar pentru asertiile API v1)"; exit 1; }
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
HOST=127.0.0.1; PORT=${BDO_HTTP_PORT:-8123}
DBH=${BDO_DB_HOST:-127.0.0.1}; DBP=${BDO_DB_PORT:-3306}
DBN=${BDO_DB_NAME:-bon}; DBU=${BDO_DB_USER:-bon}; DBW=${BDO_DB_PASS:-bon}
JAR="$(mktemp)"; SRVLOG="$(mktemp)"; CFGBAK="$(mktemp)"

# salveaza config real, scrie config de test catre baza CI
cp config/config.php "$CFGBAK" 2>/dev/null || true
cat > config/config.php <<EOF
<?php return ['db'=>['host'=>'${DBH};port=${DBP}','name'=>'${DBN}','user'=>'${DBU}','pass'=>'${DBW}','charset'=>'utf8mb4'],
'app'=>['name'=>'HTTP','base_url'=>'','env'=>'dev','timezone'=>'Europe/Bucharest','locale'=>'ro'],'landlord_pass'=>'httppass'];
EOF

SRV=""
cleanup(){ [ -n "$SRV" ] && kill "$SRV" 2>/dev/null; [ -f "$CFGBAK" ] && mv "$CFGBAK" config/config.php; rm -f "$JAR" "$SRVLOG" config/billing.json config/invoices.json; }
trap cleanup EXIT

# instaleaza schema + ia id-uri necesare (foloseste env, nu config.php)
IDS="$(BDO_DB_HOST=$DBH BDO_DB_PORT=$DBP BDO_DB_NAME=$DBN BDO_DB_USER=$DBU BDO_DB_PASS=$DBW php tests/_ids.php 2>/dev/null | tail -1)"
read -r DKEY SVC CTR BR AKEY <<< "$IDS"
[ -n "${DKEY:-}" ] || { echo "FATAL: nu am putut instala/citi id-uri (IDS='$IDS')"; exit 1; }

php -S "$HOST:$PORT" index.php >"$SRVLOG" 2>&1 &
SRV=$!
for i in $(seq 1 40); do curl -fsS "http://$HOST:$PORT/health" >/dev/null 2>&1 && break; sleep 0.5; done

PASS=0; FAIL=0
B="http://$HOST:$PORT"
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
t(){ local d="$1" want="$2" got="$3"; if [ "$got" = "$want" ]; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "FAIL: $d (want $want, got $got)"; fi; }
tcontains(){ local d="$1" needle="$2" body="$3"; if printf '%s' "$body" | grep -q "$needle"; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "FAIL: $d (missing '$needle')"; fi; }

# --- antete de securitate + igiena crawler ---
HDRS="$(curl -s -D - -o /dev/null "$B/")"
tcontains "CSP: antet prezent" 'Content-Security-Policy:' "$HDRS"
tcontains "CSP: default-src self" "default-src 'self'" "$HDRS"
tcontains "CSP: object-src none" "object-src 'none'" "$HDRS"
tcontains "noindex: X-Robots-Tag pe raspunsuri" 'X-Robots-Tag: noindex' "$HDRS"
t "GET /robots.txt -> 200" 200 "$(code $B/robots.txt)"
tcontains "robots.txt interzice indexarea" 'Disallow: /' "$(curl -s $B/robots.txt)"

# --- public ---
t "GET /health"            200 "$(code $B/health)"
tcontains "health: schema la zi dupa instalare" '"schema_current":true' "$(curl -s $B/health)"
t "GET / (portal)"         200 "$(code $B/)"
t "404 pagina inexistenta -> cod 404" 404 "$(code $B/nu-exista-xyz)"
tcontains "404 brandat (nu text brut)" 'Pagină negăsită' "$(curl -s "$B/nu-exista-xyz")"
tcontains "bilet inexistent -> pagina prietenoasa" '← Acas' "$(curl -s "$B/t/tokeninvalidxyz")"
t "GET /login"             200 "$(code $B/login)"
tcontains "login multilingv EN (?lang=en)" 'Sign in to your account' "$(curl -s "$B/login?lang=en")"
tcontains "login RO implicit" 'Autentifica-te in cont' "$(curl -s "$B/login")"
t "GET /login/forgot"      200 "$(code $B/login/forgot)"
tcontains "forgot multilingv EN" 'Reset password' "$(curl -s "$B/login/forgot?lang=en")"
t "GET /concierge anon->redirect" 302 "$(code $B/concierge)"
# feedback multilingv (ca biletul digital)
tcontains "feedback RO implicit" 'Cum a fost experienta' "$(curl -s "$B/feedback")"
tcontains "feedback EN (?lang=en)" 'How was your experience' "$(curl -s "$B/feedback?lang=en")"
tcontains "feedback a11y: rating e radiogroup" 'role="radiogroup"' "$(curl -s "$B/feedback")"
tcontains "feedback a11y: stelele sunt butoane (operabile cu tastatura)" '<button type="button" data-v="1" class="star"' "$(curl -s "$B/feedback")"
# programare publica multilingva
tcontains "book RO implicit" 'Programare online' "$(curl -s "$B/book")"
tcontains "book EN (?lang=en)" 'Online booking' "$(curl -s "$B/book?lang=en")"
t "POST /book/{id}/waitlist -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/book/$SVC/waitlist --data-urlencode 'slot_start=2030-01-01 10:00:00' --data-urlencode 'email=wl@ci.ro')"
# rezervarea publica (slot in trecut -> respinsa cu redirect, dar calea cu rate-limit nu da 500)
t "POST /book/{id} (slot trecut) -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/book/$SVC --data-urlencode 'slot_start=2000-01-01 10:00:00' --data-urlencode 'name=CI')"
# consimtamant GDPR la programare: checkbox in formular + respins fara acord
tcontains "book: checkbox de consimtamant in formular" 'name="consent"' "$(curl -s "$B/book/$SVC")"
t "POST /book/{id} fara consimtamant -> 302 (respins)" 302 "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/book/$SVC --data-urlencode "slot_start=$(date -d tomorrow +%F 2>/dev/null || date -v+1d +%F) 10:00:00" --data-urlencode 'name=CI')"
# pagini legale (GDPR): confidentialitate + termeni + aliasuri + footer
tcontains "GET /legal/privacy -> politica" 'Politica de confiden' "$(curl -s "$B/legal/privacy")"
tcontains "GET /legal/terms -> termeni" 'Termeni' "$(curl -s "$B/legal/terms")"
tcontains "GET /confidentialitate (alias)" 'confiden' "$(curl -s "$B/confidentialitate")"
tcontains "GET /termeni (alias)" 'Termeni' "$(curl -s "$B/termeni")"
tcontains "legal: referinta ANSPDCP (drept de plangere)" 'ANSPDCP' "$(curl -s "$B/legal/privacy")"
tcontains "footer public: link confidentialitate pe portal" 'legal/privacy' "$(curl -s "$B/")"
# fonturi gazduite local (fara Google Fonts) — GDPR + offline. /assets/ e servit de Apache in
# productie (nu de routerul PHP), deci verificam fisierele pe disc + referintele din HTML.
if [ -f assets/fonts.css ] && grep -q '@font-face' assets/fonts.css; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "FAIL: assets/fonts.css lipsa sau fara @font-face"; fi
if [ -f assets/fonts/manrope-latin.woff2 ]; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "FAIL: woff2 local lipsa"; fi
tcontains "portal refera fonts.css local" 'fonts.css' "$(curl -s "$B/")"
if curl -s "$B/" | grep -q 'fonts.googleapis.com'; then FAIL=$((FAIL+1)); echo "FAIL: portalul inca refera googleapis"; else PASS=$((PASS+1)); fi
if grep -rq 'fonts.googleapis.com' app/views/; then FAIL=$((FAIL+1)); echo "FAIL: views inca refera Google Fonts"; else PASS=$((PASS+1)); fi
# cod QR local (SVG) — inlocuieste serviciul extern qrserver
tcontains "GET /qr -> image/svg+xml" 'image/svg+xml' "$(curl -s -D - -o /dev/null "$B/qr?data=hello&size=120" | grep -i content-type)"
tcontains "GET /qr body contine <svg" '<svg' "$(curl -s "$B/qr?data=https://exemplu.ro/t/abc")"
# QR-ul TREBUIE sa contina module reale (regresie: /qr citea body in loc de query => cod gol)
tcontains "GET /qr genereaza module (nu SVG gol)" '<rect' "$(curl -s "$B/qr?data=https://exemplu.ro/t/abc123&size=160")"

# --- emitere bon prin API public (dispenser) ---
ISS="$(curl -s -X POST $B/api/ticket -H 'Content-Type: application/json' -d "{\"device_key\":\"$DKEY\",\"service_id\":$SVC,\"channel\":\"paper\"}")"
tcontains "POST /api/ticket ok" '"ok":true' "$ISS"
tcontains "POST /api/ticket has label" '"label"' "$ISS"
# securitate: fara cheie de dispozitiv valida, emiterea e respinsa (nu se poate inunda coada)
t "POST /api/ticket fara cheie -> 403" 403 "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/api/ticket -H 'Content-Type: application/json' -d "{\"service_id\":$SVC,\"channel\":\"paper\"}")"
t "POST /api/ticket cheie gresita -> 403" 403 "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/api/ticket -H 'Content-Type: application/json' -d "{\"device_key\":\"NUEXISTA\",\"service_id\":$SVC}")"
t "GET /api/state"         200 "$(code "$B/api/state?branch=$BR")"
# bilet digital pe telefon: pagina de urmarire + notificari locale
VTOK="$(printf '%s' "$ISS" | python3 -c "import sys,json; print(json.load(sys.stdin).get('ticket',{}).get('public_token',''))" 2>/dev/null)"
if [ -n "$VTOK" ]; then
  t "GET /t/{token} (bilet digital)" 200 "$(code "$B/t/$VTOK")"
  tcontains "bilet digital: buton notificare in browser" 'vNotify' "$(curl -s "$B/t/$VTOK")"
  tcontains "bilet digital EN (?lang=en)" 'Notify me' "$(curl -s "$B/t/$VTOK?lang=en")"
else { FAIL=$((FAIL+1)); echo "FAIL: public_token lipsa la emitere"; }; fi
tcontains "sw.js are handler notificationclick" 'notificationclick' "$(curl -s "$B/sw.js")"

# --- login cu CSRF ---
CSRF="$(curl -s -c "$JAR" $B/login | grep -oE 'name="_csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
[ -n "$CSRF" ] && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "FAIL: extract login CSRF"; }
t "POST /login bad creds -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" -X POST $B/login -d "_csrf=$CSRF&email=admin@example.ro&password=GRESIT")"
t "POST /login ok -> 302"        302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" -X POST $B/login -d "_csrf=$CSRF&email=admin@example.ro&password=123456")"
DASH="$(curl -s -b "$JAR" "$B/admin")"
t "GET /admin (autentificat)"    200 "$(code -b "$JAR" $B/admin)"
# securitate: actiunile de operator care modifica starea resping GET (anti-CSRF prin GET)
t "API mutatie (cancel) prin GET -> 405" 405 "$(code -b "$JAR" "$B/api/cancel?ticket_id=1")"
t "API mutatie (finish) prin GET -> 405" 405 "$(code -b "$JAR" "$B/api/finish?ticket_id=1")"
t "API counter-state read-only prin GET -> 200" 200 "$(code -b "$JAR" "$B/api/counter-state?counter_id=$CTR")"
tcontains "checklist onboarding are pasul operatori" 'Adauga operatori' "$DASH"
tcontains "a11y: toggle grafic/tabel are aria-pressed" 'aria-pressed' "$DASH"
tcontains "a11y: SVG-uri date au role=img" 'role="img"' "$DASH"
t "GET /admin/statistics"        200 "$(code -b "$JAR" $B/admin/statistics)"
tcontains "statistici au sectiunea Programari online" 'Programari online' "$(curl -s -b "$JAR" "$B/admin/statistics")"
t "GET /admin/closures"          200 "$(code -b "$JAR" $B/admin/closures)"
t "GET /admin/help"              200 "$(code -b "$JAR" $B/admin/help)"
tcontains "help documenteaza formatele CSV" 'nume,email,rol,parola' "$(curl -s -b "$JAR" "$B/admin/help")"
t "GET /admin/devices/qr"        200 "$(code -b "$JAR" $B/admin/devices/qr)"

# --- exporturi (autentificat) ---
TODAY="$(date +%F)"
CT_CSV="$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/tickets/export?date=$TODAY" | grep -i 'content-type')"
tcontains "export bilete CSV content-type" 'text/csv' "$CT_CSV"
CFG_JSON="$(curl -s -b "$JAR" "$B/admin/settings/export")"
tcontains "export config JSON" '"settings"' "$CFG_JSON"
# reorganizare: backup DB + tab Automatizari mutate in Setari; API nu mai are backup
SET_PAGE="$(curl -s -b "$JAR" "$B/admin/settings")"
tcontains "Setari are backup baza de date (mutat din API)" 'Backup bază de date' "$SET_PAGE"
tcontains "Setari are tab Automatizari" 'data-tab="auto"' "$SET_PAGE"
case "$(curl -s -b "$JAR" "$B/admin/api")" in *'Backup baza de date'*) FAIL=$((FAIL+1)); echo "FAIL: API inca are backup DB";; *) PASS=$((PASS+1));; esac
# backup pe server: ruleaza -> apare in lista -> se descarca; traversare de cale respinsa
t "POST /admin/backup/run -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST "$B/admin/backup/run" -d "_csrf=$CSRF")"
BKFILE="$(curl -s -b "$JAR" "$B/admin/settings" | grep -oE 'backup_[0-9]{8}_[0-9]{6}\.sql' | head -1)"
if [ -n "$BKFILE" ]; then
  PASS=$((PASS+1))
  CT_BK="$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/backup/download?file=$BKFILE" | grep -i 'content-type')"
  tcontains "download backup pe server -> application/sql" 'application/sql' "$CT_BK"
else FAIL=$((FAIL+1)); echo "FAIL: backup pe server nu apare in lista"; fi
t "backup download: traversare de cale respinsa -> 404" 404 "$(code -b "$JAR" "$B/admin/backup/download?file=../config/config.php")"
# verificare productie (readiness): pagina de diagnoza, doar admin
t "GET /admin/checkup -> 200" 200 "$(code -b "$JAR" $B/admin/checkup)"
tcontains "checkup: pagina de verificare productie" 'Verificare producție' "$(curl -s -b "$JAR" "$B/admin/checkup")"
# securitate media: SVG (poate contine <script>) e RESPINS la upload -> anti-XSS stocat
SVGF="$(mktemp)"; printf '%s' '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' > "$SVGF"
curl -s -o /dev/null -b "$JAR" -X POST "$B/admin/media" -F "_csrf=$CSRF" -F "file[]=@$SVGF;type=image/svg+xml;filename=evil.svg"
case "$(curl -s -b "$JAR" "$B/admin/media")" in *.svg*) FAIL=$((FAIL+1)); echo "FAIL: SVG acceptat la upload media (risc XSS stocat)";; *) PASS=$((PASS+1));; esac
rm -f "$SVGF"
# pregatire productie: resetul cere confirmarea exacta „STERGE" (altfel respins, fara stergere)
t "POST /admin/reset confirmare gresita -> 302 (respins)" 302 "$(code -b "$JAR" -X POST "$B/admin/reset" -d "_csrf=$CSRF&confirm=nu")"
tcontains "settings: card de pregatire productie (admin)" 'Pregătire pentru producție' "$(curl -s -b "$JAR" "$B/admin/settings")"
CT_APPT="$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/appointments/export?date=$TODAY" | grep -i 'content-type')"
tcontains "export programari CSV content-type" 'text/csv' "$CT_APPT"
tcontains "admin appointments are lista de asteptare" 'Listă de așteptare' "$(curl -s -b "$JAR" "$B/admin/appointments")"
# GDPR: pagina drepturilor persoanei vizate (export/anonimizare) — doar admin
t "GET /admin/gdpr -> 200" 200 "$(code -b "$JAR" $B/admin/gdpr)"
tcontains "gdpr: formular de cautare email" 'name="q_email"' "$(curl -s -b "$JAR" "$B/admin/gdpr")"
GDPR_EXPORT_CT="$(curl -s -b "$JAR" -D - -o /dev/null -X POST "$B/admin/gdpr/export" -d "_csrf=$CSRF&q_email=nobody@ci.ro" | grep -i 'content-type')"
tcontains "gdpr export -> JSON" 'application/json' "$GDPR_EXPORT_CT"
CT_FB="$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/feedback/export" | grep -i 'content-type')"
tcontains "export feedback CSV content-type" 'text/csv' "$CT_FB"
# injectie de formule: un comentariu public care incepe cu '=' e neutralizat in exportul CSV
curl -s -o /dev/null -X POST "$B/feedback?branch=$BR" --data-urlencode 'rating=3' --data-urlencode 'comment==DANGER123'
tcontains "export feedback neutralizeaza injectia de formule" "'=DANGER123" "$(curl -s -b "$JAR" "$B/admin/feedback/export")"
# feedback public legat de bonul servit (din biletul digital) -> apare in admin cu eticheta bonului
if [ -n "${VTOK:-}" ]; then
  VLABEL="$(printf '%s' "$ISS" | python3 -c "import sys,json;print(json.load(sys.stdin)['ticket']['label'])" 2>/dev/null)"
  VID="$(printf '%s' "$ISS" | python3 -c "import sys,json;print(json.load(sys.stdin)['ticket']['id'])" 2>/dev/null)"
  # serveste bonul (admin la ghiseul CTR) ca sa aiba operator -> activeaza CSAT pe operator
  ACSRF="$(curl -s -b "$JAR" $B/admin | grep -oE 'name="csrf" content="[^"]+"' | sed -E 's/.*content="([^"]+)".*/\1/')"
  curl -s -o /dev/null -b "$JAR" -X POST $B/api/call-specific -H "X-CSRF: $ACSRF" -H 'Content-Type: application/json' -d "{\"ticket_id\":$VID,\"counter_id\":$CTR}"
  curl -s -o /dev/null -b "$JAR" -X POST $B/api/finish -H "X-CSRF: $ACSRF" -H 'Content-Type: application/json' -d "{\"ticket_id\":$VID}"
  curl -s -o /dev/null -X POST "$B/feedback?t=$VTOK" --data-urlencode 'rating=5' --data-urlencode 'comment=CI feedback legat de bon'
  tcontains "feedback public retine eticheta bonului in admin" "$VLABEL" "$(curl -s -b "$JAR" "$B/admin/feedback")"
  tcontains "pagina feedback poarta tokenul bonului (camp ascuns)" 'name="t"' "$(curl -s "$B/feedback?t=$VTOK&branch=$BR")"
  # CSAT pe serviciu + pe operator in statistici (feedback legat de bon servit de un operator)
  STAT_PAGE="$(curl -s -b "$JAR" "$B/admin/statistics?from=$TODAY&to=$TODAY")"
  tcontains "statistici au sectiunea CSAT pe serviciu" 'Nota medie pe serviciu' "$STAT_PAGE"
  tcontains "statistici au sectiunea CSAT pe operator" 'Nota medie pe operator' "$STAT_PAGE"
  tcontains "export CSAT pe serviciu CSV content-type" 'text/csv' "$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/statistics?export=csv&dataset=csat&from=$TODAY&to=$TODAY" | grep -i 'content-type')"
  tcontains "export CSAT pe operator CSV content-type" 'text/csv' "$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/statistics?export=csv&dataset=csat_op&from=$TODAY&to=$TODAY" | grep -i 'content-type')"
fi
XLSX_SIG="$(curl -s -b "$JAR" "$B/admin/statistics?export=xlsx" | head -c 2)"
[ "$XLSX_SIG" = "PK" ] && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "FAIL: stats xlsx not a zip (got '$XLSX_SIG')"; }
CT_OPA="$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/statistics?export=csv&dataset=op_activity" | grep -i 'content-type')"
tcontains "export activitate operatori CSV" 'text/csv' "$CT_OPA"

# --- export/import servicii din CSV (autentificat) ---
CT_SVC="$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/services/export" | grep -i 'content-type')"
tcontains "export servicii CSV content-type" 'text/csv' "$CT_SVC"
ICSRF="$(curl -s -b "$JAR" $B/admin | grep -oE 'name="csrf" content="[^"]+"' | sed -E 's/.*content="([^"]+)".*/\1/')"
t "POST /admin/services/import -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST $B/admin/services/import --data-urlencode "_csrf=$ICSRF" --data-urlencode "branch_id=$BR" --data-urlencode $'csv=ZZ,Serviciu Importat CI,#16a34a')"
tcontains "serviciul importat apare in lista" 'Serviciu Importat CI' "$(curl -s -b "$JAR" "$B/admin/services")"
# re-import acelasi prefix -> nu se dubleaza
curl -s -o /dev/null -b "$JAR" -X POST $B/admin/services/import --data-urlencode "_csrf=$ICSRF" --data-urlencode "branch_id=$BR" --data-urlencode $'csv=ZZ,Duplicat,#000000'
ZZ_COUNT="$(curl -s -b "$JAR" "$B/admin/services/export" | grep -c '^ZZ,')"
[ "$ZZ_COUNT" = "1" ] && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "FAIL: prefix ZZ duplicat la re-import (count=$ZZ_COUNT)"; }
# formularul manual respinge prefixul duplicat (prefix unic pe filiala)
curl -s -o /dev/null -b "$JAR" -X POST $B/admin/services --data-urlencode "_csrf=$ICSRF" --data-urlencode "branch_id=$BR" --data-urlencode "prefix=ZZ" --data-urlencode "name=Manual Dup"
ZZ_COUNT2="$(curl -s -b "$JAR" "$B/admin/services/export" | grep -c '^ZZ,')"
[ "$ZZ_COUNT2" = "1" ] && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "FAIL: prefix ZZ duplicat acceptat din formular (count=$ZZ_COUNT2)"; }

# --- reordonare servicii: butoane a11y + endpoint ---
tcontains "servicii: butoane reordonare (a11y)" 'data-mv="up"' "$(curl -s -b "$JAR" "$B/admin/services")"
tcontains "reorder servicii -> ok" '"ok":true' "$(curl -s -b "$JAR" -X POST $B/admin/services/reorder -H 'Content-Type: application/json' -H "X-CSRF: $ICSRF" -d "{\"ids\":[$SVC]}")"

# --- grupuri: creeaza un grup, apoi verifica butoanele de reordonare a11y ---
curl -s -o /dev/null -b "$JAR" -X POST $B/admin/groups --data-urlencode "_csrf=$ICSRF" --data-urlencode "branch_id=$BR" --data-urlencode "name=Grup CI" --data-urlencode "color=#64748b" --data-urlencode "sort_order=0"
tcontains "grupuri: butoane reordonare (a11y)" 'data-mv="up"' "$(curl -s -b "$JAR" "$B/admin/groups")"

# --- webhook de test (fara URL configurat -> eroare clara, fara 500) ---
WHT="$(curl -s -b "$JAR" -X POST $B/admin/api/test-webhook --data-urlencode "_csrf=$ICSRF")"
tcontains "test-webhook fara URL -> ok:false" '"ok":false' "$WHT"
tcontains "test-webhook mesaj despre URL" 'URL' "$WHT"
API_PAGE="$(curl -s -b "$JAR" "$B/admin/api")"
tcontains "pagina API are jurnal livrari webhook" 'Jurnal livrări webhook' "$API_PAGE"
tcontains "pagina API listeaza evenimentul feedback.low" 'feedback.low' "$API_PAGE"
tcontains "export jurnal webhook CSV content-type" 'text/csv' "$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/api/webhook-log-export" | grep -i 'content-type')"

# --- export/import ghisee din CSV (autentificat) ---
tcontains "export ghisee CSV content-type" 'text/csv' "$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/counters/export" | grep -i 'content-type')"
t "POST /admin/counters/import -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST $B/admin/counters/import --data-urlencode "_csrf=$ICSRF" --data-urlencode "branch_id=$BR" --data-urlencode $'csv=GCI,Ghiseu Importat CI')"
tcontains "ghiseul importat apare in lista" 'Ghiseu Importat CI' "$(curl -s -b "$JAR" "$B/admin/counters")"
# formularul manual respinge codul de ghiseu duplicat (cod unic pe filiala)
curl -s -o /dev/null -b "$JAR" -X POST $B/admin/counters --data-urlencode "_csrf=$ICSRF" --data-urlencode "branch_id=$BR" --data-urlencode "code=GCI" --data-urlencode "name=Dup ghiseu"
GCI_COUNT="$(curl -s -b "$JAR" "$B/admin/counters/export" | grep -c '^GCI,')"
[ "$GCI_COUNT" = "1" ] && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "FAIL: cod GCI duplicat acceptat din formular (count=$GCI_COUNT)"; }

# --- export/import filiale din CSV (autentificat) ---
tcontains "export filiale CSV content-type" 'text/csv' "$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/branches/export" | grep -i 'content-type')"
t "POST /admin/branches/import -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST $B/admin/branches/import --data-urlencode "_csrf=$ICSRF" --data-urlencode $'csv=Filiala Importata CI,Cluj,Str. Test 1')"
tcontains "filiala importata apare in lista" 'Filiala Importata CI' "$(curl -s -b "$JAR" "$B/admin/branches")"

# --- export/import utilizatori din CSV (autentificat) ---
tcontains "export utilizatori CSV content-type" 'text/csv' "$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/users/export" | grep -i 'content-type')"
# exportul NU trebuie sa contina hash-uri de parola
USR_EXP="$(curl -s -b "$JAR" "$B/admin/users/export")"
case "$USR_EXP" in *'$2y$'*) FAIL=$((FAIL+1)); echo "FAIL: export utilizatori contine hash parola";; *) PASS=$((PASS+1));; esac
t "POST /admin/users/import -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST $B/admin/users/import --data-urlencode "_csrf=$ICSRF" --data-urlencode $'csv=Operator Importat CI,opci@firma.ro,agent,ParolaCI123')"
tcontains "utilizatorul importat apare in lista" 'Operator Importat CI' "$(curl -s -b "$JAR" "$B/admin/users")"

# --- export/import zile inchise din CSV (autentificat) ---
tcontains "export zile inchise CSV content-type" 'text/csv' "$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/closures/export" | grep -i 'content-type')"
t "POST /admin/closures/import -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST $B/admin/closures/import --data-urlencode "_csrf=$ICSRF" --data-urlencode "branch_id=0" --data-urlencode $'csv=2030-12-25,Craciun CI')"
tcontains "ziua inchisa importata apare in lista" 'Craciun CI' "$(curl -s -b "$JAR" "$B/admin/closures")"

# --- sabloane CSV goale (doar antetul, fara date) ---
BR_TMPL="$(curl -s -b "$JAR" "$B/admin/branches/export?template=1")"
tcontains "sablon filiale are antetul" 'nume,oras,adresa' "$BR_TMPL"
case "$BR_TMPL" in *'Filiala Importata CI'*) FAIL=$((FAIL+1)); echo "FAIL: sablonul filiale contine date";; *) PASS=$((PASS+1));; esac
# sablonul utilizatori include coloana 'parola' (spre deosebire de exportul real)
USR_TMPL="$(curl -s -b "$JAR" "$B/admin/users/export?template=1")"
tcontains "sablon utilizatori include coloana parola" 'nume,email,rol,parola' "$USR_TMPL"

# --- import prin INCARCARE FISIER .csv (multipart $_FILES) ---
CSVUP="$(mktemp)"; printf 'nume,oras,adresa\nFiliala Fisier CI,Iasi,Bd. Upload 9\n' > "$CSVUP"
t "POST /admin/branches/import (fisier) -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST $B/admin/branches/import -F "_csrf=$ICSRF" -F "file=@$CSVUP;type=text/csv")"
tcontains "filiala din fisier apare in lista" 'Filiala Fisier CI' "$(curl -s -b "$JAR" "$B/admin/branches")"
rm -f "$CSVUP"

# --- CSRF lipsa pe POST autentificat => respins (419) ---
t "POST fara CSRF -> 419" 419 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST $B/api/call-next -H 'Content-Type: application/json' -d "{\"counter_id\":$CTR}")"

# --- API v1 (cheie) ---
t "GET /api/v1/state no key -> 401" 401 "$(code "$B/api/v1/state?branch=$BR")"
ST_API="$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/state?branch=$BR")"
tcontains "GET /api/v1/state with key" '"ok":true' "$ST_API"
tcontains "GET /api/v1/branches" '"branches"' "$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/branches")"
tcontains "POST /api/v1/feedback ok" '"ok":true' "$(curl -s -X POST -H "X-Api-Key: $AKEY" -H 'Content-Type: application/json' -d '{"rating":5,"comment":"CI test"}' "$B/api/v1/feedback")"
t "POST /api/v1/feedback rating invalid -> 422" 422 "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H "X-Api-Key: $AKEY" -H 'Content-Type: application/json' -d '{"rating":9}' "$B/api/v1/feedback")"
ISS_API="$(curl -s -X POST -H "X-Api-Key: $AKEY" -H 'Content-Type: application/json' -d "{\"service_id\":$SVC}" "$B/api/v1/tickets")"
tcontains "POST /api/v1/tickets issues" '"label"' "$ISS_API"
# anuleaza biletul emis prin API
TTOK="$(printf '%s' "$ISS_API" | python3 -c "import sys,json; print(json.load(sys.stdin).get('ticket',{}).get('public_token',''))" 2>/dev/null)"
if [ -n "$TTOK" ]; then
  t "DELETE /api/v1/tickets/{token}" 200 "$(curl -s -o /dev/null -w '%{http_code}' -X DELETE -H "X-Api-Key: $AKEY" "$B/api/v1/tickets/$TTOK")"
  tcontains "ticket cancelled via API" 'cancelled' "$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/tickets/$TTOK")"
fi
# programari via API: sloturi -> rezervare -> status
TOMORROW="$(date -d '+1 day' +%F 2>/dev/null || date -v+1d +%F)"
SLOTS_API="$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/slots?service_id=$SVC&date=$TOMORROW")"
tcontains "GET /api/v1/slots" '"slots"' "$SLOTS_API"
# trei sloturi libere distincte (separator TAB: slot_start contine spatiu!)
IFS=$'\t' read -r SLOT SLOT2 SLOT3 <<< "$(printf '%s' "$SLOTS_API" | python3 -c "import sys,json; d=json.load(sys.stdin); s=[x['start'] for x in d.get('slots',[]) if not x['full'] and not x['past']]; print((s[0] if s else '')+chr(9)+(s[1] if len(s)>1 else '')+chr(9)+(s[2] if len(s)>2 else ''))" 2>/dev/null)"
if [ -n "$SLOT" ]; then
  APPT_API="$(curl -s -X POST -H "X-Api-Key: $AKEY" -H 'Content-Type: application/json' -d "{\"service_id\":$SVC,\"slot_start\":\"$SLOT\",\"name\":\"CI\"}" "$B/api/v1/appointments")"
  tcontains "POST /api/v1/appointments books" '"public_token"' "$APPT_API"
  ATOK="$(printf '%s' "$APPT_API" | python3 -c "import sys,json; print(json.load(sys.stdin).get('appointment',{}).get('public_token',''))" 2>/dev/null)"
  if [ -n "$ATOK" ]; then
    tcontains "GET /api/v1/appointments/{token}" '"status"' "$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/appointments/$ATOK")"
    # lista programarilor (sincronizare integratori)
    TMW="$(date -d tomorrow +%F 2>/dev/null || date -v+1d +%F)"
    tcontains "GET /api/v1/appointments (lista)" '"appointments"' "$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/appointments?date=$TMW")"
    # filtrul service_id din query string e respectat (regresie: se citea body-ul, deci era ignorat)
    tcontains "GET /api/v1/appointments?service_id corect -> include programarea" "$ATOK" "$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/appointments?date=$TMW&service_id=$SVC")"
    case "$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/appointments?date=$TMW&service_id=999999")" in *"$ATOK"*) FAIL=$((FAIL+1)); echo "FAIL: filtru service_id ignorat (token aparut pe alt serviciu)";; *) PASS=$((PASS+1));; esac
    # „Adauga in calendar" — fisier iCalendar (public, fara servicii externe)
    tcontains "GET a/{token}/ics -> text/calendar" 'text/calendar' "$(curl -s -D - -o /dev/null "$B/a/$ATOK/ics" | grep -i 'content-type')"
    tcontains "ics contine VEVENT" 'BEGIN:VEVENT' "$(curl -s "$B/a/$ATOK/ics")"
    # pagina de programare multilingva
    tcontains "appointment EN (?lang=en)" 'Add to calendar' "$(curl -s "$B/a/$ATOK?lang=en")"
    # stare programare live: endpoint de polling + script in pagina
    tcontains "GET /api/appt -> status" '"status"' "$(curl -s "$B/api/appt?token=$ATOK")"
    tcontains "GET /api/appt -> slot_ts" '"slot_ts"' "$(curl -s "$B/api/appt?token=$ATOK")"
    t "GET /api/appt token gresit -> 404" 404 "$(code "$B/api/appt?token=inexistent_xyz")"
    tcontains "pagina programarii face polling live (api/appt)" 'api/appt' "$(curl -s "$B/a/$ATOK")"
    # reprogrameaza in al doilea slot, apoi anuleaza
    [ -n "$SLOT2" ] && tcontains "POST /api/v1/appointments/{token}/reschedule" '"ok":true' "$(curl -s -X POST -H "X-Api-Key: $AKEY" -H 'Content-Type: application/json' -d "{\"slot_start\":\"$SLOT2\"}" "$B/api/v1/appointments/$ATOK/reschedule")"
    t "DELETE /api/v1/appointments/{token}" 200 "$(curl -s -o /dev/null -w '%{http_code}' -X DELETE -H "X-Api-Key: $AKEY" "$B/api/v1/appointments/$ATOK")"
  else { FAIL=$((FAIL+1)); echo "FAIL: appointment token missing"; }; fi
  # check-in via API pe al treilea slot -> genereaza bon
  if [ -n "$SLOT3" ]; then
    APPT2="$(curl -s -X POST -H "X-Api-Key: $AKEY" -H 'Content-Type: application/json' -d "{\"service_id\":$SVC,\"slot_start\":\"$SLOT3\",\"name\":\"CI2\"}" "$B/api/v1/appointments")"
    ATOK2="$(printf '%s' "$APPT2" | python3 -c "import sys,json; print(json.load(sys.stdin).get('appointment',{}).get('public_token',''))" 2>/dev/null)"
    [ -n "$ATOK2" ] && tcontains "POST /api/v1/appointments/{token}/checkin -> bon" '"label"' "$(curl -s -X POST -H "X-Api-Key: $AKEY" "$B/api/v1/appointments/$ATOK2/checkin")"
  fi
else
  echo "WARN: niciun slot liber maine (skip booking via API)"
fi

# nume foarte lung NU trebuie sa scurga eroarea bruta de DB (se limiteaza la dimensiunea coloanei)
DAT5="$(date -d '+5 day' +%F 2>/dev/null || date -v+5d +%F)"
LSLOT="$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/slots?service_id=$SVC&date=$DAT5" | python3 -c "import sys,json;d=json.load(sys.stdin);s=[x['start'] for x in d.get('slots',[]) if not x['full'] and not x['past']];print(s[0] if s else '')" 2>/dev/null)"
if [ -n "$LSLOT" ]; then
  LONGNAME="$(printf 'X%.0s' $(seq 1 300))"
  LRESP="$(curl -s -X POST -H "X-Api-Key: $AKEY" -H 'Content-Type: application/json' -d "{\"service_id\":$SVC,\"slot_start\":\"$LSLOT\",\"name\":\"$LONGNAME\"}" "$B/api/v1/appointments")"
  tcontains "API: nume lung -> rezervare reusita (limitat, nu eroare)" '"public_token"' "$LRESP"
  case "$LRESP" in *SQLSTATE*|*"Data too long"*) FAIL=$((FAIL+1)); echo "FAIL: API scurge eroarea bruta de DB la nume lung";; *) PASS=$((PASS+1));; esac
fi

# --- consimtamant GDPR: rezervare prin formularul public CU acord -> dovada in export ---
DAT="$(date -d '+2 day' +%F 2>/dev/null || date -v+2d +%F)"
CSLOT="$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/slots?service_id=$SVC&date=$DAT" | python3 -c "import sys,json;d=json.load(sys.stdin);s=[x['start'] for x in d.get('slots',[]) if not x['full'] and not x['past']];print(s[0] if s else '')" 2>/dev/null)"
if [ -n "$CSLOT" ]; then
  t "POST /book/{id} cu consimtamant -> 302 (succes)" 302 "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/book/$SVC --data-urlencode "slot_start=$CSLOT" --data-urlencode 'name=Consent CI' --data-urlencode 'email=consent-ci@ci.ro' --data-urlencode 'consent=1')"
  GEXP="$(curl -s -b "$JAR" -X POST "$B/admin/gdpr/export" -d "_csrf=$CSRF&q_email=consent-ci@ci.ro")"
  tcontains "consent: dovada (consent_at) in exportul GDPR" 'consent_at' "$GEXP"
  tcontains "consent: emailul rezervarii apare in export" 'consent-ci@ci.ro' "$GEXP"
else
  echo "WARN: niciun slot liber peste 2 zile (skip test consimtamant)"
fi

# --- IDOR: allowed_counters aplicat si pe API, nu doar in UI ---
# agent legat de un ghiseu inexistent (999999) => orice ghiseu real ii e interzis
curl -s -o /dev/null -b "$JAR" -X POST $B/admin/users --data-urlencode "_csrf=$ICSRF" \
  --data-urlencode "name=Pinned CI" --data-urlencode "email=pinnedci@firma.ro" \
  --data-urlencode "role=agent" --data-urlencode "active=1" \
  --data-urlencode "password=PinnedCI123" --data-urlencode "allowed_counters[]=999999"
PJAR="$(mktemp)"
PCSRF="$(curl -s -c "$PJAR" $B/login | grep -oE 'name="_csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
curl -s -o /dev/null -b "$PJAR" -c "$PJAR" -X POST $B/login -d "_csrf=$PCSRF&email=pinnedci@firma.ro&password=PinnedCI123"
PACSRF="$(curl -s -b "$PJAR" "$B/counter" | grep -oE 'name="csrf" content="[^"]+"' | head -1 | sed -E 's/.*content="([^"]+)".*/\1/')"
t "API call-next pe ghiseu nepermis -> 403" 403 "$(curl -s -o /dev/null -w '%{http_code}' -b "$PJAR" -X POST $B/api/call-next -H "X-CSRF: $PACSRF" -H 'Content-Type: application/json' -d "{\"counter_id\":$CTR}")"
t "API counter-pause pe ghiseu nepermis -> 403" 403 "$(curl -s -o /dev/null -w '%{http_code}' -b "$PJAR" -X POST $B/api/counter-pause -H "X-CSRF: $PACSRF" -H 'Content-Type: application/json' -d "{\"counter_id\":$CTR}")"
rm -f "$PJAR"

# --- facturare landlord (login separat, emitere factura, pagina printabila) ---
LJAR="$(mktemp)"
LCSRF="$(curl -s -c "$LJAR" "$B/landlord" | grep -oE 'name="_csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
curl -s -o /dev/null -b "$LJAR" -c "$LJAR" -X POST "$B/landlord" -d "_csrf=$LCSRF&password=httppass"
LCSRF2="$(curl -s -b "$LJAR" "$B/landlord/billing" | grep -oE 'name="_csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
t "GET /landlord/billing (autentificat) -> 200" 200 "$(code -b "$LJAR" "$B/landlord/billing")"
tcontains "landlord billing: formular emitent" 'name="b_name"' "$(curl -s -b "$LJAR" "$B/landlord/billing")"
curl -s -o /dev/null -b "$LJAR" -X POST "$B/landlord/billing-settings" -d "_csrf=$LCSRF2&b_name=Firma+CI&b_series=BDO&b_currency=RON&b_vat=19"
INV_LOC="$(curl -s -b "$LJAR" -o /dev/null -D - -X POST "$B/landlord/invoice-save" --data-urlencode "_csrf=$LCSRF2" --data-urlencode "client_name=Client CI" --data-urlencode "amount=49" --data-urlencode "vat_percent=19" | grep -i '^location:' | tr -d '\r')"
INV_ID="$(printf '%s' "$INV_LOC" | grep -oE 'id=[a-f0-9]+' | head -1 | cut -d= -f2)"
if [ -n "$INV_ID" ]; then
  INV_HTML="$(curl -s -b "$LJAR" "$B/landlord/invoice?id=$INV_ID")"
  tcontains "factura printabila: total cu TVA 19% (49 -> 58.31)" '58.31' "$INV_HTML"
  tcontains "factura printabila: serie/numar BDO 00001" 'BDO 00001' "$INV_HTML"
  tcontains "factura printabila: buton print" 'window.print()' "$INV_HTML"
else FAIL=$((FAIL+1)); echo "FAIL: factura nu a fost creata (fara id in redirect)"; fi
rm -f "$LJAR"

# --- logout ---
t "GET /logout -> 302"   302 "$(code -b "$JAR" $B/logout)"

echo "HTTP: PASS=$PASS FAIL=$FAIL"
[ "$FAIL" -eq 0 ]
