<?php
/**
 * Bootstrap aplicatie: config, sesiune, DB (PDO), helperi.
 * Inclus de index.php la fiecare request.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
define('APP_START', microtime(true));

// prinde erorile fatale/neprinse din toata aplicatia si afiseaza o pagina prietenoasa (fara DB)
require APP_ROOT . '/app/core/errors.php';
bdo_install_error_handlers();

define('APP_SCHEMA_VERSION', 31);   // versiunea curenta a schemei (folosita de migrari si landlord)

// Config: din config/config.php; testele de integrare pot injecta prin $GLOBALS['__config_override'].
$config = $GLOBALS['__config_override'] ?? (require APP_ROOT . '/config/config.php');
$GLOBALS['__config'] = $config;

// ---- Multi-tenant: alege baza de date dupa host (registru in config/tenants.json) ----
function qms_tenants_file(): string { return APP_ROOT . '/config/tenants.json'; }
function qms_tenants_load(): array {
    $f = qms_tenants_file();
    if (!is_file($f)) return [];
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j['tenants'] ?? null) ? $j['tenants'] : [];
}
/**
 * Starea de acces a unei instante (abonament): 'ok' | 'suspended' | 'expired'.
 * Suspendare manuala (active=0) sau abonament expirat (azi > paid_until + grace_days).
 */
function bdo_tenant_state(array $t, int $now): string {
    if (empty($t['active'])) return 'suspended';
    $pu = trim((string)($t['paid_until'] ?? ''));
    if ($pu !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pu)) {
        $grace = max(0, (int)($t['grace_days'] ?? 0));
        $deadline = strtotime($pu . ' +' . $grace . ' days 23:59:59');
        if ($deadline !== false && $now > $deadline) return 'expired';
    }
    return 'ok';
}
$GLOBALS['__tenant'] = null;
$__host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$__hostNoWww = preg_replace('/^www\./', '', $__host);
$__tenants = qms_tenants_load();
$__matched = false;
foreach ($__tenants as $__t) {
    $__th = strtolower(trim((string)($__t['host'] ?? '')));
    if ($__th === '' || ($__th !== $__host && $__th !== $__hostNoWww)) continue;
    $__matched = true;
    $__state = bdo_tenant_state($__t, time());
    if ($__state === 'suspended') {
        $c = trim((string)($config['support_email'] ?? ''));
        fail_page(503, 'Cont suspendat',
            'Acest cont a fost suspendat temporar. Contactați administratorul platformei' . ($c !== '' ? ' (' . $c . ')' : '') . '.',
            null, false);
    }
    if ($__state === 'expired') {
        $c = trim((string)($config['support_email'] ?? ''));
        $renew = trim((string)($config['renew_url'] ?? ''));
        fail_page(402, 'Abonament expirat',
            'Abonamentul a expirat. Reînnoiește-l pentru a reactiva accesul' . ($c !== '' ? '. Contact: ' . $c : '') . ($renew !== '' ? ' · ' . $renew : '') . '.',
            null, false);
    }
    if (!empty($__t['db']['name'])) {                   // foloseste baza de date a clientului
        $config['db'] = array_merge($config['db'], $__t['db']);
        $GLOBALS['__config'] = $config;
    }
    $GLOBALS['__tenant'] = $__t;
    break;
}
// host neinregistrat: daca exista un registru de tenanti si un primary_host configurat,
// nu servi pe baza de date principala un domeniu necunoscut (anti-configurare gresita/spoof)
$__primary = strtolower(trim((string)($config['primary_host'] ?? '')));
if (!$__matched && $__primary !== '' && $__host !== $__primary && $__hostNoWww !== $__primary && $__tenants) {
    fail_page(404, 'Domeniu neconfigurat', 'Acest domeniu nu este configurat pe platformă.', null, false);
}
unset($__host, $__hostNoWww, $__t, $__th, $__tenants, $__matched, $__state, $__primary);

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
