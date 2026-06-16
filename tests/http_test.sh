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

# --- export/import ghisee din CSV (autentificat) ---
tcontains "export ghisee CSV content-type" 'text/csv' "$(curl -s -b "$JAR" -D - -o /dev/null "$B/admin/counters/export" | grep -i 'content-type')"
t "POST /admin/counters/import -> 302" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST $B/admin/counters/import --data-urlencode "_csrf=$ICSRF" --data-urlencode "branch_id=$BR" --data-urlencode $'csv=GCI,Ghiseu Importat CI')"
tcontains "ghiseul importat apare in lista" 'Ghiseu Importat CI' "$(curl -s -b "$JAR" "$B/admin/counters")"

# --- CSRF lipsa pe POST autentificat => respins (419) ---
t "POST fara CSRF -> 419" 419 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST $B/api/call-next -H 'Content-Type: application/json' -d "{\"counter_id\":$CTR}")"

# --- API v1 (cheie) ---
t "GET /api/v1/state no key -> 401" 401 "$(code "$B/api/v1/state?branch=$BR")"
ST_API="$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/state?branch=$BR")"
tcontains "GET /api/v1/state with key" '"ok":true' "$ST_API"
tcontains "GET /api/v1/branches" '"branches"' "$(curl -s -H "X-Api-Key: $AKEY" "$B/api/v1/branches")"
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

# --- logout ---
t "GET /logout -> 302"   302 "$(code -b "$JAR" $B/logout)"

echo "HTTP: PASS=$PASS FAIL=$FAIL"
[ "$FAIL" -eq 0 ]
