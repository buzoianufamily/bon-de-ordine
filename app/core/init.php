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

define('APP_SCHEMA_VERSION', 35);   // versiunea curenta a schemei (folosita de migrari)

// Config: din config/config.php; testele de integrare pot injecta prin $GLOBALS['__config_override'].
$config = $GLOBALS['__config_override'] ?? (require APP_ROOT . '/config/config.php');
$GLOBALS['__config'] = $config;

// Erori in functie de mediu
if (($config['app']['env'] ?? 'production') === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

date_default_timezone_set($config['app']['timezone'] ?? 'Europe/Bucharest');

// Sesiune (cookie restrans la host curent)
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
    // unealta interna (cozi) + pagini cu jetoane personale (bilet/programare) => nu indexa nicaieri
    header('X-Robots-Tag: noindex, nofollow');
    // Content-Security-Policy: toate resursele sunt locale (fonturi incluse), deci putem restrange.
    // 'unsafe-inline' e necesar (scripturi/stiluri inline in interfata); blocheaza totusi resursele
    // externe injectate (clasa comuna de XSS) si limiteaza form-action / base-uri. Dezactivabil din config.
    if (($config['app']['csp'] ?? true)) {
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; "
             . "frame-ancestors 'self'; form-action 'self'; img-src 'self' data: https:; "
             . "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
             . "font-src 'self'; connect-src 'self' https://api.open-meteo.com; media-src 'self'; frame-src 'self' https: http:");
    }
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

// /health = sonda de uptime: verifica singura conexiunea (fara a muri), deci nu rula migrari acolo
$__lpath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$__lsdir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$__lroute = '/' . ltrim(substr($__lpath, strlen($__lsdir)), '/');
define('IS_HEALTH_REQ', rtrim($__lroute, '/') === '/health');
unset($__lpath, $__lsdir, $__lroute);

// migrare automata a schemei (idempotent)
if (!IS_HEALTH_REQ) run_migrations();
