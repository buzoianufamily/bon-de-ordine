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
function asset(string $path): string {
    // Versionare automata anti-cache: ?v= data modificarii fisierului.
    $rel = ltrim($path, '/');
    static $vcache = [];
    if (!array_key_exists($rel, $vcache)) {
        $file = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2)) . '/assets/' . $rel;
        $vcache[$rel] = is_file($file) ? (string)@filemtime($file) : '';
    }
    return url('assets/' . $rel) . ($vcache[$rel] !== '' ? '?v=' . $vcache[$rel] : '');
}

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

/* ----- Setari (cu cache pe request, coerent cu scrierile) ----- */
function settings_all(): array {
    if (!isset($GLOBALS['__settings_cache'])) {
        $c = [];
        foreach (all('SELECT k, v FROM settings') as $row) $c[$row['k']] = $row['v'];
        $GLOBALS['__settings_cache'] = $c;
    }
    return $GLOBALS['__settings_cache'];
}
function setting(string $key, $default = null) {
    $s = settings_all();
    return array_key_exists($key, $s) ? $s[$key] : $default;
}
function set_setting(string $key, $value): void {
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
    if (isset($GLOBALS['__settings_cache'])) $GLOBALS['__settings_cache'][$key] = (string)$value;
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

/**
 * Anuntul global activ (banner informativ pe paginile publice), sau '' daca niciunul.
 * Se opreste automat dupa data 'notice_until' (gol = activ cat timp exista text).
 */
function active_notice(): string {
    $txt = trim((string) setting('notice_text', ''));
    if ($txt === '') return '';
    $until = trim((string) setting('notice_until', ''));
    if ($until !== '') {
        $ts = strtotime(strlen($until) <= 10 ? $until . ' 23:59:59' : $until);
        if ($ts !== false && time() > $ts) return '';     // expirat
    }
    return $txt;
}

/**
 * Limitator generic (anti-spam) bazat pe tabela api_rate, pe ferestre de timp.
 * Returneaza true daca actiunea e PERMISA (sub limita) si o contorizeaza.
 * La eroare de DB (ex: pre-migrare) returneaza true, ca sa nu blocheze functionalitatea.
 */
function rate_limit_ok(string $bucket, int $max, int $windowSeconds = 60): bool {
    try {
        $rk = substr(sha1($bucket), 0, 64);
        $win = (int) floor(time() / max(1, $windowSeconds));
        q("INSERT INTO api_rate (rk, minute, cnt) VALUES (?, ?, 1)
           ON DUPLICATE KEY UPDATE cnt = IF(minute = VALUES(minute), cnt + 1, 1), minute = VALUES(minute)",
          [$rk, $win]);
        return (int) val("SELECT cnt FROM api_rate WHERE rk = ?", [$rk]) <= $max;
    } catch (Throwable $e) { return true; }
}

/** Inregistreaza o schimbare de status operator (inchide intervalul anterior, deschide unul nou). */
function log_user_status(int $userId, string $status): void {
    try {
        $cur = val("SELECT status FROM user_status_log WHERE user_id=? AND ended_at IS NULL ORDER BY id DESC LIMIT 1", [$userId]);
        if ($cur === $status) return; // niciun schimb real
        q("UPDATE user_status_log SET ended_at=NOW() WHERE user_id=? AND ended_at IS NULL", [$userId]);
        q("INSERT INTO user_status_log (user_id, status) VALUES (?, ?)", [$userId, $status]);
    } catch (Throwable $e) {}
}

/** Jurnalizeaza o actiune din admin (cine, ce, cand). Best-effort. */
function audit(string $action, string $entity = '', $entity_id = null, string $details = ''): void {
    try {
        $u = function_exists('current_user') ? current_user() : null;
        q("INSERT INTO audit_log (user_id, user_name, action, entity, entity_id, details, ip) VALUES (?,?,?,?,?,?,?)",
          [$u['id'] ?? null, $u['name'] ?? null, $action, $entity ?: null,
           $entity_id !== null ? (string)$entity_id : null, $details ?: null, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {}
}

/* ----- Webhooks (notificare evenimente catre un URL extern) ----- */
/** Construieste payload-ul compact pentru un bilet. */
function webhook_ticket(?array $t): array {
    if (!$t) return [];
    return [
        'id'           => (int)$t['id'],
        'label'        => $t['label'] ?? null,
        'status'       => $t['status'] ?? null,
        'branch_id'    => isset($t['branch_id']) ? (int)$t['branch_id'] : null,
        'service_id'   => isset($t['service_id']) ? (int)$t['service_id'] : null,
        'counter_id'   => isset($t['counter_id']) && $t['counter_id'] !== null ? (int)$t['counter_id'] : null,
        'priority'     => (int)($t['priority'] ?? 0),
        'channel'      => $t['channel'] ?? null,
        'public_token' => $t['public_token'] ?? null,
        'issued_at'    => $t['issued_at'] ?? null,
        'called_at'    => $t['called_at'] ?? null,
        'finished_at'  => $t['finished_at'] ?? null,
    ];
}
/** Trimite un eveniment catre webhook-ul configurat (best-effort, timeout scurt, semnat HMAC). */
function fire_webhook(string $event, array $data): void {
    try {
        $url = trim((string) setting('webhook_url', ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) return;
        $events = array_filter(array_map('trim', explode(',', (string) setting('webhook_events', ''))));
        if ($events && !in_array($event, $events, true)) return;   // lista goala = toate evenimentele
        if (!function_exists('curl_init')) return;
        $payload = json_encode(['event' => $event, 'ts' => time(), 'data' => $data], JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json', 'User-Agent: BonDeOrdine-Webhook'];
        $secret = (string) setting('webhook_secret', '');
        if ($secret !== '') $headers[] = 'X-Signature: sha256=' . hash_hmac('sha256', $payload, $secret);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($ch); curl_close($ch);
    } catch (Throwable $e) {}
}

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
/**
 * Imparte un script SQL in instructiuni, respectand sirurile si comentariile.
 * Ignora ';' din interiorul sirurilor ('...') si comentariilor (-- ..., # ..., comment-line),
 * astfel incat un comentariu care contine ';' nu mai rupe instructiunea (bug istoric).
 */
function sql_split(string $sql): array {
    $stmts = []; $buf = ''; $n = strlen($sql); $inS = false;
    for ($i = 0; $i < $n; $i++) {
        $c = $sql[$i];
        if ($inS) {                                   // in interiorul unui sir '...'
            $buf .= $c;
            if ($c === '\\' && $i + 1 < $n) { $buf .= $sql[$i + 1]; $i++; continue; }
            if ($c === "'") {
                if ($i + 1 < $n && $sql[$i + 1] === "'") { $buf .= $sql[$i + 1]; $i++; continue; } // '' escapat
                $inS = false;
            }
            continue;
        }
        if ($c === "'") { $inS = true; $buf .= $c; continue; }
        // comentariu de linie: "-- " (MySQL cere spatiu/EOL dupa --) sau "#"
        if ($c === '-' && $i + 1 < $n && $sql[$i + 1] === '-' && ($i + 2 >= $n || ctype_space($sql[$i + 2]))) {
            while ($i < $n && $sql[$i] !== "\n") $i++; continue;
        }
        if ($c === '#') { while ($i < $n && $sql[$i] !== "\n") $i++; continue; }
        if ($c === ';') { $s = trim($buf); if ($s !== '') $stmts[] = $s; $buf = ''; continue; }
        $buf .= $c;
    }
    $s = trim($buf); if ($s !== '') $stmts[] = $s;
    return $stmts;
}
function run_sql_file(string $file): void {
    if (!is_file($file)) return;
    foreach (sql_split((string) file_get_contents($file)) as $stmt) {
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

    // Stampam o versiune de baza (1), NU cea curenta: lasam run_migrations() sa ruleze
    // migrarile idempotente si dupa schema.sql, ca sa cream si tabelele/coloanele aparute
    // ulterior prin migrari (ex: password_resets, branch_closures) si sa corectam orice drift.
    try { q("INSERT INTO settings (k, v) VALUES ('schema_version', '1') ON DUPLICATE KEY UPDATE v = VALUES(v)"); }
    catch (Throwable $e) {}
}

function run_migrations(): void {
    auto_install(); // prima rulare: creeaza schema + seed + admin
    try {
        $cur = (int) (val("SELECT v FROM settings WHERE k='schema_version'") ?? 0);
    } catch (Throwable $e) { return; }
    $target = defined('APP_SCHEMA_VERSION') ? APP_SCHEMA_VERSION : 18;
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

    // v9: istoric status operator (cat timp Disponibil/Ocupat/Pauza)
    if (!$hasTable('user_status_log')) $ddl("CREATE TABLE user_status_log (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, status VARCHAR(16) NOT NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ended_at DATETIME NULL,
        INDEX idx_usl_user (user_id, started_at), INDEX idx_usl_open (user_id, ended_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // v10: grupuri de servicii (organizare pe dispenser)
    if (!$hasTable('service_groups')) $ddl("CREATE TABLE service_groups (
        id INT AUTO_INCREMENT PRIMARY KEY, branch_id INT NOT NULL,
        name VARCHAR(120) NOT NULL, color VARCHAR(20) NOT NULL DEFAULT '#64748b', sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_sg_branch (branch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$hasCol('services','group_id')) $ddl("ALTER TABLE services ADD COLUMN group_id INT NULL");

    // v11: transfer catre alt ghiseu (bilet directionat catre un birou anume)
    if (!$hasCol('tickets','target_counter_id')) $ddl("ALTER TABLE tickets ADD COLUMN target_counter_id INT NULL");

    // v12: jurnal de audit (cine ce a modificat in admin)
    if (!$hasTable('audit_log')) $ddl("CREATE TABLE audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, user_name VARCHAR(120) NULL,
        action VARCHAR(40) NOT NULL, entity VARCHAR(40) NULL, entity_id VARCHAR(40) NULL,
        details VARCHAR(255) NULL, ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_audit_time (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // v13: rate-limiting pentru API-ul public (un rand per cheie, contor pe minut)
    if (!$hasTable('api_rate')) $ddl("CREATE TABLE api_rate (
        rk VARCHAR(64) NOT NULL PRIMARY KEY,
        minute BIGINT NOT NULL,
        cnt INT NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // v14: remindere programari (marcaj ca sa nu trimitem de doua ori)
    if (!$hasCol('appointments','reminded_at')) $ddl("ALTER TABLE appointments ADD COLUMN reminded_at DATETIME NULL");

    // v15: autentificare in doi pasi (TOTP) pentru conturi backoffice
    if (!$hasCol('users','totp_secret'))  $ddl("ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL");
    if (!$hasCol('users','totp_enabled')) $ddl("ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0");

    // v16: coduri de recuperare 2FA (hash-uri JSON, consumate la folosire)
    if (!$hasCol('users','totp_backup')) $ddl("ALTER TABLE users ADD COLUMN totp_backup TEXT NULL");

    // v17: atribuirea operatorilor pe ghisee (CSV de id-uri; gol = toate)
    if (!$hasCol('users','allowed_counters')) $ddl("ALTER TABLE users ADD COLUMN allowed_counters TEXT NULL");

    // v18: pauza de ghiseu cu mesaj afisat clientilor
    if (!$hasCol('counters','pause_note')) $ddl("ALTER TABLE counters ADD COLUMN pause_note VARCHAR(120) NULL");

    // v19: resetare parola prin email (token cu hash + expirare, de unica folosinta)
    if (!$hasTable('password_resets')) $ddl("CREATE TABLE password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pr_token (token_hash), INDEX idx_pr_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // v20: zile inchise / sarbatori (per filiala sau globale = branch_id NULL)
    if (!$hasTable('branch_closures')) $ddl("CREATE TABLE branch_closures (
        id INT AUTO_INCREMENT PRIMARY KEY, branch_id INT NULL,
        closed_date DATE NOT NULL, reason VARCHAR(120) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_closure (branch_id, closed_date), INDEX idx_closure_date (closed_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // v21: pauza temporara per serviciu (opreste emiterea fara a schimba programul)
    if (!$hasCol('services','paused')) $ddl("ALTER TABLE services ADD COLUMN paused TINYINT(1) NOT NULL DEFAULT 0");

    // v22: mesaj afisat clientilor cand serviciul e in pauza
    if (!$hasCol('services','pause_note')) $ddl("ALTER TABLE services ADD COLUMN pause_note VARCHAR(120) NULL");

    // marcheaza versiunea DOAR daca schema chiar e completa acum (altfel nu reincearca degeaba)
    try {
        if ($hasTable('forms') && $hasTable('appointments')
            && $hasCol('services','form_id') && $hasCol('tickets','form_data')
            && $hasCol('services','appt_enabled') && $hasCol('services','appt_slot_min') && $hasCol('services','appt_capacity')
            && $hasCol('users','notify_browser') && $hasCol('feedback','branch_id') && $hasCol('services','i18n')
            && $hasCol('users','work_status') && $hasCol('users','last_seen') && $hasTable('user_status_log')
            && $hasTable('service_groups') && $hasCol('services','group_id') && $hasCol('tickets','target_counter_id')
            && $hasTable('audit_log') && $hasTable('api_rate') && $hasCol('appointments','reminded_at')
            && $hasCol('users','totp_secret') && $hasCol('users','totp_enabled') && $hasCol('users','totp_backup')
            && $hasCol('users','allowed_counters') && $hasCol('counters','pause_note')
            && $hasTable('password_resets') && $hasTable('branch_closures') && $hasCol('services','paused')
            && $hasCol('services','pause_note')) {
            set_setting('schema_version', (string)$target);
        }
    } catch (Throwable $e) {}
}
