<?php
/**
 * Bootstrap aplicatie: config, sesiune, DB (PDO), helperi.
 * Inclus de index.php la fiecare request.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
define('APP_START', microtime(true));

define('APP_SCHEMA_VERSION', 19);   // versiunea curenta a schemei (folosita de migrari si landlord)

$config = require APP_ROOT . '/config/config.php';
$GLOBALS['__config'] = $config;

// ---- Multi-tenant: alege baza de date dupa host (registru in config/tenants.json) ----
function qms_tenants_file(): string { return APP_ROOT . '/config/tenants.json'; }
function qms_tenants_load(): array {
    $f = qms_tenants_file();
    if (!is_file($f)) return [];
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j['tenants'] ?? null) ? $j['tenants'] : [];
}
$GLOBALS['__tenant'] = null;
$__host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$__hostNoWww = preg_replace('/^www\./', '', $__host);
foreach (qms_tenants_load() as $__t) {
    $__th = strtolower(trim((string)($__t['host'] ?? '')));
    if ($__th === '' || ($__th !== $__host && $__th !== $__hostNoWww)) continue;
    if (empty($__t['active'])) {                       // instanta suspendata
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instanta suspendata</title>'
           . '<body style="margin:0;font-family:system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0b0d12;color:#e8eaef">'
           . '<div style="text-align:center;padding:2rem"><div style="font-size:3rem">⏸</div><h1 style="margin:.3em 0">Instanta este suspendata</h1>'
           . '<p style="color:#8a93a3">Contactati administratorul platformei.</p></div>';
        exit;
    }
    if (!empty($__t['db']['name'])) {                   // foloseste baza de date a clientului
        $config['db'] = array_merge($config['db'], $__t['db']);
        $GLOBALS['__config'] = $config;
    }
    $GLOBALS['__tenant'] = $__t;
    break;
}
unset($__host, $__hostNoWww, $__t, $__th);

// Erori in functie de mediu
if (($config['app']['env'] ?? 'production') === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

date_default_timezone_set($config['app']['timezone'] ?? 'Europe/Bucharest');

// Sesiune (cookie restrans la host curent => izolare intre subdomenii/tenanti)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('qms_sess');
    session_start();
}

// Antete de securitate (fallback la nivel PHP daca mod_headers nu e activ)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=15552000');
}

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/totp.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/ticket.php';
require __DIR__ . '/appointments.php';
require __DIR__ . '/printer.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/xlsx.php';

// panoul landlord nu depinde de nicio baza de date (functioneaza si cand una e picata)
$__lpath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$__lsdir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$__lroute = '/' . ltrim(substr($__lpath, strlen($__lsdir)), '/');
define('IS_LANDLORD_REQ', str_starts_with($__lroute, '/landlord'));
// /health = sonda de uptime: nu rula migrari (verifica singura conexiunea, fara a muri)
define('IS_HEALTH_REQ', rtrim($__lroute, '/') === '/health');
unset($__lpath, $__lsdir, $__lroute);

// migrare automata a schemei (idempotent)
if (!IS_LANDLORD_REQ && !IS_HEALTH_REQ) run_migrations();
