<?php
/**
 * Bootstrap aplicatie: config, sesiune, DB (PDO), helperi.
 * Inclus de index.php la fiecare request.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
define('APP_START', microtime(true));

$config = require APP_ROOT . '/config/config.php';
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
require __DIR__ . '/auth.php';
require __DIR__ . '/ticket.php';
require __DIR__ . '/appointments.php';
require __DIR__ . '/printer.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/xlsx.php';

// migrare automata a schemei (idempotent)
run_migrations();
