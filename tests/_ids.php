<?php
/**
 * Helper pentru testul HTTP: ruleaza init (instaleaza schema la zero), apoi tipareste
 * pe o linie:  <dispenser_key> <service_id> <counter_id> <branch_id>
 * Config DB din env (acelasi ca tests/integration.php).
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
$host = getenv('BDO_DB_HOST') ?: '127.0.0.1'; $port = getenv('BDO_DB_PORT') ?: '3306';
$GLOBALS['__config_override'] = [
    'db'  => ['host' => $host . ';port=' . $port, 'name' => getenv('BDO_DB_NAME') ?: 'bon',
              'user' => getenv('BDO_DB_USER') ?: 'root', 'pass' => getenv('BDO_DB_PASS') ?: '', 'charset' => 'utf8mb4'],
    'app' => ['name'=>'HTTP','base_url'=>'','env'=>'dev','timezone'=>'Europe/Bucharest','locale'=>'ro'], 'landlord_pass'=>'httppass',
];
$_SERVER['HTTP_HOST']='ci.local'; $_SERVER['REQUEST_URI']='/'; $_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
require __DIR__ . '/../app/core/init.php';   // auto_install + migrations
$dkey = (string) val("SELECT connection_key FROM devices WHERE type='dispenser' LIMIT 1");
$svc  = (int) val("SELECT id FROM services WHERE status='active' ORDER BY sort_order LIMIT 1");
$ctr  = (int) val("SELECT id FROM counters LIMIT 1");
$br   = (int) val("SELECT branch_id FROM services WHERE id=$svc");
echo "$dkey $svc $ctr $br\n";
