<?php
/**
 * API public v1 — autentificat cu cheie API (X-Api-Key sau ?key=).
 * Rute sub /api/v1/...  (vezi pagina Administrare → API pentru documentatie).
 */
function api_v1(array $seg, string $method): void {
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');

    if ($method === 'OPTIONS') { http_response_code(204); exit; }

    $key  = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
    $real = (string) setting('api_key', '');
    if ($real === '' || !hash_equals($real, (string)$key)) {
        json_out(['ok' => false, 'error' => 'Cheie API invalida sau lipsa'], 401);
    }

    // rate-limit: max 120 cereri/minut per cheie (contor pe minut, un singur rand)
    $limit = 120;
    try {
        $rk = substr(sha1('api:' . $key), 0, 64);
        $minute = (int) floor(time() / 60);
        q("INSERT INTO api_rate (rk, minute, cnt) VALUES (?, ?, 1)
           ON DUPLICATE KEY UPDATE cnt = IF(minute = VALUES(minute), cnt + 1, 1), minute = VALUES(minute)",
          [$rk, $minute]);
        $cnt = (int) val("SELECT cnt FROM api_rate WHERE rk = ?", [$rk]);
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . max(0, $limit - $cnt));
        if ($cnt > $limit) {
            header('Retry-After: ' . (60 - (time() % 60)));
            json_out(['ok' => false, 'error' => 'Prea multe cereri (limita ' . $limit . '/minut)'], 429);
        }
    } catch (Throwable $e) {} // tabela lipsa pre-migrare -> nu bloca API-ul

    $res = $seg[2] ?? '';
    $arg = $seg[3] ?? null;
    $sub = $seg[4] ?? null;

    // GET /api/v1/state?branch=ID — starea cozii
    if ($res === 'state' && $method === 'GET') {
        $branch = (int)($_GET['branch'] ?? 1);
        json_out(['ok' => true] + queue_state($branch));
    }

    // GET /api/v1/branches — filialele active
    if ($res === 'branches' && $method === 'GET') {
        $rows = all("SELECT id, name, city, country, address FROM branches WHERE active=1 ORDER BY name");
        json_out(['ok' => true, 'branches' => array_map(fn($b) => [
            'id' => (int)$b['id'], 'name' => $b['name'], 'city' => $b['city'],
            'country' => $b['country'], 'address' => $b['address'],
        ], $rows)]);
    }

    // GET /api/v1/services?branch=ID — serviciile active
    if ($res === 'services' && $method === 'GET') {
        $branch = (int)($_GET['branch'] ?? 0);
        $where = "status = 'active'"; $args = [];
        if ($branch) { $where .= ' AND branch_id = ?'; $args[] = $branch; }
        $rows = all("SELECT id, branch_id, prefix, name, color, allow_priority FROM services WHERE $where ORDER BY branch_id, sort_order", $args);
        json_out(['ok' => true, 'services' => array_map(fn($s) => [
            'id' => (int)$s['id'], 'branch_id' => (int)$s['branch_id'], 'prefix' => $s['prefix'],
            'name' => $s['name'], 'color' => $s['color'], 'allow_priority' => (bool)$s['allow_priority'],
        ], $rows)]);
    }

    // GET /api/v1/counters?branch=ID — ghiseele
    if ($res === 'counters' && $method === 'GET') {
        $branch = (int)($_GET['branch'] ?? 0);
        $where = '1=1'; $args = [];
        if ($branch) { $where .= ' AND branch_id = ?'; $args[] = $branch; }
        $rows = all("SELECT id, branch_id, code, name, status FROM counters WHERE $where ORDER BY branch_id, code", $args);
        json_out(['ok' => true, 'counters' => array_map(fn($c) => [
            'id' => (int)$c['id'], 'branch_id' => (int)$c['branch_id'], 'code' => $c['code'],
            'name' => $c['name'], 'status' => $c['status'],
        ], $rows)]);
    }

    // POST /api/v1/tickets {service_id, priority?, channel?} — emite un bon
    if ($res === 'tickets' && $arg === null && $method === 'POST') {
        $svc = (int) input('service_id', 0);
        $priority = (bool) input('priority', false);
        $channel = (string) input('channel', 'web');
        try { $t = issue_ticket($svc, $priority, $channel ?: 'web'); }
        catch (Throwable $ex) { json_out(['ok' => false, 'error' => $ex->getMessage()], 422); }
        json_out(['ok' => true, 'ticket' => [
            'id' => (int)$t['id'], 'label' => $t['label'], 'status' => $t['status'],
            'service_id' => (int)$t['service_id'], 'branch_id' => (int)$t['branch_id'],
            'public_token' => $t['public_token'], 'position' => ticket_position($t),
            'wait_est_sec' => est_wait_seconds($t), 'follow_url' => url('t/' . $t['public_token']),
        ]]);
    }

    // DELETE /api/v1/tickets/{token} — anuleaza un bon (doar daca e in asteptare/chemat)
    if ($res === 'tickets' && $arg !== null && $method === 'DELETE') {
        $t = one('SELECT id, status FROM tickets WHERE public_token = ?', [$arg]);
        if (!$t) json_out(['ok' => false, 'error' => 'Bilet inexistent'], 404);
        if (!in_array($t['status'], ['waiting','called'], true)) json_out(['ok' => false, 'error' => 'Biletul nu mai poate fi anulat'], 409);
        cancel_ticket((int)$t['id']);
        json_out(['ok' => true]);
    }

    // GET /api/v1/tickets/{token} — starea unui bon
    if ($res === 'tickets' && $arg !== null && $method === 'GET') {
        $t = one('SELECT t.*, s.name service_name FROM tickets t JOIN services s ON s.id=t.service_id WHERE t.public_token = ?', [$arg]);
        if (!$t) json_out(['ok' => false, 'error' => 'Bilet inexistent'], 404);
        json_out(['ok' => true, 'ticket' => [
            'label' => $t['label'], 'status' => $t['status'], 'service' => $t['service_name'],
            'position' => $t['status'] === 'waiting' ? ticket_position($t) : 0,
            'wait_est_sec' => $t['status'] === 'waiting' ? est_wait_seconds($t) : 0,
        ]]);
    }

    // ---- Programari online (respecta modulul) ----
    $apptOn = setting('mod_booking', '1') === '1';

    // GET /api/v1/slots?service_id=&date= — sloturi disponibile
    if ($res === 'slots' && $method === 'GET') {
        if (!$apptOn) json_out(['ok' => false, 'error' => 'Programarile sunt dezactivate'], 404);
        $svc = one('SELECT * FROM services WHERE id=? AND status="active" AND appt_enabled=1', [(int)($_GET['service_id'] ?? 0)]);
        if (!$svc) json_out(['ok' => false, 'error' => 'Serviciu indisponibil pentru programari'], 404);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
        json_out(['ok' => true, 'date' => $date, 'slots' => array_map(fn($s) => [
            'start' => $s['start'], 'time' => $s['time'], 'available' => (int)$s['available'],
            'full' => (bool)$s['full'], 'past' => (bool)$s['past'],
        ], appt_slots($svc, $date))]);
    }

    // POST /api/v1/appointments {service_id, slot_start, name?, phone?, email?} — rezerva
    if ($res === 'appointments' && $arg === null && $method === 'POST') {
        if (!$apptOn) json_out(['ok' => false, 'error' => 'Programarile sunt dezactivate'], 404);
        try {
            $a = appt_book((int) input('service_id', 0), (string) input('slot_start', ''),
                (trim((string) input('name', '')) ?: null), (trim((string) input('phone', '')) ?: null),
                (trim((string) input('email', '')) ?: null));
        } catch (Throwable $ex) { json_out(['ok' => false, 'error' => $ex->getMessage()], 422); }
        json_out(['ok' => true, 'appointment' => [
            'id' => (int)$a['id'], 'public_token' => $a['public_token'], 'slot_start' => $a['slot_start'],
            'status' => $a['status'], 'follow_url' => url('a/' . $a['public_token']),
        ]]);
    }

    // GET /api/v1/appointments?date=&service_id=&branch= — lista programari (pt sincronizare)
    if ($res === 'appointments' && $arg === null && $method === 'GET') {
        if (!$apptOn) json_out(['ok' => false, 'error' => 'Programarile sunt dezactivate'], 404);
        $date = (string) input('date', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
        $w = 'DATE(a.slot_start) = ?'; $args = [$date];
        $sid = (int) input('service_id', 0); if ($sid) { $w .= ' AND a.service_id = ?'; $args[] = $sid; }
        $bid = (int) input('branch', 0);     if ($bid) { $w .= ' AND a.branch_id = ?';  $args[] = $bid; }
        $rows = all("SELECT a.public_token, a.status, a.slot_start, a.slot_end, a.service_id, a.branch_id,
                     a.customer_name, a.customer_phone, a.customer_email, s.name service_name
                     FROM appointments a JOIN services s ON s.id = a.service_id
                     WHERE $w ORDER BY a.slot_start LIMIT 500", $args);
        json_out(['ok' => true, 'date' => $date, 'appointments' => array_map(fn($a) => [
            'public_token' => $a['public_token'], 'status' => $a['status'],
            'slot_start' => $a['slot_start'], 'slot_end' => $a['slot_end'],
            'service_id' => (int)$a['service_id'], 'service' => $a['service_name'], 'branch_id' => (int)$a['branch_id'],
            'name' => $a['customer_name'], 'phone' => $a['customer_phone'], 'email' => $a['customer_email'],
        ], $rows)]);
    }

    // POST /api/v1/appointments/{token}/checkin — check-in (genereaza bonul)
    if ($res === 'appointments' && $arg !== null && $sub === 'checkin' && $method === 'POST') {
        if (!$apptOn) json_out(['ok' => false, 'error' => 'Programarile sunt dezactivate'], 404);
        $a = one('SELECT * FROM appointments WHERE public_token = ?', [$arg]);
        if (!$a) json_out(['ok' => false, 'error' => 'Programare inexistenta'], 404);
        try { $t = appt_checkin($a); }
        catch (Throwable $ex) { json_out(['ok' => false, 'error' => $ex->getMessage()], 422); }
        json_out(['ok' => true, 'ticket' => [
            'label' => $t['label'], 'public_token' => $t['public_token'],
            'position' => ticket_position($t), 'follow_url' => url('t/' . $t['public_token']),
        ]]);
    }

    // POST /api/v1/appointments/{token}/reschedule {slot_start} — muta in alt slot
    if ($res === 'appointments' && $arg !== null && $sub === 'reschedule' && $method === 'POST') {
        if (!$apptOn) json_out(['ok' => false, 'error' => 'Programarile sunt dezactivate'], 404);
        $a = one('SELECT id FROM appointments WHERE public_token = ?', [$arg]);
        if (!$a) json_out(['ok' => false, 'error' => 'Programare inexistenta'], 404);
        try { $na = appt_reschedule((int)$a['id'], (string) input('slot_start', '')); }
        catch (Throwable $ex) { json_out(['ok' => false, 'error' => $ex->getMessage()], 422); }
        if (!$na) json_out(['ok' => false, 'error' => 'Programarea nu mai poate fi reprogramata'], 409);
        json_out(['ok' => true, 'appointment' => [
            'public_token' => $na['public_token'], 'slot_start' => $na['slot_start'], 'status' => $na['status'],
        ]]);
    }

    // DELETE /api/v1/appointments/{token} — anuleaza o programare (doar daca e rezervata)
    if ($res === 'appointments' && $arg !== null && $method === 'DELETE') {
        if (!$apptOn) json_out(['ok' => false, 'error' => 'Programarile sunt dezactivate'], 404);
        $a = one('SELECT id, status FROM appointments WHERE public_token = ?', [$arg]);
        if (!$a) json_out(['ok' => false, 'error' => 'Programare inexistenta'], 404);
        if (!appt_cancel((int)$a['id'])) json_out(['ok' => false, 'error' => 'Programarea nu mai poate fi anulata'], 409);
        json_out(['ok' => true]);
    }

    // GET /api/v1/appointments/{token} — starea unei programari
    if ($res === 'appointments' && $arg !== null && $method === 'GET') {
        $a = one('SELECT a.*, s.name service_name, t.label ticket_label FROM appointments a
                  JOIN services s ON s.id=a.service_id LEFT JOIN tickets t ON t.id=a.ticket_id
                  WHERE a.public_token = ?', [$arg]);
        if (!$a) json_out(['ok' => false, 'error' => 'Programare inexistenta'], 404);
        json_out(['ok' => true, 'appointment' => [
            'service' => $a['service_name'], 'slot_start' => $a['slot_start'],
            'status' => $a['status'], 'ticket_label' => $a['ticket_label'],
        ]]);
    }

    // POST /api/v1/feedback {rating, comment?, branch?, ticket_token?} — trimite o evaluare
    if ($res === 'feedback' && $method === 'POST') {
        if (setting('mod_feedback', '1') !== '1') json_out(['ok' => false, 'error' => 'Modulul de feedback este dezactivat'], 404);
        $rating = (int) input('rating', 0);
        if ($rating < 1 || $rating > 5) json_out(['ok' => false, 'error' => 'rating trebuie sa fie intre 1 si 5'], 422);
        $comment = trim((string) input('comment', ''));
        $ticketId = null; $branchId = ((int) input('branch', 0)) ?: null;
        $tok = trim((string) input('ticket_token', ''));
        if ($tok !== '') {
            $t = one('SELECT id, branch_id FROM tickets WHERE public_token = ?', [$tok]);
            if ($t) { $ticketId = (int) $t['id']; $branchId = (int) $t['branch_id']; }
        }
        q('INSERT INTO feedback (ticket_id, branch_id, rating, comment) VALUES (?,?,?,?)',
          [$ticketId, $branchId, $rating, $comment !== '' ? mb_substr($comment, 0, 500) : null]);
        json_out(['ok' => true, 'id' => insert_id()]);
    }

    // GET /api/v1/stats?from&to&branch — rezumat KPI
    if ($res === 'stats' && $method === 'GET') {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d');
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
        $branch = (int)($_GET['branch'] ?? 0);
        $where = 'DATE(issued_at) BETWEEN ? AND ?'; $args = [$from, $to];
        if ($branch) { $where .= ' AND branch_id = ?'; $args[] = $branch; }
        $k = one("SELECT COUNT(*) total, SUM(status='served') served, SUM(status='no_show') no_show,
                  SUM(status='cancelled') cancelled, AVG(TIMESTAMPDIFF(SECOND,issued_at,called_at)) avg_wait,
                  AVG(CASE WHEN status='served' THEN TIMESTAMPDIFF(SECOND,called_at,finished_at) END) avg_service
                  FROM tickets WHERE $where", $args) ?: [];
        json_out(['ok' => true, 'from' => $from, 'to' => $to, 'stats' => [
            'total' => (int)($k['total'] ?? 0), 'served' => (int)($k['served'] ?? 0),
            'no_show' => (int)($k['no_show'] ?? 0), 'cancelled' => (int)($k['cancelled'] ?? 0),
            'avg_wait_sec' => (int) round((float)($k['avg_wait'] ?? 0)),
            'avg_service_sec' => (int) round((float)($k['avg_service'] ?? 0)),
        ]]);
    }

    json_out(['ok' => false, 'error' => 'Endpoint inexistent'], 404);
}
