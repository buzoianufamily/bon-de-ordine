<?php
/** Functii utilitare folosite peste tot. */

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/**
 * json_encode sigur pentru includere intr-un bloc <script> sau atribut on*=.
 * Escapeaza <, >, &, ', " ca secvente \uXXXX, deci continutul (texte/traduceri
 * editabile din admin) nu poate iesi din contextul scriptului (</script>) sau
 * al atributului. $flags se adauga peste setul de baza (ex: JSON_PRETTY_PRINT).
 */
function jsenc($v, int $flags = 0): string {
    return (string) json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | $flags);
}

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

/** Imparte un CSV in linii, scotand BOM-ul UTF-8 din fata (fisiere exportate/Excel). */
function _csv_lines(string $csv): array {
    return preg_split('/\r?\n/', preg_replace('/^\xEF\xBB\xBF/', '', $csv));
}

/**
 * Neutralizeaza injectia de formule in CSV: o celula care incepe cu = + - @ (sau TAB/CR)
 * e interpretata ca formula la deschiderea in Excel/Sheets. Prefixam cu apostrof (text),
 * conform recomandarii OWASP. Numerele/datele (incep cu cifra) raman neatinse.
 */
function csv_safe_cell($v): string {
    $s = (string)$v;
    if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) $s = "'" . $s;
    return $s;
}

/** fputcsv cu neutralizarea injectiei de formule pe fiecare camp. */
function fputcsv_safe($h, array $row, string $sep = ','): void {
    fputcsv($h, array_map('csv_safe_cell', $row), $sep);
}

/**
 * Parseaza un CSV de servicii (linii: prefix,nume,culoare?). Sare peste antet/linii goale.
 * Returneaza randuri validate: [['prefix'=>..,'name'=>..,'color'=>..], ...].
 */
function parse_services_csv(string $csv): array {
    $out = [];
    foreach (_csv_lines($csv) as $ln) {
        $ln = trim($ln);
        if ($ln === '') continue;
        $p = array_map('trim', explode(',', $ln));
        if (strcasecmp((string)($p[0] ?? ''), 'prefix') === 0) continue;   // antet (inainte de trunchiere)
        $prefix = strtoupper(substr($p[0] ?? '', 0, 3));
        $name   = (string)($p[1] ?? '');
        if ($prefix === '' || $name === '') continue;
        $color = (string)($p[2] ?? '');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#2563eb';
        $out[] = ['prefix' => $prefix, 'name' => mb_substr($name, 0, 120), 'color' => $color];
    }
    return $out;
}

/** Parseaza un CSV de zile inchise (linii: data,motiv?). data in format YYYY-MM-DD. Sare antet/linii invalide. Returneaza [['date','reason'], ...]. */
function parse_closures_csv(string $csv): array {
    $out = [];
    foreach (_csv_lines($csv) as $ln) {
        $ln = trim($ln);
        if ($ln === '') continue;
        $p = array_map('trim', explode(',', $ln));
        $date = (string)($p[0] ?? '');
        if (strcasecmp($date, 'data') === 0 || strcasecmp($date, 'date') === 0) continue; // antet
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;                          // data invalida
        $t = strtotime($date);
        if ($t === false || date('Y-m-d', $t) !== $date) continue;                          // data inexistenta (ex: 2026-13-40)
        $out[] = ['date' => $date, 'reason' => mb_substr((string)($p[1] ?? ''), 0, 120)];
    }
    return $out;
}

/** Parseaza un CSV de filiale (linii: nume,oras?,adresa?). Sare antet/linii goale. Returneaza [['name','city','address'], ...]. */
function parse_branches_csv(string $csv): array {
    $out = [];
    foreach (_csv_lines($csv) as $ln) {
        $ln = trim($ln);
        if ($ln === '') continue;
        $p = array_map('trim', explode(',', $ln));
        if (strcasecmp((string)($p[0] ?? ''), 'nume') === 0 || strcasecmp((string)($p[0] ?? ''), 'name') === 0) continue; // antet
        $name = (string)($p[0] ?? '');
        if ($name === '') continue;
        $out[] = ['name' => mb_substr($name, 0, 120), 'city' => mb_substr((string)($p[1] ?? ''), 0, 80), 'address' => mb_substr((string)($p[2] ?? ''), 0, 255)];
    }
    return $out;
}

/** Parseaza un CSV de ghisee (linii: cod,nume). Sare peste antet/linii goale. Returneaza [['code'=>..,'name'=>..], ...]. */
function parse_counters_csv(string $csv): array {
    $out = [];
    foreach (_csv_lines($csv) as $ln) {
        $ln = trim($ln);
        if ($ln === '') continue;
        $p = array_map('trim', explode(',', $ln));
        if (strcasecmp((string)($p[0] ?? ''), 'cod') === 0 || strcasecmp((string)($p[0] ?? ''), 'code') === 0) continue; // antet
        $code = substr($p[0] ?? '', 0, 10);
        if ($code === '') continue;
        $name = (string)($p[1] ?? '');
        if ($name === '') $name = $code;
        $out[] = ['code' => $code, 'name' => mb_substr($name, 0, 80)];
    }
    return $out;
}

/**
 * Parseaza un CSV de utilizatori (linii: nume,email,rol?,parola). Sare antet/linii goale.
 * rol implicit 'agent'; valideaza emailul si rolul. Returneaza [['name','email','role','password'], ...].
 */
function parse_users_csv(string $csv): array {
    $out = [];
    foreach (_csv_lines($csv) as $ln) {
        $ln = trim($ln);
        if ($ln === '') continue;
        $p = array_map('trim', explode(',', $ln));
        $first = strtolower((string)($p[0] ?? ''));
        if ($first === 'nume' || $first === 'name') continue;          // antet
        $name  = (string)($p[0] ?? '');
        $email = strtolower((string)($p[1] ?? ''));
        $role  = strtolower((string)($p[2] ?? 'agent'));
        $pass  = (string)($p[3] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') continue;
        if (!in_array($role, ['admin','manager','agent'], true)) $role = 'agent';
        $out[] = ['name' => mb_substr($name, 0, 120), 'email' => $email, 'role' => $role, 'password' => $pass];
    }
    return $out;
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
/** Construieste payload-ul compact pentru o programare (webhook). */
function webhook_appointment(?array $a): array {
    if (!$a) return [];
    return [
        'id'            => (int)$a['id'],
        'public_token'  => $a['public_token'] ?? null,
        'status'        => $a['status'] ?? null,
        'branch_id'     => isset($a['branch_id']) ? (int)$a['branch_id'] : null,
        'service_id'    => isset($a['service_id']) ? (int)$a['service_id'] : null,
        'slot_start'    => $a['slot_start'] ?? null,
        'customer_name' => $a['customer_name'] ?? null,
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
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        $ok = $cerr === '' && $status >= 200 && $status < 300;
        log_webhook($event, $url, $cerr === '' ? $status : null, $ok, $cerr !== '' ? $cerr : ($ok ? null : 'HTTP ' . $status));
    } catch (Throwable $e) {}
}

/** Inregistreaza o incercare de livrare webhook in jurnal (pastreaza ultimele 100). */
function log_webhook(string $event, string $url, ?int $status, bool $ok, ?string $error): void {
    try {
        q("INSERT INTO webhook_log (event, url, status_code, ok, error) VALUES (?,?,?,?,?)",
          [mb_substr($event, 0, 40), mb_substr($url, 0, 255), $status, $ok ? 1 : 0,
           $error !== null ? mb_substr($error, 0, 255) : null]);
        // bound: pastreaza doar ultimele 100 de inregistrari
        q("DELETE FROM webhook_log WHERE id <= (SELECT x FROM (SELECT MAX(id)-100 AS x FROM webhook_log) t)");
    } catch (Throwable $e) {}
}

/**
 * Inregistreaza o evaluare (feedback) si declanseaza alerta de nota mica daca e cazul.
 * Sursa unica de adevar pentru pagina publica si API v1. Returneaza id-ul randului.
 * La rating <= pragul `feedback_alert_rating` (0 = dezactivat) trimite webhook `feedback.low`
 * imbogatit cu serviciul/operatorul bonului (cand evaluarea e legata de un bon servit).
 */
function submit_feedback(int $rating, ?string $comment, ?int $ticketId, ?int $branchId): int {
    $comment = ($comment !== null && trim($comment) !== '') ? mb_substr(trim($comment), 0, 500) : null;
    q('INSERT INTO feedback (ticket_id, branch_id, rating, comment) VALUES (?,?,?,?)',
      [$ticketId ?: null, $branchId ?: null, $rating, $comment]);
    $id = (int) insert_id();
    $thr = (int) setting('feedback_alert_rating', '2');
    if ($thr > 0 && $rating <= $thr) {
        $ctx = ['feedback_id' => $id, 'rating' => $rating, 'comment' => $comment, 'branch_id' => $branchId];
        if ($ticketId) {
            $t = one('SELECT t.label, s.name service, u.name agent, b.name branch
                      FROM tickets t LEFT JOIN services s ON s.id=t.service_id
                      LEFT JOIN users u ON u.id=t.agent_id LEFT JOIN branches b ON b.id=t.branch_id
                      WHERE t.id=?', [$ticketId]);
            if ($t) $ctx += ['ticket' => $t['label'], 'service' => $t['service'], 'agent' => $t['agent'], 'branch' => $t['branch']];
        }
        if (function_exists('fire_webhook')) fire_webhook('feedback.low', $ctx);
        feedback_low_email($rating, $comment, $ctx);   // best-effort, doar daca emailul e activ
    }
    return $id;
}

/** Trimite (best-effort) alerta de feedback slab catre manageri — acelasi tipar ca alerta SLA. */
function feedback_low_email(int $rating, ?string $comment, array $ctx): void {
    if (!function_exists('mail_enabled') || !mail_enabled()) return;
    $to = trim((string) setting('sla_alert_to', '')) ?: trim((string) setting('daily_report_to', ''));
    if ($to === '') $to = implode(',', array_column(all("SELECT email FROM users WHERE role='admin' AND active=1"), 'email'));
    $recipients = array_filter(array_map('trim', explode(',', $to)));
    if (!$recipients) return;
    $stars = str_repeat('★', max(0, $rating)) . str_repeat('☆', max(0, 5 - $rating));
    $li = '';
    if (!empty($ctx['service'])) $li .= '<li>Serviciu: <strong>' . e($ctx['service']) . '</strong></li>';
    if (!empty($ctx['agent']))   $li .= '<li>Operator: <strong>' . e($ctx['agent']) . '</strong></li>';
    if (!empty($ctx['ticket']))  $li .= '<li>Bon: <strong>' . e($ctx['ticket']) . '</strong></li>';
    if (!empty($ctx['branch']))  $li .= '<li>Filiala: ' . e($ctx['branch']) . '</li>';
    $body = '<p>Un client a lasat o nota mica: <strong style="font-size:18px;color:#b91c1c">' . $stars . '</strong> (' . (int)$rating . '/5)</p>'
          . ($li ? '<ul>' . $li . '</ul>' : '')
          . ($comment !== null && $comment !== '' ? '<p>Comentariu: <em>' . e($comment) . '</em></p>' : '')
          . '<p style="color:#6b7280;font-size:13px">Verifica si, daca e cazul, urmareste cu operatorul / clientul.</p>';
    foreach ($recipients as $addr)
        send_mail($addr, '⚠ Feedback slab (' . (int)$rating . '/5) · ' . setting('brand_name', 'Bon de ordine'),
            mail_template('Feedback cu nota mica', $body, 'Vezi feedback', url('admin/feedback')));
}

/**
 * Trimite un webhook de test ('ping') catre URL-ul configurat si RAPORTEAZA rezultatul
 * (cod HTTP / eroare), pentru butonul de test din admin. Ignora filtrul de evenimente.
 * Returneaza ['ok'=>bool, 'status'=>int|null, 'signed'=>bool, 'error'=>string|null].
 */
function test_webhook(): array {
    $url = trim((string) setting('webhook_url', ''));
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return ['ok' => false, 'error' => 'Configureaza mai intai un URL de webhook (https).'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'Extensia cURL nu este disponibila pe server.'];
    }
    $payload = json_encode(['event' => 'ping', 'ts' => time(),
        'data' => ['message' => 'Test webhook din Bon de ordine', 'ok' => true]], JSON_UNESCAPED_UNICODE);
    $headers = ['Content-Type: application/json', 'User-Agent: BonDeOrdine-Webhook'];
    $secret = (string) setting('webhook_secret', '');
    $signed = $secret !== '';
    if ($signed) $headers[] = 'X-Signature: sha256=' . hash_hmac('sha256', $payload, $secret);
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($cerr !== '') { log_webhook('ping', $url, null, false, $cerr); return ['ok' => false, 'status' => null, 'signed' => $signed, 'error' => 'Conexiune esuata: ' . $cerr]; }
        $okStatus = $status >= 200 && $status < 300;
        log_webhook('ping', $url, $status, $okStatus, $okStatus ? null : ('HTTP ' . $status));
        return ['ok' => $okStatus, 'status' => $status, 'signed' => $signed,
                'error' => $okStatus ? null : ('Endpointul a raspuns cu codul ' . $status . '.')];
    } catch (Throwable $e) {
        return ['ok' => false, 'status' => null, 'signed' => $signed, 'error' => 'Eroare: ' . $e->getMessage()];
    }
}

/**
 * Traduceri pentru pagina biletului digital (telefon). Returneaza setul pentru limba data,
 * cu fallback pe romana pentru cheile lipsa. Limbi: ro/en/de/fr/hu/it/es.
 */
function vt_i18n(string $lang): array {
    $ro = [
        'st_waiting'=>'La rand', 'st_called'=>'Este randul dvs!', 'st_serving'=>'In curs de servire',
        'st_served'=>'Finalizat', 'st_no_show'=>'Neprezentat', 'st_cancelled'=>'Anulat', 'st_transferred'=>'Transferat',
        'ahead'=>'Sunt {n} persoane inaintea dvs.', 'est_label'=>'Timp estimat:', 'est_under1'=>'sub 1 minut', 'est_min'=>'~ {m} min',
        'goto'=>'Mergeti la: {c}', 'rate'=>'Evalueaza experienta', 'cancel'=>'Renunt la rand',
        'cancel_confirm'=>'Renunti la locul in coada? Biletul va fi anulat.',
        'auto'=>'Pagina se actualizeaza automat. Pastrati-o deschisa.', 'loading'=>'Se incarca…',
        'notify_enable'=>'🔔 Anunta-ma cu o notificare', 'notify_your_turn'=>'Este randul tau! Prezinta-te la ghiseu.',
        'notify_near'=>'Te apropii de ghiseu — fii pregatit.', 'step_away'=>'Poti pleca putin — te anuntam cand te apropii.',
    ];
    $tr = [
        'en'=>['st_waiting'=>'Waiting','st_called'=>"It's your turn!",'st_serving'=>'Now serving','st_served'=>'Done','st_no_show'=>'No-show','st_cancelled'=>'Cancelled','st_transferred'=>'Transferred','ahead'=>'{n} people ahead of you','est_label'=>'Estimated wait:','est_under1'=>'under 1 minute','est_min'=>'~ {m} min','goto'=>'Go to: {c}','rate'=>'Rate your experience','cancel'=>'Leave the queue','cancel_confirm'=>'Give up your place in the queue? The ticket will be cancelled.','auto'=>'This page updates automatically. Keep it open.','loading'=>'Loading…','notify_enable'=>'🔔 Notify me','notify_your_turn'=>"It's your turn! Please go to the counter.",'notify_near'=>"You're getting close — be ready.",'step_away'=>"You can step away — we'll alert you when you're close."],
        'de'=>['st_waiting'=>'Warten','st_called'=>'Sie sind dran!','st_serving'=>'Wird bedient','st_served'=>'Erledigt','st_no_show'=>'Nicht erschienen','st_cancelled'=>'Storniert','st_transferred'=>'Weitergeleitet','ahead'=>'{n} Personen vor Ihnen','est_label'=>'Geschätzte Wartezeit:','est_under1'=>'unter 1 Minute','est_min'=>'~ {m} Min','goto'=>'Gehen Sie zu: {c}','rate'=>'Bewerten Sie Ihre Erfahrung','cancel'=>'Warteschlange verlassen','cancel_confirm'=>'Ihren Platz aufgeben? Das Ticket wird storniert.','auto'=>'Diese Seite wird automatisch aktualisiert. Lassen Sie sie geöffnet.','loading'=>'Laden…','notify_enable'=>'🔔 Benachrichtige mich','notify_your_turn'=>'Sie sind dran! Bitte gehen Sie zum Schalter.','notify_near'=>'Sie sind fast dran — seien Sie bereit.','step_away'=>'Sie können kurz weggehen — wir benachrichtigen Sie, wenn es so weit ist.'],
        'fr'=>['st_waiting'=>'En attente','st_called'=>'C\'est votre tour !','st_serving'=>'En cours','st_served'=>'Terminé','st_no_show'=>'Absent','st_cancelled'=>'Annulé','st_transferred'=>'Transféré','ahead'=>'{n} personnes avant vous','est_label'=>'Temps d\'attente estimé :','est_under1'=>'moins d\'une minute','est_min'=>'~ {m} min','goto'=>'Allez au : {c}','rate'=>'Évaluez votre expérience','cancel'=>'Quitter la file','cancel_confirm'=>'Abandonner votre place dans la file ? Le ticket sera annulé.','auto'=>'Cette page se met à jour automatiquement. Gardez-la ouverte.','loading'=>'Chargement…','notify_enable'=>'🔔 Me prévenir','notify_your_turn'=>'C\'est votre tour ! Présentez-vous au guichet.','notify_near'=>'Vous approchez — soyez prêt.','step_away'=>'Vous pouvez vous éloigner — nous vous préviendrons quand ce sera bientôt.'],
        'hu'=>['st_waiting'=>'Várakozás','st_called'=>'Ön következik!','st_serving'=>'Kiszolgálás alatt','st_served'=>'Kész','st_no_show'=>'Nem jelent meg','st_cancelled'=>'Törölve','st_transferred'=>'Átirányítva','ahead'=>'{n} ember van Ön előtt','est_label'=>'Becsült várakozás:','est_under1'=>'kevesebb mint 1 perc','est_min'=>'~ {m} perc','goto'=>'Menjen ide: {c}','rate'=>'Értékelje élményét','cancel'=>'Kilépés a sorból','cancel_confirm'=>'Lemond a helyéről a sorban? A jegy törlődik.','auto'=>'Az oldal automatikusan frissül. Hagyja nyitva.','loading'=>'Betöltés…','notify_enable'=>'🔔 Értesítsen','notify_your_turn'=>'Ön következik! Fáradjon a pulthoz.','notify_near'=>'Mindjárt sorra kerül — készüljön.','step_away'=>'Elmehet egy kicsit — szólunk, amikor közeleg.'],
        'it'=>['st_waiting'=>'In attesa','st_called'=>'È il suo turno!','st_serving'=>'In servizio','st_served'=>'Completato','st_no_show'=>'Assente','st_cancelled'=>'Annullato','st_transferred'=>'Trasferito','ahead'=>'{n} persone prima di lei','est_label'=>'Attesa stimata:','est_under1'=>'meno di 1 minuto','est_min'=>'~ {m} min','goto'=>'Vada allo: {c}','rate'=>'Valuta la tua esperienza','cancel'=>'Lascia la coda','cancel_confirm'=>'Rinunciare al posto in coda? Il biglietto sarà annullato.','auto'=>'Questa pagina si aggiorna automaticamente. Tienila aperta.','loading'=>'Caricamento…','notify_enable'=>'🔔 Avvisami','notify_your_turn'=>'È il suo turno! Si rechi allo sportello.','notify_near'=>'Sta per toccare a lei — si prepari.','step_away'=>'Può allontanarsi — la avvisiamo quando si avvicina.'],
        'es'=>['st_waiting'=>'En espera','st_called'=>'¡Es su turno!','st_serving'=>'En atención','st_served'=>'Finalizado','st_no_show'=>'No presentado','st_cancelled'=>'Cancelado','st_transferred'=>'Transferido','ahead'=>'{n} personas delante de usted','est_label'=>'Espera estimada:','est_under1'=>'menos de 1 minuto','est_min'=>'~ {m} min','goto'=>'Diríjase a: {c}','rate'=>'Valore su experiencia','cancel'=>'Abandonar la cola','cancel_confirm'=>'¿Renunciar a su lugar en la cola? El ticket se cancelará.','auto'=>'Esta página se actualiza automáticamente. Manténgala abierta.','loading'=>'Cargando…','notify_enable'=>'🔔 Avísame','notify_your_turn'=>'¡Es su turno! Diríjase a la ventanilla.','notify_near'=>'Ya casi es su turno — prepárese.','step_away'=>'Puede alejarse — le avisaremos cuando se acerque.'],
    ];
    $lang = strtolower($lang);
    return ($lang !== 'ro' && isset($tr[$lang])) ? array_merge($ro, $tr[$lang]) : $ro;
}

/** Traduceri pentru fluxul de autentificare/cont (poarta de intrare — multilingva pentru white-label). */
function auth_i18n(string $lang): array {
    $ro = ['title'=>'Autentificare','subtitle'=>'Autentifica-te in cont','email'=>'Email','password'=>'Parola',
           'signin'=>'Intra in cont','forgot'=>'Ai uitat parola?','portal'=>'← Portal','back_login'=>'← Autentificare',
           'fp_title'=>'Resetare parola','fp_intro'=>'Introdu emailul contului. Iti trimitem un link pentru a-ti seta o parola noua.','fp_send'=>'Trimite linkul de resetare','fp_sent'=>'Daca adresa exista in sistem, ti-am trimis un email cu un link de resetare. Verifica si folderul Spam. Linkul expira in 60 de minute.','fp_back'=>'Inapoi la autentificare',
           'rp_title'=>'Parola noua','rp_h1'=>'Seteaza o parola noua','rp_invalid'=>'Linkul de resetare este invalid sau a expirat.','rp_newlink'=>'Cere un link nou','rp_intro'=>'Alege o parola noua pentru contul tau (minim 6 caractere).','rp_new'=>'Parola noua','rp_confirm'=>'Confirma parola','rp_save'=>'Salveaza parola',
           'ac_title'=>'Contul meu','ac_change'=>'Schimba parola','ac_cur'=>'Parola curenta','ac_newmin'=>'Parola noua (min 6)','ac_confirm2'=>'Confirma parola noua','ac_back'=>'← Inapoi','ac_logout'=>'Iesire'];
    $tr = [
        'en'=>['title'=>'Sign in','subtitle'=>'Sign in to your account','email'=>'Email','password'=>'Password','signin'=>'Sign in','forgot'=>'Forgot your password?','portal'=>'← Portal','back_login'=>'← Sign in',
               'fp_title'=>'Reset password','fp_intro'=>'Enter your account email. We will send you a link to set a new password.','fp_send'=>'Send reset link','fp_sent'=>'If the address exists in the system, we have sent an email with a reset link. Check the Spam folder too. The link expires in 60 minutes.','fp_back'=>'Back to sign in',
               'rp_title'=>'New password','rp_h1'=>'Set a new password','rp_invalid'=>'The reset link is invalid or has expired.','rp_newlink'=>'Request a new link','rp_intro'=>'Choose a new password for your account (at least 6 characters).','rp_new'=>'New password','rp_confirm'=>'Confirm password','rp_save'=>'Save password',
               'ac_title'=>'My account','ac_change'=>'Change password','ac_cur'=>'Current password','ac_newmin'=>'New password (min 6)','ac_confirm2'=>'Confirm new password','ac_back'=>'← Back','ac_logout'=>'Sign out'],
        'de'=>['title'=>'Anmeldung','subtitle'=>'Bei Ihrem Konto anmelden','email'=>'E-Mail','password'=>'Passwort','signin'=>'Anmelden','forgot'=>'Passwort vergessen?','portal'=>'← Portal','back_login'=>'← Anmeldung',
               'fp_title'=>'Passwort zurücksetzen','fp_intro'=>'Geben Sie die E-Mail Ihres Kontos ein. Wir senden Ihnen einen Link, um ein neues Passwort festzulegen.','fp_send'=>'Reset-Link senden','fp_sent'=>'Falls die Adresse im System existiert, haben wir eine E-Mail mit einem Reset-Link gesendet. Prüfen Sie auch den Spam-Ordner. Der Link läuft in 60 Minuten ab.','fp_back'=>'Zurück zur Anmeldung',
               'rp_title'=>'Neues Passwort','rp_h1'=>'Neues Passwort festlegen','rp_invalid'=>'Der Reset-Link ist ungültig oder abgelaufen.','rp_newlink'=>'Neuen Link anfordern','rp_intro'=>'Wählen Sie ein neues Passwort (mindestens 6 Zeichen).','rp_new'=>'Neues Passwort','rp_confirm'=>'Passwort bestätigen','rp_save'=>'Passwort speichern',
               'ac_title'=>'Mein Konto','ac_change'=>'Passwort ändern','ac_cur'=>'Aktuelles Passwort','ac_newmin'=>'Neues Passwort (min 6)','ac_confirm2'=>'Neues Passwort bestätigen','ac_back'=>'← Zurück','ac_logout'=>'Abmelden'],
        'fr'=>['title'=>'Connexion','subtitle'=>'Connectez-vous à votre compte','email'=>'E-mail','password'=>'Mot de passe','signin'=>'Se connecter','forgot'=>'Mot de passe oublié ?','portal'=>'← Portail','back_login'=>'← Connexion',
               'fp_title'=>'Réinitialiser le mot de passe','fp_intro'=>'Saisissez l\'e-mail de votre compte. Nous vous enverrons un lien pour définir un nouveau mot de passe.','fp_send'=>'Envoyer le lien','fp_sent'=>'Si l\'adresse existe dans le système, nous avons envoyé un e-mail avec un lien de réinitialisation. Vérifiez aussi le dossier Spam. Le lien expire dans 60 minutes.','fp_back'=>'Retour à la connexion',
               'rp_title'=>'Nouveau mot de passe','rp_h1'=>'Définir un nouveau mot de passe','rp_invalid'=>'Le lien de réinitialisation est invalide ou expiré.','rp_newlink'=>'Demander un nouveau lien','rp_intro'=>'Choisissez un nouveau mot de passe (au moins 6 caractères).','rp_new'=>'Nouveau mot de passe','rp_confirm'=>'Confirmer le mot de passe','rp_save'=>'Enregistrer',
               'ac_title'=>'Mon compte','ac_change'=>'Changer le mot de passe','ac_cur'=>'Mot de passe actuel','ac_newmin'=>'Nouveau mot de passe (min 6)','ac_confirm2'=>'Confirmer le nouveau mot de passe','ac_back'=>'← Retour','ac_logout'=>'Déconnexion'],
        'hu'=>['title'=>'Bejelentkezés','subtitle'=>'Jelentkezzen be a fiókjába','email'=>'E-mail','password'=>'Jelszó','signin'=>'Belépés','forgot'=>'Elfelejtette a jelszót?','portal'=>'← Portál','back_login'=>'← Bejelentkezés',
               'fp_title'=>'Jelszó visszaállítása','fp_intro'=>'Adja meg a fiók e-mail címét. Küldünk egy linket új jelszó beállításához.','fp_send'=>'Visszaállító link küldése','fp_sent'=>'Ha a cím létezik a rendszerben, küldtünk egy e-mailt a visszaállító linkkel. Ellenőrizze a Spam mappát is. A link 60 perc múlva lejár.','fp_back'=>'Vissza a bejelentkezéshez',
               'rp_title'=>'Új jelszó','rp_h1'=>'Új jelszó beállítása','rp_invalid'=>'A visszaállító link érvénytelen vagy lejárt.','rp_newlink'=>'Új link kérése','rp_intro'=>'Válasszon új jelszót (legalább 6 karakter).','rp_new'=>'Új jelszó','rp_confirm'=>'Jelszó megerősítése','rp_save'=>'Jelszó mentése',
               'ac_title'=>'Fiókom','ac_change'=>'Jelszó módosítása','ac_cur'=>'Jelenlegi jelszó','ac_newmin'=>'Új jelszó (min 6)','ac_confirm2'=>'Új jelszó megerősítése','ac_back'=>'← Vissza','ac_logout'=>'Kilépés'],
        'it'=>['title'=>'Accesso','subtitle'=>'Accedi al tuo account','email'=>'Email','password'=>'Password','signin'=>'Accedi','forgot'=>'Password dimenticata?','portal'=>'← Portale','back_login'=>'← Accesso',
               'fp_title'=>'Reimposta password','fp_intro'=>'Inserisci l\'email del tuo account. Ti invieremo un link per impostare una nuova password.','fp_send'=>'Invia il link','fp_sent'=>'Se l\'indirizzo esiste nel sistema, abbiamo inviato un\'email con un link di reimpostazione. Controlla anche la cartella Spam. Il link scade tra 60 minuti.','fp_back'=>'Torna all\'accesso',
               'rp_title'=>'Nuova password','rp_h1'=>'Imposta una nuova password','rp_invalid'=>'Il link di reimpostazione non è valido o è scaduto.','rp_newlink'=>'Richiedi un nuovo link','rp_intro'=>'Scegli una nuova password (almeno 6 caratteri).','rp_new'=>'Nuova password','rp_confirm'=>'Conferma password','rp_save'=>'Salva password',
               'ac_title'=>'Il mio account','ac_change'=>'Cambia password','ac_cur'=>'Password attuale','ac_newmin'=>'Nuova password (min 6)','ac_confirm2'=>'Conferma nuova password','ac_back'=>'← Indietro','ac_logout'=>'Esci'],
        'es'=>['title'=>'Iniciar sesión','subtitle'=>'Inicia sesión en tu cuenta','email'=>'Correo','password'=>'Contraseña','signin'=>'Iniciar sesión','forgot'=>'¿Olvidaste tu contraseña?','portal'=>'← Portal','back_login'=>'← Iniciar sesión',
               'fp_title'=>'Restablecer contraseña','fp_intro'=>'Introduce el correo de tu cuenta. Te enviaremos un enlace para definir una nueva contraseña.','fp_send'=>'Enviar enlace','fp_sent'=>'Si la dirección existe en el sistema, hemos enviado un correo con un enlace de restablecimiento. Revisa también la carpeta de Spam. El enlace caduca en 60 minutos.','fp_back'=>'Volver al inicio de sesión',
               'rp_title'=>'Nueva contraseña','rp_h1'=>'Establece una nueva contraseña','rp_invalid'=>'El enlace de restablecimiento no es válido o ha caducado.','rp_newlink'=>'Solicitar un nuevo enlace','rp_intro'=>'Elige una nueva contraseña (al menos 6 caracteres).','rp_new'=>'Nueva contraseña','rp_confirm'=>'Confirmar contraseña','rp_save'=>'Guardar contraseña',
               'ac_title'=>'Mi cuenta','ac_change'=>'Cambiar contraseña','ac_cur'=>'Contraseña actual','ac_newmin'=>'Nueva contraseña (mín 6)','ac_confirm2'=>'Confirmar nueva contraseña','ac_back'=>'← Volver','ac_logout'=>'Cerrar sesión'],
    ];
    $lang = strtolower($lang);
    return ($lang !== 'ro' && isset($tr[$lang])) ? array_merge($ro, $tr[$lang]) : $ro;
}

/** Traduceri pentru pagina de feedback (multilingv ca biletul digital). */
function fb_i18n(string $lang): array {
    $ro = [
        'thanks_title'=>'Multumim!', 'thanks_sub'=>'Parerea ta ne ajuta sa imbunatatim serviciile.',
        'q_title'=>'Cum a fost experienta?', 'q_sub'=>'Acorda o nota de la 1 la 5.',
        'comment_ph'=>'Comentariu (optional)', 'send'=>'Trimite',
        'rate_group'=>'Nota (1–5 stele)', 'rate_n'=>'{n} din 5',
        'privacy_note'=>'Trimitand, esti de acord cu', 'privacy'=>'Politica de confidentialitate',
    ];
    $tr = [
        'en'=>['thanks_title'=>'Thank you!','thanks_sub'=>'Your feedback helps us improve our service.','q_title'=>'How was your experience?','q_sub'=>'Give a rating from 1 to 5.','comment_ph'=>'Comment (optional)','send'=>'Send','rate_group'=>'Rating (1–5 stars)','rate_n'=>'{n} of 5','privacy_note'=>'By submitting, you agree to the','privacy'=>'Privacy Policy'],
        'de'=>['thanks_title'=>'Danke!','thanks_sub'=>'Ihr Feedback hilft uns, unseren Service zu verbessern.','q_title'=>'Wie war Ihre Erfahrung?','q_sub'=>'Geben Sie eine Bewertung von 1 bis 5.','comment_ph'=>'Kommentar (optional)','send'=>'Senden','rate_group'=>'Bewertung (1–5 Sterne)','rate_n'=>'{n} von 5','privacy_note'=>'Mit dem Absenden stimmen Sie der','privacy'=>'Datenschutzerklärung zu'],
        'fr'=>['thanks_title'=>'Merci !','thanks_sub'=>'Votre avis nous aide à améliorer nos services.','q_title'=>'Comment s\'est passée votre expérience ?','q_sub'=>'Donnez une note de 1 à 5.','comment_ph'=>'Commentaire (facultatif)','send'=>'Envoyer','rate_group'=>'Note (1–5 étoiles)','rate_n'=>'{n} sur 5','privacy_note'=>'En envoyant, vous acceptez la','privacy'=>'Politique de confidentialité'],
        'hu'=>['thanks_title'=>'Köszönjük!','thanks_sub'=>'Visszajelzése segít javítani a szolgáltatásunkon.','q_title'=>'Milyen volt az élmény?','q_sub'=>'Adjon 1-től 5-ig értékelést.','comment_ph'=>'Megjegyzés (opcionális)','send'=>'Küldés','rate_group'=>'Értékelés (1–5 csillag)','rate_n'=>'{n} / 5','privacy_note'=>'A küldéssel elfogadja az','privacy'=>'Adatvédelmi szabályzatot'],
        'it'=>['thanks_title'=>'Grazie!','thanks_sub'=>'Il tuo parere ci aiuta a migliorare il servizio.','q_title'=>'Com\'è stata la tua esperienza?','q_sub'=>'Dai un voto da 1 a 5.','comment_ph'=>'Commento (facoltativo)','send'=>'Invia','rate_group'=>'Voto (1–5 stelle)','rate_n'=>'{n} su 5','privacy_note'=>'Inviando, accetti l\'','privacy'=>'Informativa sulla privacy'],
        'es'=>['thanks_title'=>'¡Gracias!','thanks_sub'=>'Tu opinión nos ayuda a mejorar el servicio.','q_title'=>'¿Cómo fue tu experiencia?','q_sub'=>'Da una valoración de 1 a 5.','comment_ph'=>'Comentario (opcional)','send'=>'Enviar','rate_group'=>'Valoración (1–5 estrellas)','rate_n'=>'{n} de 5','privacy_note'=>'Al enviar, aceptas la','privacy'=>'Política de privacidad'],
    ];
    $lang = strtolower($lang);
    return ($lang !== 'ro' && isset($tr[$lang])) ? array_merge($ro, $tr[$lang]) : $ro;
}

/** Traduceri pentru fluxul public de programare (alegere serviciu + slot + formular). */
function book_i18n(string $lang): array {
    $ro = [
        'title'=>'Programare online', 'choose'=>'Alege serviciul pentru care vrei sa te programezi',
        'none'=>'Momentan nu sunt servicii disponibile pentru programare.',
        'all_services'=>'← Toate serviciile', 'day'=>'Zi',
        'closed_day'=>'Inchis in aceasta zi. Alege alta data.',
        'closed_named'=>'🚫 Inchis in aceasta zi', 'pick_other'=>'Alege alta data.',
        'next_avail'=>'Prima zi disponibila:',
        'chosen_time'=>'Ora aleasa:', 'name'=>'Nume', 'phone'=>'Telefon', 'email'=>'Email (optional)',
        'confirm'=>'Confirma programarea',
        'full'=>'complet', 'wl_join'=>'Anunta-ma daca se elibereaza un loc', 'wl_for'=>'Lista de asteptare pentru:',
        'wl_email'=>'Email (te anuntam aici)', 'wl_submit'=>'Adauga-ma pe lista',
        'consent'=>'Sunt de acord cu prelucrarea datelor pentru gestionarea programarii, conform', 'privacy'=>'Politicii de confidentialitate',
        'days'=>['Dum','Lun','Mar','Mie','Joi','Vin','Sam'],
    ];
    $tr = [
        'en'=>['title'=>'Online booking','choose'=>'Choose the service you want to book','none'=>'No services are currently available for booking.','all_services'=>'← All services','day'=>'Day','closed_day'=>'Closed on this day. Choose another date.','closed_named'=>'🚫 Closed on this day','pick_other'=>'Choose another date.','next_avail'=>'Next available day:','chosen_time'=>'Chosen time:','name'=>'Name','phone'=>'Phone','email'=>'Email (optional)','confirm'=>'Confirm booking','full'=>'full','wl_join'=>'Notify me if a spot frees up','wl_for'=>'Waitlist for:','wl_email'=>'Email (we notify you here)','wl_submit'=>'Add me to the waitlist','consent'=>'I agree to the processing of my data for managing this booking, per the','privacy'=>'Privacy Policy','days'=>['Sun','Mon','Tue','Wed','Thu','Fri','Sat']],
        'de'=>['title'=>'Online-Termin','choose'=>'Wählen Sie die gewünschte Dienstleistung','none'=>'Derzeit sind keine Dienste für Termine verfügbar.','all_services'=>'← Alle Dienste','day'=>'Tag','closed_day'=>'An diesem Tag geschlossen. Wählen Sie ein anderes Datum.','closed_named'=>'🚫 An diesem Tag geschlossen','pick_other'=>'Wählen Sie ein anderes Datum.','next_avail'=>'Nächster freier Tag:','chosen_time'=>'Gewählte Zeit:','name'=>'Name','phone'=>'Telefon','email'=>'E-Mail (optional)','confirm'=>'Termin bestätigen','full'=>'belegt','wl_join'=>'Benachrichtigen, wenn ein Platz frei wird','wl_for'=>'Warteliste für:','wl_email'=>'E-Mail (wir benachrichtigen Sie hier)','wl_submit'=>'Zur Warteliste hinzufügen','consent'=>'Ich stimme der Verarbeitung meiner Daten zur Verwaltung dieses Termins zu, gemäß der','privacy'=>'Datenschutzerklärung','days'=>['So','Mo','Di','Mi','Do','Fr','Sa']],
        'fr'=>['title'=>'Rendez-vous en ligne','choose'=>'Choisissez le service souhaité','none'=>'Aucun service disponible pour le moment.','all_services'=>'← Tous les services','day'=>'Jour','closed_day'=>'Fermé ce jour-là. Choisissez une autre date.','closed_named'=>'🚫 Fermé ce jour-là','pick_other'=>'Choisissez une autre date.','next_avail'=>'Premier jour disponible :','chosen_time'=>'Heure choisie :','name'=>'Nom','phone'=>'Téléphone','email'=>'E-mail (facultatif)','confirm'=>'Confirmer le rendez-vous','full'=>'complet','wl_join'=>'Me prévenir si une place se libère','wl_for'=>'Liste d\'attente pour :','wl_email'=>'E-mail (nous vous prévenons ici)','wl_submit'=>'M\'ajouter à la liste','consent'=>'J\'accepte le traitement de mes données pour la gestion de ce rendez-vous, conformément à la','privacy'=>'Politique de confidentialité','days'=>['Dim','Lun','Mar','Mer','Jeu','Ven','Sam']],
        'hu'=>['title'=>'Online időpont','choose'=>'Válassza ki a kívánt szolgáltatást','none'=>'Jelenleg nincs foglalható szolgáltatás.','all_services'=>'← Összes szolgáltatás','day'=>'Nap','closed_day'=>'Ezen a napon zárva. Válasszon másik dátumot.','closed_named'=>'🚫 Ezen a napon zárva','pick_other'=>'Válasszon másik dátumot.','next_avail'=>'Következő szabad nap:','chosen_time'=>'Választott időpont:','name'=>'Név','phone'=>'Telefon','email'=>'E-mail (opcionális)','confirm'=>'Időpont megerősítése','full'=>'megtelt','wl_join'=>'Értesítsen, ha felszabadul egy hely','wl_for'=>'Várólista erre:','wl_email'=>'E-mail (ide értesítjük)','wl_submit'=>'Felveszem a listára','consent'=>'Hozzájárulok adataim kezeléséhez az időpont kezelése céljából, az','privacy'=>'Adatvédelmi szabályzat szerint','days'=>['Vas','Hét','Ked','Sze','Csü','Pén','Szo']],
        'it'=>['title'=>'Prenotazione online','choose'=>'Scegli il servizio da prenotare','none'=>'Nessun servizio disponibile al momento.','all_services'=>'← Tutti i servizi','day'=>'Giorno','closed_day'=>'Chiuso in questo giorno. Scegli un\'altra data.','closed_named'=>'🚫 Chiuso in questo giorno','pick_other'=>'Scegli un\'altra data.','next_avail'=>'Primo giorno disponibile:','chosen_time'=>'Orario scelto:','name'=>'Nome','phone'=>'Telefono','email'=>'Email (facoltativo)','confirm'=>'Conferma prenotazione','full'=>'completo','wl_join'=>'Avvisami se si libera un posto','wl_for'=>'Lista d\'attesa per:','wl_email'=>'Email (ti avvisiamo qui)','wl_submit'=>'Aggiungimi alla lista','consent'=>'Acconsento al trattamento dei dati per la gestione di questa prenotazione, secondo l\'','privacy'=>'Informativa sulla privacy','days'=>['Dom','Lun','Mar','Mer','Gio','Ven','Sab']],
        'es'=>['title'=>'Reserva en línea','choose'=>'Elige el servicio que quieres reservar','none'=>'No hay servicios disponibles por el momento.','all_services'=>'← Todos los servicios','day'=>'Día','closed_day'=>'Cerrado este día. Elige otra fecha.','closed_named'=>'🚫 Cerrado este día','pick_other'=>'Elige otra fecha.','next_avail'=>'Próximo día disponible:','chosen_time'=>'Hora elegida:','name'=>'Nombre','phone'=>'Teléfono','email'=>'Correo (opcional)','confirm'=>'Confirmar la reserva','full'=>'completo','wl_join'=>'Avísame si se libera una plaza','wl_for'=>'Lista de espera para:','wl_email'=>'Correo (te avisamos aquí)','wl_submit'=>'Añadirme a la lista','consent'=>'Acepto el tratamiento de mis datos para gestionar esta reserva, según la','privacy'=>'Política de privacidad','days'=>['Dom','Lun','Mar','Mié','Jue','Vie','Sáb']],
    ];
    $lang = strtolower($lang);
    return ($lang !== 'ro' && isset($tr[$lang])) ? array_merge($ro, $tr[$lang]) : $ro;
}

/** Traduceri pentru pagina de status a programarii (a/{token}). */
function appt_i18n(string $lang): array {
    $ro = [
        'st_booked'=>'Confirmata','st_checked_in'=>'Check-in efectuat','st_cancelled'=>'Anulata','st_no_show'=>'Neprezentat',
        'view_ticket'=>'Vezi biletul →','checkin'=>'Check-in (am ajuns)',
        'checkin_note'=>'Check-in-ul devine disponibil cu 30 de minute inainte de ora programata.',
        'cancel'=>'Anuleaza programarea','cancel_confirm'=>'Anulezi programarea? Locul se elibereaza pentru alti clienti.',
        'add_cal'=>'📅 Adauga in calendar','keep'=>'Pastreaza acest link — il poti redeschide oricand pentru status.',
        'starts_in'=>'Incepe in',
    ];
    $tr = [
        'en'=>['st_booked'=>'Confirmed','st_checked_in'=>'Checked in','st_cancelled'=>'Cancelled','st_no_show'=>'No-show','view_ticket'=>'View ticket →','checkin'=>'Check in (I have arrived)','checkin_note'=>'Check-in becomes available 30 minutes before your appointment time.','cancel'=>'Cancel appointment','cancel_confirm'=>'Cancel the appointment? The slot is freed for other customers.','add_cal'=>'📅 Add to calendar','keep'=>'Keep this link — you can reopen it anytime for status.','starts_in'=>'Starts in'],
        'de'=>['st_booked'=>'Bestätigt','st_checked_in'=>'Eingecheckt','st_cancelled'=>'Storniert','st_no_show'=>'Nicht erschienen','view_ticket'=>'Ticket ansehen →','checkin'=>'Check-in (Ich bin da)','checkin_note'=>'Check-in ist 30 Minuten vor Ihrem Termin verfügbar.','cancel'=>'Termin stornieren','cancel_confirm'=>'Termin stornieren? Der Platz wird für andere frei.','add_cal'=>'📅 Zum Kalender hinzufügen','keep'=>'Bewahren Sie diesen Link auf — jederzeit für den Status abrufbar.','starts_in'=>'Beginnt in'],
        'fr'=>['st_booked'=>'Confirmé','st_checked_in'=>'Enregistré','st_cancelled'=>'Annulé','st_no_show'=>'Absent','view_ticket'=>'Voir le ticket →','checkin'=>'Enregistrement (je suis arrivé)','checkin_note'=>'L\'enregistrement est disponible 30 minutes avant l\'heure du rendez-vous.','cancel'=>'Annuler le rendez-vous','cancel_confirm'=>'Annuler le rendez-vous ? La place est libérée pour d\'autres.','add_cal'=>'📅 Ajouter au calendrier','keep'=>'Conservez ce lien — réouvrable à tout moment pour le statut.','starts_in'=>'Commence dans'],
        'hu'=>['st_booked'=>'Megerősítve','st_checked_in'=>'Bejelentkezve','st_cancelled'=>'Törölve','st_no_show'=>'Nem jelent meg','view_ticket'=>'Jegy megtekintése →','checkin'=>'Bejelentkezés (megérkeztem)','checkin_note'=>'A bejelentkezés 30 perccel az időpont előtt elérhető.','cancel'=>'Időpont lemondása','cancel_confirm'=>'Lemondja az időpontot? A hely felszabadul másoknak.','add_cal'=>'📅 Naptárhoz adás','keep'=>'Őrizze meg ezt a linket — bármikor megnyitható a státuszért.','starts_in'=>'Kezdés'],
        'it'=>['st_booked'=>'Confermato','st_checked_in'=>'Check-in fatto','st_cancelled'=>'Annullato','st_no_show'=>'Assente','view_ticket'=>'Vedi biglietto →','checkin'=>'Check-in (sono arrivato)','checkin_note'=>'Il check-in è disponibile 30 minuti prima dell\'orario.','cancel'=>'Annulla prenotazione','cancel_confirm'=>'Annullare la prenotazione? Il posto si libera per altri.','add_cal'=>'📅 Aggiungi al calendario','keep'=>'Conserva questo link — riapribile in qualsiasi momento per lo stato.','starts_in'=>'Inizia tra'],
        'es'=>['st_booked'=>'Confirmado','st_checked_in'=>'Registrado','st_cancelled'=>'Cancelado','st_no_show'=>'No presentado','view_ticket'=>'Ver ticket →','checkin'=>'Check-in (he llegado)','checkin_note'=>'El check-in está disponible 30 minutos antes de la hora.','cancel'=>'Cancelar la cita','cancel_confirm'=>'¿Cancelar la cita? La plaza se libera para otros.','add_cal'=>'📅 Añadir al calendario','keep'=>'Guarda este enlace — puedes reabrirlo en cualquier momento para el estado.','starts_in'=>'Empieza en'],
    ];
    $lang = strtolower($lang);
    return ($lang !== 'ro' && isset($tr[$lang])) ? array_merge($ro, $tr[$lang]) : $ro;
}

/**
 * Bara de limbi (steaguri) pentru paginile publice, pe baza setarii dispenser_langs.
 * Returneaza '' daca e configurata o singura limba. $base = URL-ul paginii (fara param lang).
 */
function public_lang_bar(string $current, string $base): string {
    $codes = array_values(array_filter(array_map('trim', explode(',', (string) setting('dispenser_langs', 'ro')))));
    if (!in_array('ro', $codes, true)) array_unshift($codes, 'ro');
    $meta = disp_lang_meta();
    $codes = array_values(array_filter($codes, fn($c) => isset($meta[$c])));
    if (count($codes) < 2) return '';
    $sep = strpos($base, '?') !== false ? '&' : '?';
    $h = '<div class="langbar" role="group" aria-label="Limba" style="display:flex;gap:.4rem;justify-content:center;flex-wrap:wrap;margin:.2rem 0 1rem">';
    foreach ($codes as $c) {
        $on = $c === $current;
        $h .= '<a href="' . e($base . $sep . 'lang=' . $c) . '"' . ($on ? ' aria-current="true"' : '')
            . ' style="text-decoration:none;padding:.2rem .55rem;border-radius:8px;font-size:.85rem;'
            . ($on ? 'background:var(--accent,#2563eb);color:#fff' : 'opacity:.65') . '">'
            . $meta[$c][1] . ' ' . e(strtoupper($c)) . '</a>';
    }
    return $h . '</div>';
}

/**
 * Subsol public cu linkuri legale (confidentialitate + termeni) si brand.
 * $lang threadeaza limba in URL ca restul paginii ramana coerenta.
 */
function public_legal_footer(string $lang = 'ro'): string {
    $brand = e(setting('brand_name', 'Bon de ordine'));
    $lq = ($lang !== '' && $lang !== 'ro') ? '?lang=' . rawurlencode($lang) : '';
    $priv  = e(url('legal/privacy') . $lq);
    $terms = e(url('legal/terms') . $lq);
    $labels = [
        'ro'=>['Confidentialitate','Termeni'], 'en'=>['Privacy','Terms'], 'de'=>['Datenschutz','AGB'],
        'fr'=>['Confidentialité','Conditions'], 'hu'=>['Adatvédelem','Feltételek'],
        'it'=>['Privacy','Termini'], 'es'=>['Privacidad','Términos'],
    ];
    [$lp, $lt] = $labels[$lang] ?? $labels['ro'];
    return '<footer class="pub-foot" style="text-align:center;margin:1.8rem auto .6rem;font-size:.78rem;color:var(--muted,#8a93a3);line-height:1.8">'
         . '<a href="' . $priv . '" style="color:inherit;text-decoration:underline">' . e($lp) . '</a>'
         . ' · <a href="' . $terms . '" style="color:inherit;text-decoration:underline">' . e($lt) . '</a>'
         . '<br>© ' . date('Y') . ' ' . $brand
         . '</footer>';
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
      'opens_at'        => ['en'=>'Opens','de'=>'Öffnet','fr'=>'Ouvre','hu'=>'Nyit','it'=>'Apre','es'=>'Abre'],
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

/**
 * Poate utilizatorul opera ghiseul dat? `allowed_counters` (CSV de id-uri) gol/absent = toate.
 * Aceeasi semantica folosita la deschiderea terminalului (/counter), aplicata si pe API
 * ca sa nu poata fi ocolita prin apeluri directe (call-next/pause/open pe alt ghiseu).
 */
function user_counter_allowed(int $userId, int $counterId): bool {
    static $cache = [];
    if (!array_key_exists($userId, $cache)) {
        $csv = (string) (val('SELECT allowed_counters FROM users WHERE id=?', [$userId]) ?? '');
        $cache[$userId] = array_values(array_filter(array_map('intval', explode(',', $csv))));
    }
    $ids = $cache[$userId];
    return empty($ids) || in_array($counterId, $ids, true);
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
            // must_change_pw=1: in productie, adminul e obligat sa schimbe parola implicita la prima logare
            q("INSERT INTO users (name, email, password_hash, role, active, must_change_pw) VALUES (?,?,?,'admin',1,1)",
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
    $hasIdx = fn(string $t, string $i) => (int) val(
        "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?", [$t,$i]) > 0;

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
        CONSTRAINT fk_appt_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE CASCADE,
        CONSTRAINT fk_appt_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
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

    // v23: plafon zilnic de bonuri per serviciu (0 = nelimitat)
    if (!$hasCol('services','max_per_day')) $ddl("ALTER TABLE services ADD COLUMN max_per_day INT NOT NULL DEFAULT 0");

    // v24: index pentru cautarea bonului curent pe ghiseu (counter_view / etichete curente)
    if (!$hasIdx('tickets','idx_tickets_counter')) $ddl("ALTER TABLE tickets ADD INDEX idx_tickets_counter (counter_id, called_at)");

    // v25: jurnal livrari webhook (depanare integrari)
    if (!$hasTable('webhook_log')) $ddl("CREATE TABLE webhook_log (
        id INT AUTO_INCREMENT PRIMARY KEY, event VARCHAR(40) NOT NULL, url VARCHAR(255) NOT NULL,
        status_code INT NULL, ok TINYINT(1) NOT NULL DEFAULT 0, error VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_whlog_time (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // v26: lista de asteptare programari
    if (!$hasTable('appointment_waitlist')) $ddl("CREATE TABLE appointment_waitlist (
        id INT AUTO_INCREMENT PRIMARY KEY, service_id INT NOT NULL, branch_id INT NULL,
        slot_start DATETIME NOT NULL, customer_name VARCHAR(120) NULL, customer_email VARCHAR(160) NOT NULL,
        customer_phone VARCHAR(32) NULL, notified_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_wl_slot (service_id, slot_start, notified_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // v27: orar de functionare la nivel de filiala (JSON, plic peste orarele serviciilor)
    if (!$hasCol('branches','open_hours')) $ddl("ALTER TABLE branches ADD COLUMN open_hours TEXT NULL");

    // v28: index pe (service_id, status) — accelereaza pozitia in coada, numaratoarea pe serviciu
    // si media de servire (queue_state ruleaza la fiecare tick de afisaj/dispenser)
    if (!$hasIdx('tickets','idx_tickets_svc_status')) $ddl("ALTER TABLE tickets ADD INDEX idx_tickets_svc_status (service_id, status)");

    // v29: cod TOTP de unica folosinta (anti-replay) — ultimul slot 2FA folosit
    if (!$hasCol('users','totp_last_slice')) $ddl("ALTER TABLE users ADD COLUMN totp_last_slice BIGINT NOT NULL DEFAULT 0");

    // v30: schimbarea obligatorie a parolei implicite (onboarding sigur) — flag per utilizator
    if (!$hasCol('users','must_change_pw')) {
        $ddl("ALTER TABLE users ADD COLUMN must_change_pw TINYINT(1) NOT NULL DEFAULT 0");
        // instalari existente: daca adminul implicit inca foloseste parola '123456', forteaza schimbarea
        try {
            $h = (string) val("SELECT password_hash FROM users WHERE email='admin@example.ro'");
            if ($h !== '' && password_verify('123456', $h)) q("UPDATE users SET must_change_pw=1 WHERE email='admin@example.ro'");
        } catch (Throwable $e) {}
    }

    // v31: dovada consimtamantului GDPR la programarea online (data + IP)
    if (!$hasCol('appointments','consent_at')) $ddl("ALTER TABLE appointments ADD COLUMN consent_at DATETIME NULL");
    if (!$hasCol('appointments','consent_ip')) $ddl("ALTER TABLE appointments ADD COLUMN consent_ip VARCHAR(45) NULL");

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
            && $hasCol('services','pause_note') && $hasCol('services','max_per_day')
            && $hasIdx('tickets','idx_tickets_counter') && $hasTable('webhook_log')
            && $hasTable('appointment_waitlist') && $hasCol('branches','open_hours')
            && $hasIdx('tickets','idx_tickets_svc_status') && $hasCol('users','totp_last_slice')
            && $hasCol('users','must_change_pw')
            && $hasCol('appointments','consent_at') && $hasCol('appointments','consent_ip')) {
            set_setting('schema_version', (string)$target);
        }
    } catch (Throwable $e) {}
}

/* ----------------------- Backup baza de date (manual + automat) ----------------------- */
/** Directorul de backup-uri (creat la nevoie, protejat de acces web prin .htaccess). */
function backup_dir(): string {
    $d = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2)) . '/backups';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    $ht = $d . '/.htaccess';                          // blocheaza accesul direct prin web (Apache)
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    return $d;
}
/** Genereaza un dump SQL complet, apeland $emit(string) pentru fiecare bucata (stream sau fisier). */
function db_dump_write(callable $emit): void {
    $pdo = db();
    @set_time_limit(300);
    $emit("-- Backup Bon de ordine · " . date('c') . "\n");
    $emit("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $create = one("SHOW CREATE TABLE `$t`");
        $emit("DROP TABLE IF EXISTS `$t`;\n" . ($create['Create Table'] ?? '') . ";\n\n");
        $stmt = $pdo->query("SELECT * FROM `$t`");
        $batch = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 200) { $emit("INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n"); $batch = []; }
        }
        if ($batch) $emit("INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n");
        $emit("\n");
    }
    $emit("SET FOREIGN_KEY_CHECKS=1;\n-- Gata.\n");
}
/** Scrie un backup intr-un fisier in backup_dir() si returneaza numele fisierului (sau '' la esec). */
function backup_to_file(): string {
    $name = 'backup_' . date('Ymd_His') . '.sql';
    $path = backup_dir() . '/' . $name;
    $fh = @fopen($path, 'w');
    if (!$fh) return '';
    try { db_dump_write(function (string $s) use ($fh) { fwrite($fh, $s); }); }
    finally { fclose($fh); }
    return $name;
}
/** Lista backup-urilor existente (nume + dimensiune + data), cel mai nou primul. */
function backup_list(): array {
    $out = [];
    foreach (glob(backup_dir() . '/backup_*.sql') ?: [] as $f)
        $out[] = ['name' => basename($f), 'size' => (int)@filesize($f), 'mtime' => (int)@filemtime($f)];
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}
/** Pastreaza doar cele mai noi $keep backup-uri; sterge restul. Returneaza cate a sters. */
function backup_prune(int $keep): int {
    $keep = max(1, $keep);
    $del = 0;
    foreach (array_slice(backup_list(), $keep) as $b) if (@unlink(backup_dir() . '/' . $b['name'])) $del++;
    return $del;
}

/* ----------------------- Limite de plan per instanta (abonament) ----------------------- */
/**
 * Limita planului pentru un tip de resursa (branches/counters/users/services); 0 = nelimitat.
 * Limitele se seteaza per client in panoul landlord (config/tenants.json). Instanta principala
 * (fara tenant) e mereu nelimitata.
 */
function tenant_limit(string $what): int {
    $t = $GLOBALS['__tenant'] ?? null;
    if (!is_array($t) || empty($t['limits']) || !is_array($t['limits'])) return 0;
    return max(0, (int)($t['limits'][$what] ?? 0));
}
/**
 * Script de auto-delogare la inactivitate (PC-uri partajate la ghisee/backoffice).
 * Returneaza '' daca e dezactivat (setarea admin_idle_min = 0). Urmareste activitatea
 * reala a utilizatorului (mouse/tastatura), nu traficul de retea.
 */
function idle_logout_script(): string {
    $min = (int) setting('admin_idle_min', '0');
    if ($min <= 0) return '';
    $ms = $min * 60000;
    $url = url('logout');
    return '<script>(function(){var t,L=' . $ms . ',U=' . json_encode($url) . ';'
         . 'function r(){clearTimeout(t);t=setTimeout(function(){location.href=U;},L);}'
         . "['mousemove','keydown','mousedown','touchstart','scroll'].forEach(function(e){document.addEventListener(e,r,{passive:true});});"
         . 'r();})();</script>';
}

/** A atins instanta limita de plan pentru $what? (numara randurile din tabelul corespunzator) */
function tenant_limit_reached(string $what): bool {
    $lim = tenant_limit($what);
    if ($lim <= 0) return false;
    $tables = ['branches' => 'branches', 'counters' => 'counters', 'users' => 'users', 'services' => 'services'];
    if (!isset($tables[$what])) return false;                 // whitelist tabel (fara interpolare nesigura)
    try { return (int) val("SELECT COUNT(*) FROM `{$tables[$what]}`") >= $lim; }
    catch (Throwable $e) { return false; }
}
