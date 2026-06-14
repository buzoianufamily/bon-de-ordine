<?php
/**
 * Test izolat (proces propriu): o inchidere pe ZIUA CURENTA blocheaza emiterea biletelor.
 * Separat de integration.php pentru ca branch_closure_reason() are cache pe cerere (per data),
 * iar acest scenariu cere prima verificare a zilei curente sa "vada" inchiderea.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
$host = getenv('BDO_DB_HOST') ?: '127.0.0.1'; $port = getenv('BDO_DB_PORT') ?: '3306';
$GLOBALS['__config_override'] = [
    'db'  => ['host' => $host . ';port=' . $port, 'name' => getenv('BDO_DB_NAME') ?: 'bon',
              'user' => getenv('BDO_DB_USER') ?: 'root', 'pass' => getenv('BDO_DB_PASS') ?: '', 'charset' => 'utf8mb4'],
    'app' => ['name'=>'CI','base_url'=>'','env'=>'dev','timezone'=>'Europe/Bucharest','locale'=>'ro'], 'landlord_pass'=>'x',
];
$_SERVER['HTTP_HOST']='ci.local'; $_SERVER['REQUEST_URI']='/'; $_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
require __DIR__ . '/../app/core/init.php';

$svc = (int)val("SELECT id FROM services WHERE status='active' ORDER BY sort_order LIMIT 1");
$br  = (int)val("SELECT branch_id FROM services WHERE id=$svc");
q("DELETE FROM branch_closures WHERE branch_id=? AND closed_date=CURDATE()", [$br]);
q("INSERT INTO branch_closures (branch_id, closed_date, reason) VALUES (?, CURDATE(), 'CI today')", [$br]);

$ok = 0; $fail = 0;
$detected = branch_closure_reason($br) === 'CI today';
$ok += $detected ? 1 : 0; $fail += $detected ? 0 : 1;
$blocked = false; try { issue_ticket($svc, false, 'paper'); } catch (Throwable $e) { $blocked = str_contains($e->getMessage(), 'Inchis'); }
$ok += $blocked ? 1 : 0; $fail += $blocked ? 0 : 1;

q("DELETE FROM branch_closures WHERE branch_id=? AND closed_date=CURDATE()", [$br]);

echo "CLOSURE-BLOCK: detected=" . ($detected?'Y':'N') . " blocked=" . ($blocked?'Y':'N') . " (PASS=$ok FAIL=$fail)\n";
exit($fail ? 1 : 0);
