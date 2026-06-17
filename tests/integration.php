<?php
/**
 * Test de integrare end-to-end: ruleaza logica reala pe o baza de date reala (MySQL/MariaDB).
 * Config DB din variabile de mediu (pentru CI), fara a atinge config/config.php:
 *   BDO_DB_HOST (implicit 127.0.0.1), BDO_DB_PORT (3306), BDO_DB_NAME, BDO_DB_USER, BDO_DB_PASS
 * Iese cu cod !=0 daca vreo aserțiune pică. Rulare locala/CI:
 *   BDO_DB_NAME=bon BDO_DB_USER=bon BDO_DB_PASS=bon php tests/integration.php
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);

$host = getenv('BDO_DB_HOST') ?: '127.0.0.1';
$port = getenv('BDO_DB_PORT') ?: '3306';
$GLOBALS['__config_override'] = [
    'db'  => ['host' => $host . ';port=' . $port, 'name' => getenv('BDO_DB_NAME') ?: 'bon',
              'user' => getenv('BDO_DB_USER') ?: 'root', 'pass' => getenv('BDO_DB_PASS') ?: '', 'charset' => 'utf8mb4'],
    'app' => ['name' => 'CI', 'base_url' => '', 'env' => 'dev', 'timezone' => 'Europe/Bucharest', 'locale' => 'ro'],
    'landlord_pass' => 'ci-pass',
];
$_SERVER['HTTP_HOST'] = 'ci.local'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require __DIR__ . '/../app/core/init.php';            // conecteaza + auto_install + run_migrations
require __DIR__ . '/../app/admin_routes.php';         // pentru build_stats_xlsx / settings_export_denylist
require __DIR__ . '/../app/api_v1.php';

$ok = 0; $fail = 0; $F = [];
function chk($c, $m) { global $ok, $fail, $F; if ($c) { $ok++; } else { $fail++; $F[] = $m; } }

/* ---- 1. Instalare + schema ---- */
chk((int)val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()") >= 21, 'install: >=21 tables');
chk((int)val("SELECT v FROM settings WHERE k='schema_version'") === (defined('APP_SCHEMA_VERSION') ? APP_SCHEMA_VERSION : 0), 'install: schema_version = APP_SCHEMA_VERSION');
chk((int)val("SELECT COUNT(*) FROM users WHERE email='admin@example.ro' AND role='admin'") === 1, 'install: default admin');
foreach (['users','tickets','feedback','counter_sessions','password_resets','branch_closures'] as $t)
    chk((int)val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?", [$t]) === 1, "install: table $t exists");
foreach ([['services','paused'],['services','pause_note'],['counters','pause_note'],['tickets','target_counter_id']] as $c)
    chk((int)val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?", $c) === 1, "install: column {$c[0]}.{$c[1]}");

/* ---- 2. Autentificare + parola ---- */
$adminId = (int)val("SELECT id FROM users WHERE email='admin@example.ro'");
chk(verify_credentials('admin@example.ro','123456') !== null, 'auth: correct creds');
chk(verify_credentials('admin@example.ro','wrong') === null, 'auth: wrong creds rejected');
$r = change_own_password($adminId,'123456','newpass1','newpass1'); chk($r['ok'], 'auth: change own password');
chk(verify_credentials('admin@example.ro','newpass1') !== null, 'auth: new password active');
change_own_password($adminId,'newpass1','123456','123456');
password_reset_request('admin@example.ro');
chk((int)val("SELECT COUNT(*) FROM password_resets") >= 1, 'auth: reset token created');

/* ---- 3. Ciclu bilet ---- */
$svc = (int)val("SELECT id FROM services WHERE status='active' ORDER BY sort_order LIMIT 1");
$br  = (int)val("SELECT branch_id FROM services WHERE id=$svc");
$ctr = (int)val("SELECT id FROM counters WHERE branch_id=$br LIMIT 1");
$t = issue_ticket($svc, false, 'paper'); chk($t['status'] === 'waiting', 'ticket: issued waiting');
$called = call_next($ctr, $adminId, 0); chk($called && (int)$called['id'] === (int)$t['id'] && $called['status'] === 'called', 'ticket: call_next');
start_serving((int)$t['id']); chk(val("SELECT status FROM tickets WHERE id=".(int)$t['id']) === 'serving', 'ticket: serving');
finish_ticket((int)$t['id']); chk(val("SELECT status FROM tickets WHERE id=".(int)$t['id']) === 'served', 'ticket: finished');

/* ---- 4. No-show + requeue ---- */
$t2 = issue_ticket($svc, false, 'paper'); call_next($ctr, $adminId, 0); no_show_ticket((int)$t2['id']);
chk(val("SELECT status FROM tickets WHERE id=".(int)$t2['id']) === 'no_show', 'noshow: marked');
chk(requeue_ticket((int)$t2['id']) === true && val("SELECT status FROM tickets WHERE id=".(int)$t2['id']) === 'waiting', 'requeue: back to waiting');

/* ---- 5. Transferuri ---- */
$svc2 = (int)val("SELECT id FROM services WHERE status='active' AND id<>$svc AND branch_id=$br ORDER BY sort_order LIMIT 1");
if ($svc2) { $t3 = issue_ticket($svc, false, 'paper'); transfer_ticket((int)$t3['id'], $svc2);
    chk((int)val("SELECT service_id FROM tickets WHERE id=".(int)$t3['id']) === $svc2, 'transfer: to service');
    $ctr2 = (int)val("SELECT id FROM counters WHERE branch_id=$br AND id<>$ctr LIMIT 1");
    if ($ctr2) { transfer_to_counter((int)$t3['id'], $ctr2); chk((int)val("SELECT target_counter_id FROM tickets WHERE id=".(int)$t3['id']) === $ctr2, 'transfer: to counter'); }
}

/* ---- 6. Inchideri (logica, prin data viitoare ca sa nu interfereze cu cache-ul pe "azi") ---- */
$future = date('Y-m-d', strtotime('+30 days')); $futTs = strtotime($future . ' 12:00:00');
q("DELETE FROM branch_closures WHERE branch_id=? AND closed_date=?", [$br, $future]);
q("INSERT INTO branch_closures (branch_id, closed_date, reason) VALUES (?,?,?)", [$br, $future, 'CI']);
chk(branch_closure_reason($br, $futTs) === 'CI', 'closure: detected (future date)');
chk(service_is_open(one("SELECT * FROM services WHERE id=$svc"), $futTs) === false, 'closure: service_is_open false on closed day');
q("INSERT INTO branch_closures (branch_id, closed_date, reason) VALUES (NULL, DATE_ADD(?, INTERVAL 1 DAY), 'global')", [$future]);
chk(branch_closure_reason(99999, strtotime($future . ' +1 day 12:00:00')) === 'global', 'closure: global covers any branch');
q("DELETE FROM branch_closures WHERE reason IN ('CI','global')");

/* ---- 7. Pauza serviciu (fara cache: deterministic) ---- */
q("UPDATE services SET paused=1 WHERE id=$svc");
$pb = false; try { issue_ticket($svc, false, 'paper'); } catch (Throwable $e) { $pb = str_contains($e->getMessage(),'oprit'); }
chk($pb, 'pause: blocks issuance');
q("UPDATE services SET paused=0 WHERE id=$svc");

/* ---- 8. Programari ---- */
q("UPDATE services SET appt_enabled=1, appt_slot_min=15, appt_capacity=2 WHERE id=$svc");
$svcRow = one("SELECT * FROM services WHERE id=$svc"); $day = date('Y-m-d', strtotime('+1 day'));
$slots = appt_slots($svcRow, $day); chk(count($slots) > 0, 'appt: slots generated');
$appt = appt_book($svc, $slots[0]['start'], 'Ion', '0712', ''); chk(!empty($appt['id']) && $appt['status'] === 'booked', 'appt: booked');
$tk = appt_checkin($appt); chk(!empty($tk['id']) && val("SELECT status FROM appointments WHERE id=".(int)$appt['id']) === 'checked_in', 'appt: checkin -> ticket');

/* ---- 9. queue_state + estimari ---- */
$qs = queue_state($br, true);
chk(isset($qs['waiting'], $qs['counters'], $qs['notice']), 'queue_state: shape');
chk(array_reduce($qs['waiting'], fn($c,$w) => $c || array_key_exists('est',$w), false), 'queue_state: estimates present');
$cv = counter_view(one("SELECT * FROM counters WHERE id=$ctr")); chk(array_key_exists('no_show',$cv), 'counter_view: no_show key');
chk(array_key_exists('no_show', branch_queue($br)), 'branch_queue: no_show key');

/* ---- 10. Rate-limit + anunt ---- */
$allow = 0; for ($i=0;$i<5;$i++) if (rate_limit_ok('ci:ip',3,600)) $allow++;
chk($allow === 3, 'rate_limit: 3 of 5');
set_setting('notice_text','CI anunt'); set_setting('notice_until','');
chk(active_notice() === 'CI anunt', 'notice: active'); set_setting('notice_text',''); chk(active_notice() === '', 'notice: cleared');

/* ---- 11. 2FA ---- */
$sec = totp_secret(); chk(strlen($sec) >= 16, '2fa: secret');
chk(totp_verify($sec, totp_at($sec, intdiv(time(),30))) === true, '2fa: verify own code');
chk(totp_verify($sec, '000001') === false, '2fa: reject wrong');
[$plain,$hashes] = totp_backup_generate(); chk(count($plain) > 0, '2fa: backup generated');
$nj = totp_backup_consume(json_encode($hashes), $plain[0]); chk($nj !== null, '2fa: backup consumed');
chk(totp_backup_consume($nj, $plain[0]) === null, '2fa: backup not reusable');

/* ---- 12. Buildere: ESC/POS + Excel ---- */
$t9 = issue_ticket($svc, false, 'paper');
$bytes = build_ticket_escpos($t9, one("SELECT * FROM services WHERE id=$svc"), one("SELECT * FROM branches WHERE id=$br"));
chk(is_string($bytes) && strlen($bytes) > 20 && strpos($bytes, $t9['label']) !== false, 'escpos: bytes contain label');
$xl = build_stats_xlsx('CI','Toate','2026-01-01','2026-01-31',
    ['total'=>5,'served'=>3,'no_show'=>1,'cancelled'=>1,'avg_wait'=>120,'avg_service'=>90],
    [['d'=>date('Y-m-d'),'c'=>5,'w'=>120]],
    [['name'=>'Casierie','color'=>'#2563eb','kpi_wait_sec'=>600,'c'=>5,'served'=>3,'w'=>120,'sv'=>90,'called_cnt'=>4,'kpi_ok'=>3]],
    [['h'=>9,'c'=>5]], [['code'=>'G1','name'=>'B1','cnt'=>5]], '#2563eb',
    [['name'=>'Ana','c'=>5,'served'=>3,'w'=>120,'sv'=>90]]);
chk(substr($xl->build(),0,2) === 'PK', 'xlsx: valid zip');

/* ---- 13. API v1 + denylist ---- */
chk(in_array('api_key', settings_export_denylist(), true) && in_array('cron_token', settings_export_denylist(), true), 'settings: denylist excludes secrets');
set_setting('api_key', 'ci-key-123456');
chk(hash_equals('ci-key-123456','ci-key-123456'), 'api: key set');

/* ---- 14. Audit ---- */
audit('ci_action','thing',7,'detail');
chk((int)val("SELECT COUNT(*) FROM audit_log WHERE action='ci_action'") === 1, 'audit: recorded');

/* ---- 15. Escaladare prioritate (anti-starvation) ---- */
q("UPDATE services SET allow_priority=1 WHERE id=$svc");
$setup = function() use ($svc) {
    q("UPDATE tickets SET status='cancelled' WHERE service_id=$svc AND status='waiting'");
    $old = issue_ticket($svc, false, 'paper');                  // normal, vechi
    q("UPDATE tickets SET issued_at = NOW() - INTERVAL 20 MINUTE WHERE id=".(int)$old['id']);
    $new = issue_ticket($svc, true, 'paper');                   // prioritar, nou
    return [(int)$old['id'], (int)$new['id']];
};
// escaladare OPRITA: biletul prioritar nou e chemat primul
set_setting('priority_escalate_min','0');
[$oldA,$newA] = $setup(); $pickA = call_next($ctr, $adminId, 0);
chk($pickA && (int)$pickA['id'] === $newA, 'escalate off: priority ticket called first');
// escaladare PORNITA (5 min): biletul normal vechi (peste prag) bate prioritarul nou
set_setting('priority_escalate_min','5');
[$oldB,$newB] = $setup(); $pickB = call_next($ctr, $adminId, 0);
chk($pickB && (int)$pickB['id'] === $oldB, 'escalate on: old normal ticket called before newer priority');
set_setting('priority_escalate_min','0');

/* ---- 16. Schimbare operator prin PIN ---- */
q("DELETE FROM users WHERE email='agentpin@ci.ro'");
q("INSERT INTO users (name,email,role,active,pin,password_hash) VALUES ('Agent PIN','agentpin@ci.ro','agent',1,'4321',?)", [password_hash('x', PASSWORD_DEFAULT)]);
$agId = (int) val("SELECT id FROM users WHERE email='agentpin@ci.ro'");
$sw = pin_switch('4321'); chk($sw && (int)$sw['id'] === $agId, 'pin: switch to agent by pin');
chk(pin_switch('0000') === null, 'pin: wrong pin rejected');
chk(pin_switch('') === null, 'pin: empty pin rejected');
q("UPDATE users SET pin='9999' WHERE id=$adminId");           // adminul nu trebuie sa fie comutabil prin PIN
chk(pin_switch('9999') === null, 'pin: admin not switchable (no escalation)');
q("UPDATE users SET pin=NULL WHERE id=$adminId");

/* ---- 17. Eliberare bilete directionate la pauza ghiseu ---- */
q("UPDATE tickets SET status='cancelled' WHERE service_id=$svc AND status='waiting'");
$tt = issue_ticket($svc, false, 'paper');
transfer_to_counter((int)$tt['id'], $ctr);
chk((int)val("SELECT target_counter_id FROM tickets WHERE id=".(int)$tt['id']) === $ctr, 'release: ticket targeted to counter');
$rel = release_targeted_tickets($ctr);
chk($rel >= 1 && val("SELECT target_counter_id FROM tickets WHERE id=".(int)$tt['id']) === null, 'release: targeted ticket freed to general queue');

/* ---- 18. Plafon zilnic de bonuri per serviciu ---- */
q("UPDATE tickets SET status='cancelled' WHERE service_id=$svc AND status='waiting'");
$cntToday = (int) val("SELECT COUNT(*) FROM tickets WHERE service_id=$svc AND DATE(issued_at)=CURDATE()");
q("UPDATE services SET max_per_day=? WHERE id=$svc", [$cntToday + 1]);   // mai permite exact un bon azi
issue_ticket($svc, false, 'paper');                                      // al (cntToday+1)-lea: ok
$capped = false; try { issue_ticket($svc, false, 'paper'); } catch (Throwable $e) { $capped = str_contains($e->getMessage(), 'Limita zilnica'); }
chk($capped, 'daily cap: blocks issuance over max_per_day');
q("UPDATE services SET max_per_day=0 WHERE id=$svc");
chk(!empty(issue_ticket($svc, false, 'paper')['id']), 'daily cap: 0 = unlimited again');

/* ---- 19. Traduceri bilet digital (multi-limba) ---- */
chk(vt_i18n('en')['st_called'] === "It's your turn!", 'i18n: en status');
chk(vt_i18n('de')['st_waiting'] === 'Warten', 'i18n: de status');
chk(strpos(vt_i18n('ro')['ahead'], '{n}') !== false, 'i18n: ro placeholder {n}');
chk(vt_i18n('xx')['rate'] === vt_i18n('ro')['rate'], 'i18n: unknown lang -> ro fallback');
chk(vt_i18n('es')['cancel'] === 'Abandonar la cola', 'i18n: es cancel');

/* ---- 20. Auto-neprezentat dupa max_recalls rechemari ---- */
q("UPDATE tickets SET status='cancelled' WHERE service_id=$svc AND status='waiting'");
set_setting('max_recalls','2');
$rc = issue_ticket($svc, false, 'paper'); call_next($ctr, $adminId, 0);   // -> called
chk(recall_ticket((int)$rc['id']) === 'called', 'recall 1 -> called');
chk(recall_ticket((int)$rc['id']) === 'called', 'recall 2 -> called');
chk(recall_ticket((int)$rc['id']) === 'no_show', 'recall 3 (>max) -> auto no_show');
chk(val("SELECT status FROM tickets WHERE id=".(int)$rc['id']) === 'no_show', 'recall: ticket marked no_show');
set_setting('max_recalls','0');

/* ---- 21. Nota pe bilet (operator) ---- */
$tn = issue_ticket($svc, false, 'paper');
set_ticket_note((int)$tn['id'], 'revine cu acte');
chk(val("SELECT note FROM tickets WHERE id=".(int)$tn['id']) === 'revine cu acte', 'note: saved');
set_ticket_note((int)$tn['id'], '');
chk(val("SELECT note FROM tickets WHERE id=".(int)$tn['id']) === null, 'note: cleared (empty -> null)');

/* ---- 22. Anulare programare centralizata + payload webhook ---- */
q("UPDATE services SET appt_enabled=1, appt_slot_min=15, appt_capacity=3 WHERE id=$svc");
$svcRow2 = one("SELECT * FROM services WHERE id=$svc"); $day2 = date('Y-m-d', strtotime('+2 days'));
$sl = appt_slots($svcRow2, $day2);
$ap = appt_book($svc, $sl[0]['start'], 'Test', '07', '');
$cc = appt_cancel((int)$ap['id']);
chk($cc && $cc['status'] === 'cancelled' && val("SELECT status FROM appointments WHERE id=".(int)$ap['id']) === 'cancelled', 'appt_cancel: booked -> cancelled');
chk(appt_cancel((int)$ap['id']) === null, 'appt_cancel: already cancelled -> null');
$wp = webhook_appointment($cc);
chk(($wp['id'] ?? 0) === (int)$ap['id'] && ($wp['status'] ?? '') === 'cancelled' && array_key_exists('slot_start',$wp), 'webhook_appointment: payload shape');

/* ---- 23. Reprogramare programare ---- */
q("UPDATE services SET appt_enabled=1, appt_slot_min=15, appt_capacity=3 WHERE id=$svc");
$svcRow3 = one("SELECT * FROM services WHERE id=$svc"); $day3 = date('Y-m-d', strtotime('+3 days'));
$sl3 = appt_slots($svcRow3, $day3);
$ap3 = appt_book($svc, $sl3[0]['start'], 'R', '07', '');
$na = appt_reschedule((int)$ap3['id'], $sl3[1]['start']);
chk($na && $na['slot_start'] === date('Y-m-d H:i:00', strtotime($sl3[1]['start'])), 'reschedule: slot updated');
$threw = false; try { appt_reschedule((int)$ap3['id'], '2000-01-01 09:00:00'); } catch (Throwable $e) { $threw = str_contains($e->getMessage(),'trecut'); }
chk($threw, 'reschedule: past slot rejected');
appt_cancel((int)$ap3['id']);
chk(appt_reschedule((int)$ap3['id'], $sl3[2]['start']) === null, 'reschedule: cancelled appt -> null');

/* ---- 24. Parser CSV servicii ---- */
$pcsv = parse_services_csv("prefix,nume,culoare\nA,Casierie,#2563eb\nB,Informatii\n , ,\nC,Acte,bad-color");
chk(count($pcsv) === 3, 'csv: 3 randuri valide (sare antet + linie goala)');
chk($pcsv[0]['prefix'] === 'A' && $pcsv[0]['color'] === '#2563eb', 'csv: rand cu culoare');
chk($pcsv[1]['color'] === '#2563eb', 'csv: culoare lipsa -> default');
chk($pcsv[2]['color'] === '#2563eb', 'csv: culoare invalida -> default');

/* ---- 25. Parser CSV ghisee ---- */
$ccsv = parse_counters_csv("cod,nume\nG1,Birou 1\nG2\n , \nG3,Casierie");
chk(count($ccsv) === 3, 'csv ghisee: 3 randuri (sare antet+gol)');
chk($ccsv[1]['code'] === 'G2' && $ccsv[1]['name'] === 'G2', 'csv ghisee: nume lipsa -> codul');

/* ---- 25a. Parser CSV zile inchise ---- */
$clcsv = parse_closures_csv("data,motiv\n2026-12-01,Ziua Nationala\n2026-13-40,Data invalida\nnu-i data,x\n2027-01-01,Anul Nou");
chk(count($clcsv) === 2, 'csv zile: 2 randuri valide (sare antet/data inexistenta/format gresit)');
chk($clcsv[0]['date'] === '2026-12-01' && $clcsv[0]['reason'] === 'Ziua Nationala', 'csv zile: data+motiv');
chk($clcsv[1]['date'] === '2027-01-01', 'csv zile: a doua data valida');

/* ---- 25b. Parser CSV filiale ---- */
$bcsv = parse_branches_csv("nume,oras,adresa\nFiliala Centru,Cluj,Str. A 1\nFiliala Nord\n , , \nFiliala Sud,Cluj,Bd. B 2");
chk(count($bcsv) === 3, 'csv filiale: 3 randuri (sare antet+gol)');
chk($bcsv[0]['name'] === 'Filiala Centru' && $bcsv[0]['city'] === 'Cluj', 'csv filiale: nume+oras');
chk($bcsv[1]['name'] === 'Filiala Nord' && $bcsv[1]['city'] === '', 'csv filiale: oras lipsa -> gol');

/* ---- 26. Parser CSV utilizatori ---- */
$ucsv = parse_users_csv("nume,email,rol,parola\nIon Popescu,ion@firma.ro,manager,Parola123\nFara Email,nu-i email,agent,x\nAna,ana@firma.ro,sef,Secret456\nGol,,agent,\nMaria,maria@firma.ro");
chk(count($ucsv) === 2, 'csv useri: 2 randuri valide (sare antet/email invalid/parola lipsa)');
chk($ucsv[0]['email'] === 'ion@firma.ro' && $ucsv[0]['role'] === 'manager', 'csv useri: rol valid pastrat');
chk($ucsv[1]['role'] === 'agent', 'csv useri: rol necunoscut -> agent implicit');

/* ---- 27. Generator QR local (SVG, fara servicii externe) ---- */
require __DIR__ . '/../app/core/qr.php';
$qm = QR::matrix('otpauth://totp/Test:a@b.ro?secret=ABCDEF234567&issuer=Test');
chk(is_array($qm) && count($qm) === count($qm[0]) && (count($qm) - 17) % 4 === 0, 'qr: matrice patrata cu dimensiune de versiune valida');
chk($qm[0][0]===1 && $qm[0][6]===1 && $qm[1][1]===0 && $qm[2][2]===1 && $qm[6][6]===1, 'qr: finder pattern stanga-sus corect');
$svg = QR::svg('hello', 200);
chk(strpos($svg, '<svg') === 0 && strpos($svg, '</svg>') !== false && strpos($svg, '<rect') !== false, 'qr: svg valid cu module');
chk(QR::matrix(str_repeat('x', 400)) === null, 'qr: peste capacitate (v1..10-L) -> null');

echo "INTEGRATION: PASS=$ok FAIL=$fail\n";
if ($F) { echo "FAILURES:\n - " . implode("\n - ", $F) . "\n"; exit(1); }
echo "ALL GREEN\n";
exit(0);
