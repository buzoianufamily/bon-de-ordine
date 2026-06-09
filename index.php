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
    // =========================== API ===========================
    if ($seg[0] === 'api') {
        header('Cache-Control: no-store');
        $action = $seg[1] ?? '';

        // ---- endpoint-uri publice (folosite de dispozitive) ----
        if ($action === 'state') { // polling afisaj
            $branch = (int)($_GET['branch'] ?? 1);
            json_out(['ok' => true] + queue_state($branch));
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
            case 'call-next':
                $t = call_next((int)input('counter_id', 0), (int)$u['id']);
                json_out(['ok' => true, 'ticket' => $t]);
            case 'call-specific':
                $t = call_specific((int)input('ticket_id', 0), (int)input('counter_id', 0), (int)$u['id']);
                json_out(['ok' => (bool)$t, 'ticket' => $t] + ($t ? [] : ['error' => 'Biletul nu mai este la rand']));
            case 'recall':    recall_ticket((int)input('ticket_id', 0)); json_out(['ok' => true]);
            case 'serving':   start_serving((int)input('ticket_id', 0)); json_out(['ok' => true]);
            case 'finish':    finish_ticket((int)input('ticket_id', 0)); json_out(['ok' => true]);
            case 'no-show':   no_show_ticket((int)input('ticket_id', 0)); json_out(['ok' => true]);
            case 'cancel':    cancel_ticket((int)input('ticket_id', 0)); json_out(['ok' => true]);
            case 'transfer':  transfer_ticket((int)input('ticket_id', 0), (int)input('service_id', 0)); json_out(['ok' => true]);
            case 'counter-state':
                $c = one('SELECT * FROM counters WHERE id = ?', [(int)($_GET['counter_id'] ?? 0)]);
                if (!$c) json_out(['ok' => false], 404);
                json_out(['ok' => true] + counter_view($c));
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
        if ($method === 'POST') {
            csrf_check();
            if (attempt_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
                redirect(current_user()['role'] === 'agent' ? 'counter' : 'admin');
            }
            flash('Email sau parola incorecte.', 'error');
            redirect('login');
        }
        view('public/login');
        return;
    }
    if ($seg[0] === 'logout') { if ($cu = current_user()) { log_user_status((int)$cu['id'],'offline'); q('UPDATE users SET work_status="offline" WHERE id=?', [(int)$cu['id']]); } logout(); redirect('login'); }

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
        $branch = (int)($_GET['branch'] ?? 1);
        if ($method === 'POST') {
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

    // bilet digital (telefon):  /t/{token}
    if ($seg[0] === 't' && !empty($seg[1])) {
        $t = one('SELECT t.*, s.name AS service_name, s.color FROM tickets t
                  JOIN services s ON s.id=t.service_id WHERE t.public_token = ?', [$seg[1]]);
        if (!$t) { http_response_code(404); echo 'Bilet inexistent.'; return; }
        view('public/virtual_ticket', ['t' => $t, 'token' => $seg[1]]);
        return;
    }

    // ===================== PROGRAMARI (public) =====================
    if ($seg[0] === 'book') {
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
            view('public/book_slots', compact('svc','date','slots'));
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
        view('public/appointment', ['a' => $appt]);
        return;
    }

    // ===================== TERMINAL OPERATOR =====================
    if ($seg[0] === 'counter') {
        $u = require_login();
        if (!empty($seg[1])) {
            $c = one('SELECT * FROM counters WHERE id = ?', [(int)$seg[1]]);
            if (!$c) { http_response_code(404); die('Ghiseu inexistent'); }
            $branch = one('SELECT * FROM branches WHERE id = ?', [$c['branch_id']]);
            view('public/counter', ['counter' => $c, 'branch' => $branch, 'u' => $u]);
            return;
        }
        $counters = all('SELECT * FROM counters ORDER BY code');
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
        $hash = md5(json_encode($state['called']) . json_encode($state['counters']));
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
