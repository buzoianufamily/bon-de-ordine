<?php
/**
 * API public v1 — autentificat cu cheie API (X-Api-Key sau ?key=).
 * Rute sub /api/v1/...  (vezi pagina Administrare → API pentru documentatie).
 */
function api_v1(array $seg, string $method): void {
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

    if ($method === 'OPTIONS') { http_response_code(204); exit; }

    $key  = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
    $real = (string) setting('api_key', '');
    if ($real === '' || !hash_equals($real, (string)$key)) {
        json_out(['ok' => false, 'error' => 'Cheie API invalida sau lipsa'], 401);
    }

    $res = $seg[2] ?? '';
    $arg = $seg[3] ?? null;

    // GET /api/v1/state?branch=ID — starea cozii
    if ($res === 'state' && $method === 'GET') {
        $branch = (int)($_GET['branch'] ?? 1);
        json_out(['ok' => true] + queue_state($branch));
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
