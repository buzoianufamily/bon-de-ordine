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

/** Limbi disponibile la dispenser: cod => [nume, steag]. */
function disp_lang_meta(): array {
    return [
        'ro'=>['Romana','🇷🇴'], 'en'=>['English','🇬🇧'], 'de'=>['Deutsch','🇩🇪'],
        'fr'=>['Francais','🇫🇷'], 'hu'=>['Magyar','🇭🇺'], 'it'=>['Italiano','🇮🇹'], 'es'=>['Espanol','🇪🇸'],
    ];
}
/** Dictionar de traduceri pentru textele standard ale dispenserului. */
function disp_strings(): array {
    return [
      'title'           => ['en'=>'CHOOSE A SERVICE','de'=>'DIENST WÄHLEN','fr'=>'CHOISISSEZ UN SERVICE','hu'=>'VÁLASSZON SZOLGÁLTATÁST','it'=>'SCEGLI UN SERVIZIO','es'=>'ELIJA UN SERVICIO'],
      'btn_hint'        => ['en'=>'Tap to get a ticket','de'=>'Tippen für ein Ticket','fr'=>'Touchez pour un ticket','hu'=>'Érintse meg a jegyért','it'=>'Tocca per un biglietto','es'=>'Toque para un boleto'],
      'no_services'     => ['en'=>'No services available right now','de'=>'Derzeit keine Dienste verfügbar','fr'=>'Aucun service disponible','hu'=>'Jelenleg nincs elérhető szolgáltatás','it'=>'Nessun servizio disponibile','es'=>'No hay servicios disponibles'],
      'priority_label'  => ['en'=>'★ Priority ticket','de'=>'★ Prioritätsticket','fr'=>'★ Ticket prioritaire','hu'=>'★ Elsőbbségi jegy','it'=>'★ Biglietto prioritario','es'=>'★ Boleto prioritario'],
      'closed_hint'     => ['en'=>'Closed now','de'=>'Jetzt geschlossen','fr'=>'Fermé','hu'=>'Most zárva','it'=>'Ora chiuso','es'=>'Cerrado ahora'],
      'closed_label'    => ['en'=>'🔒 Closed','de'=>'🔒 Geschlossen','fr'=>'🔒 Fermé','hu'=>'🔒 Zárva','it'=>'🔒 Chiuso','es'=>'🔒 Cerrado'],
      'popup_title'     => ['en'=>'Your ticket','de'=>'Ihr Ticket','fr'=>'Votre ticket','hu'=>'Az Ön jegye','it'=>'Il tuo biglietto','es'=>'Su boleto'],
      'ahead_text'      => ['en'=>'{n} people ahead of you','de'=>'{n} Personen vor Ihnen','fr'=>'{n} personnes avant vous','hu'=>'{n} ember van Ön előtt','it'=>'{n} persone prima di te','es'=>'{n} personas antes que usted'],
      'ahead_first'     => ['en'=>'You are next in line','de'=>'Sie sind als Nächstes dran','fr'=>'Vous êtes le prochain','hu'=>'Ön következik','it'=>'Sei il prossimo','es'=>'Usted es el siguiente'],
      'qr_hint'         => ['en'=>'Follow on your phone','de'=>'Auf dem Handy verfolgen','fr'=>'Suivez sur votre téléphone','hu'=>'Kövesse telefonon','it'=>'Segui sul telefono','es'=>'Siga en su teléfono'],
      'done_btn'        => ['en'=>'Done','de'=>'Fertig','fr'=>'Terminé','hu'=>'Kész','it'=>'Fatto','es'=>'Listo'],
      'regular_label'   => ['en'=>'REGULAR TICKET','de'=>'NORMALES TICKET','fr'=>'TICKET NORMAL','hu'=>'NORMÁL JEGY','it'=>'BIGLIETTO NORMALE','es'=>'BOLETO NORMAL'],
      'priority_label_pu'=>['en'=>'PRIORITY TICKET','de'=>'PRIORITÄTSTICKET','fr'=>'TICKET PRIORITAIRE','hu'=>'ELSŐBBSÉGI JEGY','it'=>'BIGLIETTO PRIORITARIO','es'=>'BOLETO PRIORITARIO'],
      'policy_cancel'   => ['en'=>'Cancel','de'=>'Abbrechen','fr'=>'Annuler','hu'=>'Mégse','it'=>'Annulla','es'=>'Cancelar'],
      'policy_ok'       => ['en'=>'Continue','de'=>'Weiter','fr'=>'Continuer','hu'=>'Tovább','it'=>'Continua','es'=>'Continuar'],
    ];
}

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
/** Ruleaza un fisier .sql instructiune cu instructiune (erori individuale ignorate). */
function run_sql_file(string $file): void {
    if (!is_file($file)) return;
    $sql = file_get_contents($file);
    // 1) elimina liniile de comentariu (-- ...) ca sa nu "inghita" instructiunea de sub ele
    $lines = preg_split('/\r?\n/', $sql);
    $kept = [];
    foreach ($lines as $ln) { if (preg_match('/^\s*--/', $ln)) continue; $kept[] = $ln; }
    $sql = implode("\n", $kept);
    // 2) imparte pe ';' (schema/seed nu au ';' in interiorul instructiunilor) si executa
    foreach (array_map('trim', explode(';', $sql)) as $stmt) {
        if ($stmt === '') continue;
        try { db()->exec($stmt); } catch (Throwable $e) {}
    }
}

/**
 * Instalare automata la prima rulare: daca tabelele nu exista, importa schema +
 * date demo si creeaza adminul implicit (Admin / admin@example.ro / 123456).
 * Astfel NU mai e nevoie de install.php.
 */
function auto_install(): void {
    try {
        $hasSettings = (int) val(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='settings'"
        ) > 0;
    } catch (Throwable $e) { return; } // fara conexiune DB nu putem face nimic
    if ($hasSettings) return; // deja instalat -> run_migrations() se ocupa de actualizari

    $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
    run_sql_file($root . '/database/schema.sql');
    run_sql_file($root . '/database/seed.sql');

    // Admin implicit (coloana corecta = password_hash). Schimbabil din pagina Utilizatori.
    try {
        $exists = (int) val("SELECT COUNT(*) FROM users WHERE email = ?", ['admin@example.ro']);
        if (!$exists) {
            q("INSERT INTO users (name, email, password_hash, role, active) VALUES (?,?,?,'admin',1)",
              ['Admin', 'admin@example.ro', password_hash('123456', PASSWORD_DEFAULT)]);
        }
    } catch (Throwable $e) {}

    // schema.sql contine deja toate coloanele recente -> marcheaza versiunea la zi
    try { q("INSERT INTO settings (k, v) VALUES ('schema_version', '8') ON DUPLICATE KEY UPDATE v = '8'"); } catch (Throwable $e) {}
}

function run_migrations(): void {
    auto_install(); // prima rulare: creeaza schema + seed + admin
    try {
        $cur = (int) (val("SELECT v FROM settings WHERE k='schema_version'") ?? 0);
    } catch (Throwable $e) { return; }
    $target = 8;
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

    // v5: notificari in browser pentru operatorul de la terminal (opt-in per utilizator)
    if (!$hasCol('users','notify_browser')) $ddl("ALTER TABLE users ADD COLUMN notify_browser TINYINT(1) NOT NULL DEFAULT 0");

    // v6: feedback general (prin QR pe afisaj) — bilet optional + filiala
    if ($hasCol('feedback','ticket_id')) $ddl("ALTER TABLE feedback MODIFY ticket_id BIGINT NULL");
    if (!$hasCol('feedback','branch_id')) $ddl("ALTER TABLE feedback ADD COLUMN branch_id INT NULL");

    // v7: traduceri nume/descriere serviciu (multi-limba la dispenser)
    if (!$hasCol('services','i18n')) $ddl("ALTER TABLE services ADD COLUMN i18n LONGTEXT NULL");

    // v8: status operator (prezenta) — Disponibil/Ocupat/Pauza/Offline + ultima activitate
    if (!$hasCol('users','work_status')) $ddl("ALTER TABLE users ADD COLUMN work_status VARCHAR(16) NOT NULL DEFAULT 'offline'");
    if (!$hasCol('users','last_seen'))   $ddl("ALTER TABLE users ADD COLUMN last_seen DATETIME NULL");

    // marcheaza versiunea DOAR daca schema chiar e completa acum (altfel nu reincearca degeaba)
    try {
        if ($hasTable('forms') && $hasTable('appointments')
            && $hasCol('services','form_id') && $hasCol('tickets','form_data')
            && $hasCol('services','appt_enabled') && $hasCol('services','appt_slot_min') && $hasCol('services','appt_capacity')
            && $hasCol('users','notify_browser') && $hasCol('feedback','branch_id') && $hasCol('services','i18n')
            && $hasCol('users','work_status') && $hasCol('users','last_seen')) {
            set_setting('schema_version', (string)$target);
        }
    } catch (Throwable $e) {}
}
