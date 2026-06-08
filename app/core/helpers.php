<?php
/** Functii utilitare folosite peste tot. */

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function cfg(string $path, $default = null) {
    $parts = explode('.', $path);
    $v = $GLOBALS['__config'];
    foreach ($parts as $p) { if (!isset($v[$p])) return $default; $v = $v[$p]; }
    return $v;
}

/** URL de baza al aplicatiei (auto-detectat). */
function base_url(): string {
    $set = cfg('app.base_url');
    if ($set) return rtrim($set, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // directorul in care sta index.php (suporta instalare in subfolder)
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . $dir;
}
function url(string $path = ''): string { return base_url() . '/' . ltrim($path, '/'); }
function asset(string $path): string { return url('assets/' . ltrim($path, '/')); }

function redirect(string $path): void {
    $loc = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $loc);
    exit;
}

function want_json(): bool {
    $a = $_SERVER['HTTP_ACCEPT'] ?? '';
    $x = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return str_contains($a, 'application/json') || strtolower($x) === 'xmlhttprequest'
        || (($_GET['format'] ?? '') === 'json');
}
function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ----- Setari (cu cache pe request) ----- */
function settings_all(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (all('SELECT k, v FROM settings') as $row) $cache[$row['k']] = $row['v'];
    }
    return $cache;
}
function setting(string $key, $default = null) {
    $s = settings_all();
    return array_key_exists($key, $s) ? $s[$key] : $default;
}
function set_setting(string $key, $value): void {
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
}

/* ----- CSRF ----- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals(csrf_token(), (string)$sent)) {
        json_out(['ok' => false, 'error' => 'Token CSRF invalid. Reincarca pagina.'], 419);
    }
}

/* ----- Mesaje flash ----- */
function flash(string $msg, string $type = 'info'): void {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}
function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ----- Random key pt connection_key dispozitive ----- */
function gen_key(int $len = 6): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // fara caractere ambigue
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $out;
}
function gen_token(int $len = 20): string { return bin2hex(random_bytes($len)); }

function now(): string { return date('Y-m-d H:i:s'); }

/** Render view cu layout. $view relativ la app/views. */
function view(string $view, array $data = []): void {
    extract($data, EXTR_SKIP);
    $__file = APP_ROOT . '/app/views/' . $view . '.php';
    if (!is_file($__file)) { http_response_code(500); die("View lipsa: $view"); }
    require $__file;
}

/**
 * Migrare automata a bazei de date (idempotenta, ghidata de schema_version).
 * Permite actualizarea instalarilor existente doar prin urcarea fisierelor noi.
 */
function auto_install(): void {
    // Verifica daca tabelul settings exista; daca nu, ruleaza schema + seed + creeaza admin implicit.
    $hasSettings = false;
    try {
        $hasSettings = (int) val(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='settings'"
        ) > 0;
    } catch (Throwable $e) { return; }

    if ($hasSettings) return; // instalat deja, run_migrations() va face restul

    $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
    $schema = $root . '/database/schema.sql';
    $seed   = $root . '/database/seed.sql';

    // Executa schema.sql si seed.sql instructiune cu instructiune
    foreach ([$schema, $seed] as $file) {
        if (!is_file($file)) continue;
        $sql = file_get_contents($file);
        // Imparte in instructiuni individuale (ignore linii goale si comentarii)
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '' || str_starts_with($stmt, '--')) continue;
            try { db()->exec($stmt); } catch (Throwable $e) {}
        }
    }

    // Admin implicit: Admin / admin@example.ro / 123456
    $exists = (int) val("SELECT COUNT(*) FROM users WHERE email='admin@example.ro'");
    if (!$exists) {
        $hash = password_hash('123456', PASSWORD_BCRYPT);
        try {
            q("INSERT INTO users (name, email, password, role, active, created_at) VALUES (?,?,?,'admin',1,NOW())",
              ['Admin', 'admin@example.ro', $hash]);
        } catch (Throwable $e) {}
    }

    // Marcheaza schema_version la zi
    try { q("INSERT INTO settings(k,v) VALUES('schema_version','4') ON DUPLICATE KEY UPDATE v='4'"); } catch (Throwable $e) {}
}

function run_migrations(): void {
    auto_install(); // prima rulare: creeaza schema + seed + admin
    try {
        $cur = (int) (val("SELECT v FROM settings WHERE k='schema_version'") ?? 0);
    } catch (Throwable $e) { return; }
    $target = 4;
    if ($cur >= $target) return;

    $hasTable = fn(string $t) => (int) val(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?", [$t]) > 0;
    $hasCol = fn(string $t, string $c) => (int) val(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?", [$t,$c]) > 0;

    // ruleaza un DDL ignorand erorile individuale (ex: obiect deja existent)
    $ddl = function(string $sql){ try { db()->exec($sql); } catch (Throwable $e) {} };

    // v2: formulare + coloane aferente
    if (!$hasTable('forms')) $ddl("CREATE TABLE forms (
        id INT AUTO_INCREMENT PRIMARY KEY, branch_id INT NULL,
        name VARCHAR(120) NOT NULL, fields LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$hasCol('services','form_id'))  $ddl("ALTER TABLE services ADD COLUMN form_id INT NULL");
    if (!$hasCol('tickets','form_data')) $ddl("ALTER TABLE tickets ADD COLUMN form_data LONGTEXT NULL");

    // v3: programari (appointments) + setari pe serviciu
    if (!$hasTable('appointments')) $ddl("CREATE TABLE appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL, service_id INT NOT NULL,
        customer_name VARCHAR(120) NULL, customer_phone VARCHAR(32) NULL, customer_email VARCHAR(160) NULL,
        slot_start DATETIME NOT NULL, slot_end DATETIME NULL,
        status ENUM('booked','checked_in','cancelled','no_show') NOT NULL DEFAULT 'booked',
        ticket_id BIGINT NULL, public_token VARCHAR(40) NULL, note VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_appt_slot (service_id, slot_start), INDEX idx_appt_token (public_token),
        INDEX idx_appt_day (branch_id, slot_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$hasCol('services','appt_enabled'))  $ddl("ALTER TABLE services ADD COLUMN appt_enabled TINYINT(1) NOT NULL DEFAULT 0");
    if (!$hasCol('services','appt_slot_min'))  $ddl("ALTER TABLE services ADD COLUMN appt_slot_min INT NOT NULL DEFAULT 15");
    if (!$hasCol('services','appt_capacity'))  $ddl("ALTER TABLE services ADD COLUMN appt_capacity INT NOT NULL DEFAULT 1");

    // v4: revino la accent albastru (verdele a fost doar temporar)
    try { if (val("SELECT v FROM settings WHERE k='accent_color'") === '#10b981') set_setting('accent_color', '#2563eb'); } catch (Throwable $e) {}

    // marcheaza versiunea DOAR daca schema chiar e completa acum (altfel nu reincearca degeaba)
    try {
        if ($hasTable('forms') && $hasTable('appointments')
            && $hasCol('services','form_id') && $hasCol('tickets','form_data')
            && $hasCol('services','appt_enabled') && $hasCol('services','appt_slot_min') && $hasCol('services','appt_capacity')) {
            set_setting('schema_version', (string)$target);
        }
    } catch (Throwable $e) {}
}
