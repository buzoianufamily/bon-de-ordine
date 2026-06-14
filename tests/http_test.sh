#!/usr/bin/env bash
# Test HTTP end-to-end al stratului de rutare (index.php) cu serverul integrat PHP.
# Porneste php -S, instaleaza schema, ruleaza cereri reale (login/CSRF, emitere bon, exporturi,
# pagini admin, logout) si verifica codurile/raspunsurile. Iese !=0 daca pica ceva.
# Config DB din env: BDO_DB_HOST/PORT/NAME/USER/PASS (ca tests/integration.php).
set -u
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
cleanup(){ [ -n "$SRV" ] && kill "$SRV" 2>/dev/null; [ -f "$CFGBAK" ] && mv "$CFGBAK" config/config.php; rm -f "$JAR" "$SRVLOG"; }
trap cleanup EXIT

# instaleaza schema + ia id-uri necesare (foloseste env, nu config.php)
IDS="$(BDO_DB_HOST=$DBH BDO_DB_PORT=$DBP BDO_DB_NAME=$DBN BDO_DB_USER=$DBU BDO_DB_PASS=$DBW php tests/_ids.php 2>/dev/null | tail -1)"
read -r DKEY SVC CTR BR <<< "$IDS"
[ -n "${DKEY:-}" ] || { echo "FATAL: nu am putut instala/citi id-uri (IDS='$IDS')"; exit 1; }

php -S "$HOST:$PORT" index.php >"$SRVLOG" 2>&1 &
SRV=$!
for i in $(seq 1 40); do curl -fsS "http://$HOST:$PORT/health" >/dev/null 2>&1 && break; sleep 0.5; done

PASS=0; FAIL=0
B="http://$HOST:$PORT"
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
t(){ local d="$1" want="$2" got="$3"; if [ "$got" = "$want" ]; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "FAIL: $d (want $want, got $got)"; fi; }
tcontains(){ local d="$1" needle="$2" body="$3"; if printf '%s' "$body" | grep -q "$needle"; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "FAIL: $d (missing '$needle')"; fi; }

# --- public ---
t "GET /health"            200 "$(code $B/health)"
t "GET / (portal)"         200 "$(code $B/)"
t "GET /login"             200 "$(code $B/login)"
t "GET /login/forgot"      200 "$(code $B/login/forgot)"
t "GET /concierge anon->redirect" 302 "$(code $B/concierge)"

# --- emitere bon prin API public (dispenser) ---
ISS="$(curl -s -X POST $B/api/ticket -H 'Content-Type: application/json' -d "{\"device_key\":\"$DKEY\",\"service_id\":$SVC,\"channel\":\"paper\"}")"
tcontains "POST /api/ticket ok" '"ok":true' "$ISS"
tcontains "POST /api/ticket has label" '"label"' "$ISS"
t "GET /api/state"         200 "$(code "$B/api/state?branch=$BR")"

# --- login cu CSRF ---
CSRF="$(curl -s -c "$JAR" $B/login | grep -oE 'name="_csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
[ -n "$CSRF" ] && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "FAIL: extract login CSRF"; }
t "POST /login bad creds -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" -X POST $B/login -d "_csrf=$CSRF&email=admin@example.ro&password=GRESIT")"
t "POST /login ok -> 302"        302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" -X POST $B/login -d "_csrf=$CSRF&email=admin@example.ro&password=123456")"
t "GET /admin (autentificat)"    200 "$(code -b "$JAR" $B/admin)"
t "GET /admin/statistics"        200 "$(code -b "$JAR" $B/admin/statistics)"
t "GET /admin/closures"          200 "$(code -b "$JAR" $B/admin/closures)"
t "GET /admin/help"              200 "$(code -b "$JAR" $B/admin/help)"
t "GET /admin/devices/qr"        200 "$(code -b "$JAR" $B/admin/devices/qr)"

# --- exporturi (autentificat) ---
TODAY="$(date +%F)"
CT_CSV="$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/tickets/export?date=$TODAY" | grep -i 'content-type')"
tcontains "export bilete CSV content-type" 'text/csv' "$CT_CSV"
CFG_JSON="$(curl -s -b "$JAR" "$B/admin/settings/export")"
tcontains "export config JSON" '"settings"' "$CFG_JSON"
CT_APPT="$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/appointments/export?date=$TODAY" | grep -i 'content-type')"
tcontains "export programari CSV content-type" 'text/csv' "$CT_APPT"
CT_FB="$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/feedback/export" | grep -i 'content-type')"
tcontains "export feedback CSV content-type" 'text/csv' "$CT_FB"
XLSX_SIG="$(curl -s -b "$JAR" "$B/admin/statistics?export=xlsx" | head -c 2)"
[ "$XLSX_SIG" = "PK" ] && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "FAIL: stats xlsx not a zip (got '$XLSX_SIG')"; }
CT_OPA="$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/statistics?export=csv&dataset=op_activity" | grep -i 'content-type')"
tcontains "export activitate operatori CSV" 'text/csv' "$CT_OPA"

# --- CSRF lipsa pe POST autentificat => respins (419) ---
t "POST fara CSRF -> 419" 419 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST $B/api/call-next -H 'Content-Type: application/json' -d "{\"counter_id\":$CTR}")"

# --- logout ---
t "GET /logout -> 302"   302 "$(code -b "$JAR" $B/logout)"

echo "HTTP: PASS=$PASS FAIL=$FAIL"
[ "$FAIL" -eq 0 ]
