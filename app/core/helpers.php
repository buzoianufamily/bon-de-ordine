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

/**
 * Parseaza un CSV de servicii (linii: prefix,nume,culoare?). Sare peste antet/linii goale.
 * Returneaza randuri validate: [['prefix'=>..,'name'=>..,'color'=>..], ...].
 */
function parse_services_csv(string $csv): array {
    $out = [];
    foreach (preg_split('/\r?\n/', $csv) as $ln) {
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
    foreach (preg_split('/\r?\n/', $csv) as $ln) {
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
    foreach (preg_split('/\r?\n/', $csv) as $ln) {
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
    foreach (preg_split('/\r?\n/', $csv) as $ln) {
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
    foreach (preg_split('/\r?\n/', $csv) as $ln) {
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
    ];
    $tr = [
        'en'=>['st_waiting'=>'Waiting','st_called'=>"It's your turn!",'st_serving'=>'Now serving','st_served'=>'Done','st_no_show'=>'No-show','st_cancelled'=>'Cancelled','st_transferred'=>'Transferred','ahead'=>'{n} people ahead of you','est_label'=>'Estimated wait:','est_under1'=>'under 1 minute','est_min'=>'~ {m} min','goto'=>'Go to: {c}','rate'=>'Rate your experience','cancel'=>'Leave the queue','cancel_confirm'=>'Give up your place in the queue? The ticket will be cancelled.','auto'=>'This page updates automatically. Keep it open.','loading'=>'Loading…'],
        'de'=>['st_waiting'=>'Warten','st_called'=>'Sie sind dran!','st_serving'=>'Wird bedient','st_served'=>'Erledigt','st_no_show'=>'Nicht erschienen','st_cancelled'=>'Storniert','st_transferred'=>'Weitergeleitet','ahead'=>'{n} Personen vor Ihnen','est_label'=>'Geschätzte Wartezeit:','est_under1'=>'unter 1 Minute','est_min'=>'~ {m} Min','goto'=>'Gehen Sie zu: {c}','rate'=>'Bewerten Sie Ihre Erfahrung','cancel'=>'Warteschlange verlassen','cancel_confirm'=>'Ihren Platz aufgeben? Das Ticket wird storniert.','auto'=>'Diese Seite wird automatisch aktualisiert. Lassen Sie sie geöffnet.','loading'=>'Laden…'],
        'fr'=>['st_waiting'=>'En attente','st_called'=>'C\'est votre tour !','st_serving'=>'En cours','st_served'=>'Terminé','st_no_show'=>'Absent','st_cancelled'=>'Annulé','st_transferred'=>'Transféré','ahead'=>'{n} personnes avant vous','est_label'=>'Temps d\'attente estimé :','est_under1'=>'moins d\'une minute','est_min'=>'~ {m} min','goto'=>'Allez au : {c}','rate'=>'Évaluez votre expérience','cancel'=>'Quitter la file','cancel_confirm'=>'Abandonner votre place dans la file ? Le ticket sera annulé.','auto'=>'Cette page se met à jour automatiquement. Gardez-la ouverte.','loading'=>'Chargement…'],
        'hu'=>['st_waiting'=>'Várakozás','st_called'=>'Ön következik!','st_serving'=>'Kiszolgálás alatt','st_served'=>'Kész','st_no_show'=>'Nem jelent meg','st_cancelled'=>'Törölve','st_transferred'=>'Átirányítva','ahead'=>'{n} ember van Ön előtt','est_label'=>'Becsült várakozás:','est_under1'=>'kevesebb mint 1 perc','est_min'=>'~ {m} perc','goto'=>'Menjen ide: {c}','rate'=>'Értékelje élményét','cancel'=>'Kilépés a sorból','cancel_confirm'=>'Lemond a helyéről a sorban? A jegy törlődik.','auto'=>'Az oldal automatikusan frissül. Hagyja nyitva.','loading'=>'Betöltés…'],
        'it'=>['st_waiting'=>'In attesa','st_called'=>'È il suo turno!','st_serving'=>'In servizio','st_served'=>'Completato','st_no_show'=>'Assente','st_cancelled'=>'Annullato','st_transferred'=>'Trasferito','ahead'=>'{n} persone prima di lei','est_label'=>'Attesa stimata:','est_under1'=>'meno di 1 minuto','est_min'=>'~ {m} min','goto'=>'Vada allo: {c}','rate'=>'Valuta la tua esperienza','cancel'=>'Lascia la coda','cancel_confirm'=>'Rinunciare al posto in coda? Il biglietto sarà annullato.','auto'=>'Questa pagina si aggiorna automaticamente. Tienila aperta.','loading'=>'Caricamento…'],
        'es'=>['st_waiting'=>'En espera','st_called'=>'¡Es su turno!','st_serving'=>'En atención','st_served'=>'Finalizado','st_no_show'=>'No presentado','st_cancelled'=>'Cancelado','st_transferred'=>'Transferido','ahead'=>'{n} personas delante de usted','est_label'=>'Espera estimada:','est_under1'=>'menos de 1 minuto','est_min'=>'~ {m} min','goto'=>'Diríjase a: {c}','rate'=>'Valore su experiencia','cancel'=>'Abandonar la cola','cancel_confirm'=>'¿Renunciar a su lugar en la cola? El ticket se cancelará.','auto'=>'Esta página se actualiza automáticamente. Manténgala abierta.','loading'=>'Cargando…'],
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
    ];
    $tr = [
        'en'=>['thanks_title'=>'Thank you!','thanks_sub'=>'Your feedback helps us improve our service.','q_title'=>'How was your experience?','q_sub'=>'Give a rating from 1 to 5.','comment_ph'=>'Comment (optional)','send'=>'Send'],
        'de'=>['thanks_title'=>'Danke!','thanks_sub'=>'Ihr Feedback hilft uns, unseren Service zu verbessern.','q_title'=>'Wie war Ihre Erfahrung?','q_sub'=>'Geben Sie eine Bewertung von 1 bis 5.','comment_ph'=>'Kommentar (optional)','send'=>'Senden'],
        'fr'=>['thanks_title'=>'Merci !','thanks_sub'=>'Votre avis nous aide à améliorer nos services.','q_title'=>'Comment s\'est passée votre expérience ?','q_sub'=>'Donnez une note de 1 à 5.','comment_ph'=>'Commentaire (facultatif)','send'=>'Envoyer'],
        'hu'=>['thanks_title'=>'Köszönjük!','thanks_sub'=>'Visszajelzése segít javítani a szolgáltatásunkon.','q_title'=>'Milyen volt az élmény?','q_sub'=>'Adjon 1-től 5-ig értékelést.','comment_ph'=>'Megjegyzés (opcionális)','send'=>'Küldés'],
        'it'=>['thanks_title'=>'Grazie!','thanks_sub'=>'Il tuo parere ci aiuta a migliorare il servizio.','q_title'=>'Com\'è stata la tua esperienza?','q_sub'=>'Dai un voto da 1 a 5.','comment_ph'=>'Commento (facoltativo)','send'=>'Invia'],
        'es'=>['thanks_title'=>'¡Gracias!','thanks_sub'=>'Tu opinión nos ayuda a mejorar el servicio.','q_title'=>'¿Cómo fue tu experiencia?','q_sub'=>'Da una valoración de 1 a 5.','comment_ph'=>'Comentario (opcional)','send'=>'Enviar'],
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
        'chosen_time'=>'Ora aleasa:', 'name'=>'Nume', 'phone'=>'Telefon', 'email'=>'Email (optional)',
        'confirm'=>'Confirma programarea',
        'full'=>'complet', 'wl_join'=>'Anunta-ma daca se elibereaza un loc', 'wl_for'=>'Lista de asteptare pentru:',
        'wl_email'=>'Email (te anuntam aici)', 'wl_submit'=>'Adauga-ma pe lista',
        'days'=>['Dum','Lun','Mar','Mie','Joi','Vin','Sam'],
    ];
    $tr = [
        'en'=>['title'=>'Online booking','choose'=>'Choose the service you want to book','none'=>'No services are currently available for booking.','all_services'=>'← All services','day'=>'Day','closed_day'=>'Closed on this day. Choose another date.','closed_named'=>'🚫 Closed on this day','pick_other'=>'Choose another date.','chosen_time'=>'Chosen time:','name'=>'Name','phone'=>'Phone','email'=>'Email (optional)','confirm'=>'Confirm booking','full'=>'full','wl_join'=>'Notify me if a spot frees up','wl_for'=>'Waitlist for:','wl_email'=>'Email (we notify you here)','wl_submit'=>'Add me to the waitlist','days'=>['Sun','Mon','Tue','Wed','Thu','Fri','Sat']],
        'de'=>['title'=>'Online-Termin','choose'=>'Wählen Sie die gewünschte Dienstleistung','none'=>'Derzeit sind keine Dienste für Termine verfügbar.','all_services'=>'← Alle Dienste','day'=>'Tag','closed_day'=>'An diesem Tag geschlossen. Wählen Sie ein anderes Datum.','closed_named'=>'🚫 An diesem Tag geschlossen','pick_other'=>'Wählen Sie ein anderes Datum.','chosen_time'=>'Gewählte Zeit:','name'=>'Name','phone'=>'Telefon','email'=>'E-Mail (optional)','confirm'=>'Termin bestätigen','full'=>'belegt','wl_join'=>'Benachrichtigen, wenn ein Platz frei wird','wl_for'=>'Warteliste für:','wl_email'=>'E-Mail (wir benachrichtigen Sie hier)','wl_submit'=>'Zur Warteliste hinzufügen','days'=>['So','Mo','Di','Mi','Do','Fr','Sa']],
        'fr'=>['title'=>'Rendez-vous en ligne','choose'=>'Choisissez le service souhaité','none'=>'Aucun service disponible pour le moment.','all_services'=>'← Tous les services','day'=>'Jour','closed_day'=>'Fermé ce jour-là. Choisissez une autre date.','closed_named'=>'🚫 Fermé ce jour-là','pick_other'=>'Choisissez une autre date.','chosen_time'=>'Heure choisie :','name'=>'Nom','phone'=>'Téléphone','email'=>'E-mail (facultatif)','confirm'=>'Confirmer le rendez-vous','full'=>'complet','wl_join'=>'Me prévenir si une place se libère','wl_for'=>'Liste d\'attente pour :','wl_email'=>'E-mail (nous vous prévenons ici)','wl_submit'=>'M\'ajouter à la liste','days'=>['Dim','Lun','Mar','Mer','Jeu','Ven','Sam']],
        'hu'=>['title'=>'Online időpont','choose'=>'Válassza ki a kívánt szolgáltatást','none'=>'Jelenleg nincs foglalható szolgáltatás.','all_services'=>'← Összes szolgáltatás','day'=>'Nap','closed_day'=>'Ezen a napon zárva. Válasszon másik dátumot.','closed_named'=>'🚫 Ezen a napon zárva','pick_other'=>'Válasszon másik dátumot.','chosen_time'=>'Választott időpont:','name'=>'Név','phone'=>'Telefon','email'=>'E-mail (opcionális)','confirm'=>'Időpont megerősítése','full'=>'megtelt','wl_join'=>'Értesítsen, ha felszabadul egy hely','wl_for'=>'Várólista erre:','wl_email'=>'E-mail (ide értesítjük)','wl_submit'=>'Felveszem a listára','days'=>['Vas','Hét','Ked','Sze','Csü','Pén','Szo']],
        'it'=>['title'=>'Prenotazione online','choose'=>'Scegli il servizio da prenotare','none'=>'Nessun servizio disponibile al momento.','all_services'=>'← Tutti i servizi','day'=>'Giorno','closed_day'=>'Chiuso in questo giorno. Scegli un\'altra data.','closed_named'=>'🚫 Chiuso in questo giorno','pick_other'=>'Scegli un\'altra data.','chosen_time'=>'Orario scelto:','name'=>'Nome','phone'=>'Telefono','email'=>'Email (facoltativo)','confirm'=>'Conferma prenotazione','full'=>'completo','wl_join'=>'Avvisami se si libera un posto','wl_for'=>'Lista d\'attesa per:','wl_email'=>'Email (ti avvisiamo qui)','wl_submit'=>'Aggiungimi alla lista','days'=>['Dom','Lun','Mar','Mer','Gio','Ven','Sab']],
        'es'=>['title'=>'Reserva en línea','choose'=>'Elige el servicio que quieres reservar','none'=>'No hay servicios disponibles por el momento.','all_services'=>'← Todos los servicios','day'=>'Día','closed_day'=>'Cerrado este día. Elige otra fecha.','closed_named'=>'🚫 Cerrado este día','pick_other'=>'Elige otra fecha.','chosen_time'=>'Hora elegida:','name'=>'Nombre','phone'=>'Teléfono','email'=>'Correo (opcional)','confirm'=>'Confirmar la reserva','full'=>'completo','wl_join'=>'Avísame si se libera una plaza','wl_for'=>'Lista de espera para:','wl_email'=>'Correo (te avisamos aquí)','wl_submit'=>'Añadirme a la lista','days'=>['Dom','Lun','Mar','Mié','Jue','Vie','Sáb']],
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
    ];
    $tr = [
        'en'=>['st_booked'=>'Confirmed','st_checked_in'=>'Checked in','st_cancelled'=>'Cancelled','st_no_show'=>'No-show','view_ticket'=>'View ticket →','checkin'=>'Check in (I have arrived)','checkin_note'=>'Check-in becomes available 30 minutes before your appointment time.','cancel'=>'Cancel appointment','cancel_confirm'=>'Cancel the appointment? The slot is freed for other customers.','add_cal'=>'📅 Add to calendar','keep'=>'Keep this link — you can reopen it anytime for status.'],
        'de'=>['st_booked'=>'Bestätigt','st_checked_in'=>'Eingecheckt','st_cancelled'=>'Storniert','st_no_show'=>'Nicht erschienen','view_ticket'=>'Ticket ansehen →','checkin'=>'Check-in (Ich bin da)','checkin_note'=>'Check-in ist 30 Minuten vor Ihrem Termin verfügbar.','cancel'=>'Termin stornieren','cancel_confirm'=>'Termin stornieren? Der Platz wird für andere frei.','add_cal'=>'📅 Zum Kalender hinzufügen','keep'=>'Bewahren Sie diesen Link auf — jederzeit für den Status abrufbar.'],
        'fr'=>['st_booked'=>'Confirmé','st_checked_in'=>'Enregistré','st_cancelled'=>'Annulé','st_no_show'=>'Absent','view_ticket'=>'Voir le ticket →','checkin'=>'Enregistrement (je suis arrivé)','checkin_note'=>'L\'enregistrement est disponible 30 minutes avant l\'heure du rendez-vous.','cancel'=>'Annuler le rendez-vous','cancel_confirm'=>'Annuler le rendez-vous ? La place est libérée pour d\'autres.','add_cal'=>'📅 Ajouter au calendrier','keep'=>'Conservez ce lien — réouvrable à tout moment pour le statut.'],
        'hu'=>['st_booked'=>'Megerősítve','st_checked_in'=>'Bejelentkezve','st_cancelled'=>'Törölve','st_no_show'=>'Nem jelent meg','view_ticket'=>'Jegy megtekintése →','checkin'=>'Bejelentkezés (megérkeztem)','checkin_note'=>'A bejelentkezés 30 perccel az időpont előtt elérhető.','cancel'=>'Időpont lemondása','cancel_confirm'=>'Lemondja az időpontot? A hely felszabadul másoknak.','add_cal'=>'📅 Naptárhoz adás','keep'=>'Őrizze meg ezt a linket — bármikor megnyitható a státuszért.'],
        'it'=>['st_booked'=>'Confermato','st_checked_in'=>'Check-in fatto','st_cancelled'=>'Annullato','st_no_show'=>'Assente','view_ticket'=>'Vedi biglietto →','checkin'=>'Check-in (sono arrivato)','checkin_note'=>'Il check-in è disponibile 30 minuti prima dell\'orario.','cancel'=>'Annulla prenotazione','cancel_confirm'=>'Annullare la prenotazione? Il posto si libera per altri.','add_cal'=>'📅 Aggiungi al calendario','keep'=>'Conserva questo link — riapribile in qualsiasi momento per lo stato.'],
        'es'=>['st_booked'=>'Confirmado','st_checked_in'=>'Registrado','st_cancelled'=>'Cancelado','st_no_show'=>'No presentado','view_ticket'=>'Ver ticket →','checkin'=>'Check-in (he llegado)','checkin_note'=>'El check-in está disponible 30 minutos antes de la hora.','cancel'=>'Cancelar la cita','cancel_confirm'=>'¿Cancelar la cita? La plaza se libera para otros.','add_cal'=>'📅 Añadir al calendario','keep'=>'Guarda este enlace — puedes reabrirlo en cualquier momento para el estado.'],
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
            && $hasTable('appointment_waitlist')) {
            set_setting('schema_version', (string)$target);
        }
    } catch (Throwable $e) {}
}
