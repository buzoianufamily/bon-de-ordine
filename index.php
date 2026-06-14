<?php
/**
 * Front controller - Sistem de Bon de Ordine
 * Toate cererile (cu exceptia fisierelor reale) trec prin acest fisier.
 */
require __DIR__ . '/app/core/init.php';

// ---- determina ruta relativa la directorul aplicatiei ----
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$uriPath   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route     = '/' . ltrim(substr($uriPath, strlen($scriptDir)), '/');
$route     = rtrim($route, '/');
if ($route === '') $route = '/';
$seg       = explode('/', trim($route, '/'));
$method    = $_SERVER['REQUEST_METHOD'];

// helper scurt pentru body POST (form sau JSON)
function input(string $key, $default = null) {
    if (isset($_POST[$key])) return $_POST[$key];
    static $json = null;
    if ($json === null) {
        $raw = file_get_contents('php://input');
        $json = ($raw && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json')) ? (json_decode($raw, true) ?: []) : [];
    }
    return $json[$key] ?? $default;
}

try {
    // ===================== LANDLORD (administrare instante clienti, multi-tenant) =====================
    if ($seg[0] === 'landlord') {
        require APP_ROOT . '/app/landlord.php';
        landlord_dispatch($seg, $method);
        return;
    }

    // ===================== CRON (sarcini programate, protejat cu token) =====================
    if ($seg[0] === 'cron') {
        $token = (string) setting('cron_token', '');
        if ($token === '' || !hash_equals($token, (string)($_GET['key'] ?? ''))) {
            json_out(['ok' => false, 'error' => 'Token cron invalid'], 403);
        }
        require APP_ROOT . '/app/cron.php';
        json_out(run_cron_jobs());
    }

    // ===================== HEALTH (monitorizare uptime) =====================
    if ($seg[0] === 'health') {
        // conexiune proprie cu timeout scurt — nu folosim db() (care ar opri procesul daca DB e picata)
        $ok = false; $err = null;
        try {
            $c = $GLOBALS['__config']['db'];
            $pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}",
                $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
            $ok = ((int) $pdo->query('SELECT 1')->fetchColumn() === 1);
        } catch (Throwable $e) { $err = 'db'; }
        json_out([
            'ok'      => $ok,
            'service' => 'bon-de-ordine',
            'db'      => $ok ? 'up' : 'down',
            'time'    => date('c'),
            'error'   => $err,
        ], $ok ? 200 : 503);
    }

    // ===================== PWA (manifest + service worker) =====================
    if ($route === '/manifest.webmanifest') {
        header('Content-Type: application/manifest+json; charset=utf-8');
        $brand = (string) setting('brand_name', 'Bon de ordine');
        echo json_encode([
            'name' => $brand, 'short_name' => mb_substr($brand, 0, 12) ?: 'Bon',
            'start_url' => base_url() . '/', 'scope' => base_url() . '/',
            'display' => 'standalone', 'background_color' => '#0a0b0d',
            'theme_color' => (string) setting('accent_color', '#2563eb'),
            'icons' => [
                ['src' => url('assets/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => url('assets/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => url('assets/icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($route === '/sw.js') {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache');
        echo <<<'SWJS'
const CACHE = 'bdo-v1';
const ASSET_RE = /\.(css|js|woff2?|ttf|png|jpe?g|webp|svg|gif|ico)$/i;
self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))));
  self.clients.claim();
});
self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;       // doar same-origin
  if (url.pathname.indexOf('/api/') !== -1) return;  // niciodata cache la API (date live)
  if (ASSET_RE.test(url.pathname)) {                 // assets: cache-first
    e.respondWith(caches.open(CACHE).then(c => c.match(req).then(hit =>
      hit || fetch(req).then(res => { if (res.ok) c.put(req, res.clone()); return res; }))));
    return;
  }
  if (req.mode === 'navigate') {                     // pagini: network-first, fallback la cache
    e.respondWith(fetch(req).then(res => {
      const cc = res.clone(); caches.open(CACHE).then(c => c.put(req, cc)); return res;
    }).catch(() => caches.match(req)));
  }
});
SWJS;
        exit;
    }

    // =========================== API ===========================
    if ($seg[0] === 'api') {
        // ---- API public v1 (autentificat cu cheie) ----
        if (($seg[1] ?? '') === 'v1') { require APP_ROOT . '/app/api_v1.php'; api_v1($seg, $method); exit; }

        header('Cache-Control: no-store');
        $action = $seg[1] ?? '';

        // ---- endpoint-uri publice (folosite de dispozitive) ----
        if ($action === 'state') { // polling afisaj
            $branch = (int)($_GET['branch'] ?? 1);
            json_out(['ok' => true] + queue_state($branch, !empty($_GET['est'])));
        }
        if ($action === 'sse') { // server-sent events pentru afisaj
            sse_stream((int)($_GET['branch'] ?? 1));
        }
        if ($action === 'virtual') { // stare bilet digital (telefon)
            $t = one('SELECT t.*, s.name AS service_name, s.color, c.code AS counter_code, c.name AS counter_name
                      FROM tickets t JOIN services s ON s.id=t.service_id
                      LEFT JOIN counters c ON c.id=t.counter_id WHERE t.public_token = ?',
                     [$_GET['token'] ?? '']);
            if (!$t) json_out(['ok' => false, 'error' => 'Bilet inexistent'], 404);
            json_out(['ok' => true, 'ticket' => [
                'label' => $t['label'], 'status' => $t['status'], 'service' => $t['service_name'],
                'color' => $t['color'], 'counter' => $t['counter_code'] ? ($t['counter_name'] ?: $t['counter_code']) : null,
                'position' => $t['status'] === 'waiting' ? ticket_position($t) : 0,
                'wait_est' => $t['status'] === 'waiting' ? est_wait_seconds($t) : 0,
                'priority' => (int)$t['priority'],
            ]]);
        }
        if ($action === 'ticket' && $method === 'POST') { // emitere bilet (dispenser)
            $dev = device_by_key((string)input('device_key', ''));
            $branch_id = $dev['branch_id'] ?? (int)input('branch_id', 1);
            $svc = (int)input('service_id', 0);
            $priority = (bool)input('priority', false);
            $channel = (string)input('channel', 'paper');
            $formData = input('form_data', null);
            $formJson = (is_array($formData) && $formData) ? json_encode($formData, JSON_UNESCAPED_UNICODE) : null;
            try {
                $t = issue_ticket($svc, $priority, $channel, null, $formJson);
            } catch (Throwable $ex) {
                json_out(['ok' => false, 'error' => $ex->getMessage()], 422);
            }
            $svcRow = one('SELECT * FROM services WHERE id = ?', [$svc]);
            $resp = ['ok' => true, 'ticket' => $t, 'position' => ticket_position($t),
                     'wait_est' => est_wait_seconds($t),
                     'virtual_url' => url('t/' . $t['public_token'])];
            // daca dispozitivul printeaza in retea, trimite acum
            if ($dev && $dev['printer_mode'] === 'network' && $dev['printer_ip']) {
                $bytes = build_ticket_escpos($t, $svcRow, one('SELECT * FROM branches WHERE id=?', [$t['branch_id']]));
                $resp['print'] = print_network($dev['printer_ip'], (int)$dev['printer_port'], $bytes);
            }
            // pentru Android (bridge): trimite si octetii ESC/POS in base64
            if ($dev && $dev['printer_mode'] === 'android') {
                $bytes = build_ticket_escpos($t, $svcRow, one('SELECT * FROM branches WHERE id=?', [$t['branch_id']]));
                $resp['escpos_b64'] = base64_encode($bytes);
            }
            json_out($resp);
        }
        if ($action === 'virtual-cancel' && $method === 'POST') { // clientul renunta la rand de pe telefon
            $tok = (string) input('token', '');
            $row = $tok !== '' ? one("SELECT id, status FROM tickets WHERE public_token = ?", [$tok]) : null;
            if (!$row) json_out(['ok' => false, 'error' => 'Bilet inexistent'], 404);
            if (!in_array($row['status'], ['waiting','called'], true)) json_out(['ok' => false, 'error' => 'Biletul nu mai poate fi anulat']);
            cancel_ticket((int)$row['id']);
            json_out(['ok' => true]);
        }
        if ($action === 'heartbeat' && $method === 'POST') {
            $dev = device_by_key((string)input('device_key', ''));
            if ($dev) q('UPDATE devices SET last_seen = NOW() WHERE id = ?', [$dev['id']]);
            json_out(['ok' => (bool)$dev]);
        }

        // ---- endpoint-uri ce necesita autentificare (operator) ----
        $u = require_login();
        if ($method === 'POST') csrf_check();
        q('UPDATE users SET last_seen = NOW() WHERE id = ?', [(int)$u['id']]); // prezenta

        switch ($action) {
            case 'user-status': {
                $allowed = ['available','busy','paused','offline'];
                $stt = (string) input('status', '');
                if (!in_array($stt, $allowed, true)) json_out(['ok' => false, 'error' => 'Status invalid'], 422);
                log_user_status((int)$u['id'], $stt);
                q('UPDATE users SET work_status = ?, last_seen = NOW() WHERE id = ?', [$stt, (int)$u['id']]);
                json_out(['ok' => true, 'status' => $stt]);
            }
            case 'issue-manual': {   // emitere bon walk-in de la receptie/operator
                $svcId = (int) input('service_id', 0);
                $priority = (bool) input('priority', false);
                try { $t = issue_ticket($svcId, $priority, 'web'); }
                catch (Throwable $ex) { json_out(['ok' => false, 'error' => $ex->getMessage()], 422); }
                json_out(['ok' => true, 'ticket' => $t, 'position' => ticket_position($t),
                          'virtual_url' => url('t/' . $t['public_token'])]);
            }
            case 'pin-switch': {
                $nu = pin_switch((string) input('pin', ''));
                if (!$nu) json_out(['ok' => false, 'error' => 'PIN invalid']);
                audit('pin_switch', 'auth', $nu['id']);
                json_out(['ok' => true, 'name' => $nu['name']]);
            }
            case 'call-next':
                $t = call_next((int)input('counter_id', 0), (int)$u['id'], (int)input('service_id', 0));
                json_out(['ok' => true, 'ticket' => $t]);
            case 'call-specific':
                $t = call_specific((int)input('ticket_id', 0), (int)input('counter_id', 0), (int)$u['id']);
                json_out(['ok' => (bool)$t, 'ticket' => $t] + ($t ? [] : ['error' => 'Biletul nu mai este la rand']));
            case 'recall':    recall_ticket((int)input('ticket_id', 0)); json_out(['ok' => true]);
            case 'serving':   start_serving((int)input('ticket_id', 0)); json_out(['ok' => true]);
            case 'finish':    finish_ticket((int)input('ticket_id', 0)); json_out(['ok' => true]);
            case 'no-show':   no_show_ticket((int)input('ticket_id', 0)); json_out(['ok' => true]);
            case 'requeue':   $rq = requeue_ticket((int)input('ticket_id', 0)); json_out(['ok' => $rq] + ($rq ? [] : ['error' => 'Biletul nu mai poate fi repus']));
            case 'cancel':    cancel_ticket((int)input('ticket_id', 0)); json_out(['ok' => true]);
            case 'transfer':  transfer_ticket((int)input('ticket_id', 0), (int)input('service_id', 0)); json_out(['ok' => true]);
            case 'transfer-counter': transfer_to_counter((int)input('ticket_id', 0), (int)input('target_counter', 0)); json_out(['ok' => true]);
            case 'counter-pause': {
                $cid = (int) input('counter_id', 0);
                $note = mb_substr(trim((string) input('note', '')), 0, 120);
                q("UPDATE counters SET status='paused', pause_note=? WHERE id=?", [$note !== '' ? $note : null, $cid]);
                // elibereaza biletele directionate catre acest ghiseu, ca sa nu ramana blocate (optional)
                $released = setting('release_on_pause', '1') === '1' ? release_targeted_tickets($cid) : 0;
                json_out(['ok' => true, 'released' => $released]);
            }
            case 'counter-open':
                q("UPDATE counters SET status='open', pause_note=NULL WHERE id=?", [(int) input('counter_id', 0)]);
                json_out(['ok' => true]);
            case 'counter-state':
                $c = one('SELECT * FROM counters WHERE id = ?', [(int)($_GET['counter_id'] ?? 0)]);
                if (!$c) json_out(['ok' => false], 404);
                $myStats = one("SELECT COUNT(*) served,
                                  AVG(CASE WHEN finished_at IS NOT NULL AND called_at IS NOT NULL
                                      THEN TIMESTAMPDIFF(SECOND, called_at, finished_at) END) avg_handle
                                FROM tickets WHERE agent_id=? AND status='served' AND DATE(finished_at)=CURDATE()",
                                [(int)$u['id']]) ?: ['served'=>0,'avg_handle'=>null];
                json_out(['ok' => true, 'me' => [
                    'served' => (int)$myStats['served'],
                    'avg_handle' => (int)round((float)($myStats['avg_handle'] ?? 0)),
                ]] + counter_view($c));
            case 'branch-state':
                json_out(['ok' => true] + branch_queue((int)($_GET['branch'] ?? 1)));
        }
        json_out(['ok' => false, 'error' => 'Endpoint necunoscut'], 404);
    }

    // ===================== PAGINI PUBLICE =====================
    if ($route === '/') {
        if (current_user()) redirect('admin');
        view('public/portal');
        return;
    }
    if ($seg[0] === 'login') {
        // ---- pasul 2: cod 2FA ----
        if (($seg[1] ?? '') === '2fa') {
            $puid = (int)($_SESSION['2fa_uid'] ?? 0);
            if (!$puid || (time() - (int)($_SESSION['2fa_time'] ?? 0)) > 300) {
                unset($_SESSION['2fa_uid'], $_SESSION['2fa_time']); redirect('login');
            }
            if ($method === 'POST') {
                csrf_check();
                $pu = one('SELECT * FROM users WHERE id=? AND active=1', [$puid]);
                $code = (string)($_POST['code'] ?? '');
                if ($pu && !empty($pu['totp_enabled'])) {
                    $okTotp = totp_verify((string)$pu['totp_secret'], $code);
                    // fallback: cod de recuperare (de unica folosinta) — se consuma la folosire
                    $newJson = $okTotp ? null : totp_backup_consume($pu['totp_backup'] ?? null, $code);
                    if ($okTotp || $newJson !== null) {
                        if (!$okTotp) { q('UPDATE users SET totp_backup=? WHERE id=?', [$newJson, $puid]); audit('2fa_backup_used', 'auth', $puid); }
                        unset($_SESSION['2fa_uid'], $_SESSION['2fa_time']);
                        complete_login($puid); audit('login', 'auth');
                        redirect($pu['role'] === 'agent' ? 'counter' : 'admin');
                    }
                }
                audit('login_failed_2fa', 'auth', null, $pu['email'] ?? '');
                flash('Cod incorect. Incearca din nou.', 'error');
            }
            view('public/login_2fa');
            return;
        }
        // ---- am uitat parola: cere link pe email ----
        if (($seg[1] ?? '') === 'forgot') {
            if ($method === 'POST') {
                csrf_check();
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $tries = 0;
                try { $tries = (int) val("SELECT COUNT(*) FROM audit_log WHERE action='pwreset_request' AND ip=? AND created_at > NOW() - INTERVAL 15 MINUTE", [$ip]); } catch (Throwable $e) {}
                if ($tries < 5) {
                    $email = trim((string) input('email', ''));
                    password_reset_request($email);                 // best-effort, fara a divulga existenta contului
                    audit('pwreset_request', 'auth', null, substr($email, 0, 120));
                }
                view('public/forgot', ['sent' => true]);
                return;
            }
            view('public/forgot', ['sent' => false]);
            return;
        }
        // ---- resetare parola din link (token) ----
        if (($seg[1] ?? '') === 'reset') {
            $token = (string) ($_GET['token'] ?? input('token', ''));
            if ($method === 'POST') {
                csrf_check();
                $res = password_reset_apply($token, (string) input('password', ''), (string) input('password2', ''));
                if ($res['ok']) {
                    audit('pwreset_done', 'auth', $res['uid']);
                    flash('Parola a fost schimbata. Te poti autentifica acum.');
                    redirect('login');
                }
                // daca tokenul a expirat/devenit invalid intre afisare si trimitere, arata panoul „link invalid"
                view('public/reset', ['token' => $token, 'valid' => password_reset_lookup($token) !== null, 'error' => $res['error']]);
                return;
            }
            $valid = password_reset_lookup($token) !== null;
            view('public/reset', ['token' => $token, 'valid' => $valid, 'error' => '']);
            return;
        }
        if ($method === 'POST') {
            csrf_check();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            // throttling: max 10 incercari esuate / IP in 10 minute
            $fails = 0;
            try { $fails = (int) val("SELECT COUNT(*) FROM audit_log WHERE action IN('login_failed','login_failed_2fa') AND ip=? AND created_at > NOW() - INTERVAL 10 MINUTE", [$ip]); } catch (Throwable $e) {}
            if ($fails >= 10) { flash('Prea multe incercari esuate. Reincearca peste cateva minute.', 'error'); redirect('login'); }
            $u = verify_credentials((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
            if ($u) {
                if (!empty($u['totp_enabled']) && !empty($u['totp_secret'])) {
                    // parola OK -> cere codul 2FA (sesiune intermediara, fara uid)
                    $_SESSION['2fa_uid'] = (int)$u['id']; $_SESSION['2fa_time'] = time();
                    redirect('login/2fa');
                }
                complete_login((int)$u['id']); audit('login', 'auth');
                redirect($u['role'] === 'agent' ? 'counter' : 'admin');
            }
            audit('login_failed', 'auth', null, substr((string)($_POST['email'] ?? ''), 0, 120));
            flash('Email sau parola incorecte.', 'error');
            redirect('login');
        }
        view('public/login');
        return;
    }
    if ($seg[0] === 'logout') { if ($cu = current_user()) { log_user_status((int)$cu['id'],'offline'); q('UPDATE users SET work_status="offline" WHERE id=?', [(int)$cu['id']]); } logout(); redirect('login'); }

    // contul propriu (orice utilizator autentificat — util mai ales operatorilor care nu intra in backoffice)
    if ($seg[0] === 'account') {
        $u = require_login();
        if ($method === 'POST') {
            csrf_check();
            $res = change_own_password((int)$u['id'], (string) input('cur_pass', ''),
                (string) input('new_pass', ''), (string) input('new_pass2', ''));
            if ($res['ok']) { audit('password_change', 'user', $u['id']); flash('Parola a fost schimbata.'); }
            else { flash($res['error'], 'error'); }
            redirect('account');
        }
        view('public/account', compact('u'));
        return;
    }

    // dispozitiv prin connection key:  /launcher?key=XXX  sau  /d/XXX  /screen/XXX
    if ($seg[0] === 'launcher' || $seg[0] === 'd' || $seg[0] === 'screen') {
        $key = $_GET['key'] ?? $_GET['connection_key'] ?? ($seg[1] ?? '');
        $dev = device_by_key((string)$key);
        if (!$dev) { http_response_code(404); view('public/device_404', ['key' => $key]); return; }
        q('UPDATE devices SET last_seen = NOW() WHERE id = ?', [$dev['id']]);
        $branch = one('SELECT * FROM branches WHERE id = ?', [$dev['branch_id']]);
        $services = device_services($dev);
        $type = $dev['type'];
        if ($seg[0] === 'screen') $type = 'player';
        if ($seg[0] === 'd') $type = 'dispenser';
        if ($type === 'dispenser' || $type === 'digital_ticket') { view('public/dispenser', compact('dev','branch','services')); return; }
        // player / widget_player
        view('public/display', compact('dev','branch','services'));
        return;
    }

    // feedback client (anonim, prin QR pe afisaj):  /feedback
    if ($seg[0] === 'feedback') {
        if (setting('mod_feedback', '1') !== '1') { http_response_code(404); echo 'Modulul de feedback este dezactivat.'; return; }
        $branch = (int)($_GET['branch'] ?? 1);
        if ($method === 'POST') {
            // anti-spam: max 3 evaluari / IP / 10 minute
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!rate_limit_ok('fb:' . $ip, 3, 600)) {
                view('public/feedback', ['done' => true, 'branch' => $branch]);
                return;
            }
            $rating  = (int) input('rating', 0);
            $comment = trim((string) input('comment', ''));
            if ($rating >= 1 && $rating <= 5) {
                q('INSERT INTO feedback (ticket_id, branch_id, rating, comment) VALUES (NULL, ?, ?, ?)',
                  [$branch ?: null, $rating, $comment !== '' ? mb_substr($comment, 0, 500) : null]);
                view('public/feedback', ['done' => true, 'branch' => $branch]);
                return;
            }
            flash('Alege o nota de la 1 la 5.', 'error');
        }
        view('public/feedback', ['done' => false, 'branch' => $branch]);
        return;
    }

    // status public al cozii (fara cheie de dispozitiv) — pentru site-ul clientului:  /status?branch=ID
    if ($seg[0] === 'status') {
        if (setting('mod_public_status', '0') !== '1') { http_response_code(404); echo 'Pagina de status public este dezactivata.'; return; }
        $branchId = (int)($seg[1] ?? $_GET['branch'] ?? 1);
        $branch = one('SELECT * FROM branches WHERE id=?', [$branchId]) ?: one('SELECT * FROM branches ORDER BY id LIMIT 1');
        if (!$branch) { http_response_code(404); echo 'Filiala inexistenta.'; return; }
        view('public/status', ['branch' => $branch] + queue_state((int)$branch['id'], true));
        return;
    }

    // bilet digital (telefon):  /t/{token}
    if ($seg[0] === 't' && !empty($seg[1])) {
        $t = one('SELECT t.*, s.name AS service_name, s.color FROM tickets t
                  JOIN services s ON s.id=t.service_id WHERE t.public_token = ?', [$seg[1]]);
        if (!$t) { http_response_code(404); echo 'Bilet inexistent.'; return; }
        $lang = strtolower((string)($_GET['lang'] ?? 'ro'));
        if (!isset(disp_lang_meta()[$lang])) $lang = 'ro';
        view('public/virtual_ticket', ['t' => $t, 'token' => $seg[1], 'lang' => $lang]);
        return;
    }

    // ===================== PROGRAMARI (public) =====================
    if ($seg[0] === 'book') {
        if (setting('mod_booking', '1') !== '1') { http_response_code(404); echo 'Modulul de programari este dezactivat.'; return; }
        if (!empty($seg[1]) && ctype_digit($seg[1])) {
            $svc = one('SELECT s.*, b.name AS branch_name FROM services s JOIN branches b ON b.id=s.branch_id
                        WHERE s.id=? AND s.status="active" AND s.appt_enabled=1', [(int)$seg[1]]);
            if (!$svc) { http_response_code(404); echo 'Serviciu indisponibil pentru programari.'; return; }
            if ($method === 'POST') {
                $slot = (string)input('slot_start', '');
                $name = trim((string)input('name','')); $phone = trim((string)input('phone','')); $email = trim((string)input('email',''));
                try { $appt = appt_book((int)$svc['id'], $slot, $name ?: null, $phone ?: null, $email ?: null); }
                catch (Throwable $ex) { flash($ex->getMessage(), 'error'); redirect('book/'.$svc['id'].'?date='.urlencode(substr($slot,0,10) ?: date('Y-m-d'))); }
                redirect('a/'.$appt['public_token']);
            }
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
            $slots = appt_slots($svc, $date);
            $closed = branch_closure_reason((int)$svc['branch_id'], strtotime($date.' 12:00:00'));
            view('public/book_slots', compact('svc','date','slots','closed'));
            return;
        }
        $services = all('SELECT s.*, b.name AS branch_name FROM services s JOIN branches b ON b.id=s.branch_id
                         WHERE s.status="active" AND s.appt_enabled=1 ORDER BY b.name, s.sort_order');
        view('public/book_services', ['services' => $services]);
        return;
    }

    // status programare + check-in:  /a/{token}
    if ($seg[0] === 'a' && !empty($seg[1])) {
        $appt = one('SELECT a.*, s.name service_name, s.color, b.name branch_name, t.public_token AS ticket_token
                     FROM appointments a JOIN services s ON s.id=a.service_id JOIN branches b ON b.id=a.branch_id
                     LEFT JOIN tickets t ON t.id=a.ticket_id WHERE a.public_token=?', [$seg[1]]);
        if (!$appt) { http_response_code(404); echo 'Programare inexistenta.'; return; }
        if (($seg[2] ?? '') === 'checkin' && $method === 'POST') {
            try { $tk = appt_checkin($appt); redirect('t/'.$tk['public_token']); }
            catch (Throwable $ex) { flash($ex->getMessage(), 'error'); redirect('a/'.$seg[1]); }
        }
        if (($seg[2] ?? '') === 'cancel' && $method === 'POST') {
            if ($appt['status'] === 'booked') {
                q("UPDATE appointments SET status='cancelled' WHERE id=?", [$appt['id']]);
                flash('Programarea a fost anulată.');
            } else flash('Programarea nu mai poate fi anulată.', 'error');
            redirect('a/'.$seg[1]);
        }
        view('public/appointment', ['a' => $appt]);
        return;
    }

    // ===================== AFISAJ DE GHISEU (tableta la birou):  /cd/{counter_id} =====================
    if ($seg[0] === 'cd' && !empty($seg[1]) && ctype_digit((string)$seg[1])) {
        $counter = one('SELECT * FROM counters WHERE id = ?', [(int)$seg[1]]);
        if (!$counter) { http_response_code(404); echo 'Ghiseu inexistent.'; return; }
        $branch = one('SELECT * FROM branches WHERE id = ?', [$counter['branch_id']]);
        view('public/counter_display', compact('counter', 'branch'));
        return;
    }

    // ===================== CONCIERGE (receptie: cheama pentru orice ghiseu) =====================
    if ($seg[0] === 'concierge') {
        if (setting('mod_concierge', '1') !== '1') { http_response_code(404); echo 'Modulul Concierge este dezactivat.'; return; }
        $u = require_login();
        $branches = all('SELECT id,name FROM branches ORDER BY name');
        $branchId = (int)($_GET['branch'] ?? ($branches[0]['id'] ?? 1));
        $branch = one('SELECT * FROM branches WHERE id=?', [$branchId]) ?: ($branches[0] ?? ['id'=>0,'name'=>'—']);
        $counters = all('SELECT id,code,name,status FROM counters WHERE branch_id=? ORDER BY code', [$branch['id']]);
        $services = all('SELECT id,prefix,name,color,allow_priority FROM services WHERE branch_id=? AND status="active" ORDER BY sort_order', [$branch['id']]);
        view('public/concierge', compact('u','branches','branch','counters','services'));
        return;
    }

    // ===================== TERMINAL OPERATOR =====================
    if ($seg[0] === 'counter') {
        $u = require_login();
        // ghiseele atribuite utilizatorului (gol = toate)
        $allowedCsv = (string) (val('SELECT allowed_counters FROM users WHERE id=?', [(int)$u['id']]) ?? '');
        $allowedIds = array_filter(array_map('intval', explode(',', $allowedCsv)));
        if (!empty($seg[1])) {
            $c = one('SELECT * FROM counters WHERE id = ?', [(int)$seg[1]]);
            if (!$c) { http_response_code(404); die('Ghiseu inexistent'); }
            if ($allowedIds && !in_array((int)$c['id'], $allowedIds, true)) {
                flash('Nu ai acces la acest ghiseu. Alege unul dintre ghiseele atribuite tie.', 'error');
                redirect('counter');
            }
            $branch = one('SELECT * FROM branches WHERE id = ?', [$c['branch_id']]);
            view('public/counter', ['counter' => $c, 'branch' => $branch, 'u' => $u]);
            return;
        }
        $counters = all('SELECT * FROM counters ORDER BY code');
        if ($allowedIds) $counters = array_values(array_filter($counters, fn($c) => in_array((int)$c['id'], $allowedIds, true)));
        view('public/counter_select', ['counters' => $counters, 'u' => $u]);
        return;
    }

    // ===================== ADMIN (backoffice) =====================
    if ($seg[0] === 'admin') {
        require_role(['admin','manager']);
        require APP_ROOT . '/app/admin_routes.php';
        admin_dispatch($seg, $method);
        return;
    }

    http_response_code(404);
    echo '<h1>404</h1><p>Pagina nu a fost gasita.</p><p><a href="' . e(url('/')) . '">Acasa</a></p>';

} catch (Throwable $ex) {
    if (cfg('app.env') === 'dev') {
        http_response_code(500);
        echo '<pre>' . e($ex->getMessage() . "\n\n" . $ex->getTraceAsString()) . '</pre>';
    } else {
        if (want_json()) json_out(['ok' => false, 'error' => 'Eroare interna'], 500);
        http_response_code(500);
        echo 'A aparut o eroare. Incercati din nou.';
    }
}

// ---------- helpers locale ----------
function device_by_key(string $key): ?array {
    if ($key === '') return null;
    return one('SELECT * FROM devices WHERE connection_key = ?', [strtoupper(trim($key))]);
}
function device_services(array $dev): array {
    if ((int)$dev['all_services'] === 1) {
        return all('SELECT * FROM services WHERE branch_id = ? AND status = "active" ORDER BY sort_order', [$dev['branch_id']]);
    }
    return all('SELECT s.* FROM services s JOIN device_services d ON d.service_id = s.id
                WHERE d.device_id = ? AND s.status = "active" ORDER BY s.sort_order', [$dev['id']]);
}

/** Flux SSE pentru afisaj (trimite starea cand se schimba). */
function sse_stream(int $branch): void {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no'); // dezactiveaza buffering pe nginx/proxy
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) ob_end_flush();
    set_time_limit(0);
    ignore_user_abort(false);
    echo "retry: 3000\n\n";
    $lastHash = '';
    $start = time();
    while (true) {
        $state = queue_state($branch);
        // include si 'waiting' + 'notice' in hash: altfel TV-ul nu afla de bilete noi emise / anunt schimbat
        $hash = md5(json_encode($state['called']) . json_encode($state['counters'])
                  . json_encode($state['waiting']) . (string)($state['notice'] ?? ''));
        if ($hash !== $lastHash) {
            echo 'data: ' . json_encode($state, JSON_UNESCAPED_UNICODE) . "\n\n";
            $lastHash = $hash;
        } else {
            echo ": ping\n\n"; // heartbeat
        }
        @ob_flush(); @flush();
        if (connection_aborted()) break;
        if (time() - $start > 50) break; // reconectare periodica (limite hosting)
        sleep(2);
    }
    exit;
}
