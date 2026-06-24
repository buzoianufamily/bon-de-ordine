<?php
/**
 * Panou "landlord" — administrarea instantelor clientilor (multi-tenant).
 * Acces: /landlord pe domeniul principal (NU pe subdomeniile clientilor),
 * protejat cu parola din config/config.php ('landlord_pass').
 * Registrul instantelor sta in config/tenants.json (host -> baza de date).
 * Nu depinde de nicio baza de date: functioneaza si cand una dintre ele e picata.
 */

function landlord_tenants_save(array $tenants): bool {
    $json = json_encode(['tenants' => array_values($tenants)],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return @file_put_contents(qms_tenants_file(), $json, LOCK_EX) !== false;
}

/** Verifica sanatatea unei instante: conexiune + cativa indicatori cheie. */
function landlord_health(array $db): array {
    $out = ['ok' => false, 'error' => '', 'brand' => '', 'schema' => 0,
            'users' => null, 'dev_total' => null, 'dev_online' => null,
            'tickets_today' => null, 'last_ticket' => null];
    if (empty($db['name']) || empty($db['user'])) { $out['error'] = 'Date DB incomplete'; return $out; }
    try {
        $dsn = "mysql:host=" . ($db['host'] ?? 'localhost') . ";dbname={$db['name']};charset=" . ($db['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $db['user'], $db['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3,
        ]);
        $v = function (string $sql) use ($pdo) {
            try { $r = $pdo->query($sql)->fetchColumn(); return $r === false ? null : $r; }
            catch (Throwable $e) { return null; }
        };
        $out['ok']            = true;
        $out['brand']         = (string)($v("SELECT v FROM settings WHERE k='brand_name'") ?? '');
        $out['schema']        = (int)($v("SELECT v FROM settings WHERE k='schema_version'") ?? 0);
        $out['users']         = $v("SELECT COUNT(*) FROM users WHERE active=1");
        $out['dev_total']     = $v("SELECT COUNT(*) FROM devices");
        $out['dev_online']    = $v("SELECT COUNT(*) FROM devices WHERE last_seen IS NOT NULL AND last_seen > (NOW() - INTERVAL 2 MINUTE)");
        $out['tickets_today'] = $v("SELECT COUNT(*) FROM tickets WHERE DATE(issued_at)=CURDATE()");
        $out['last_ticket']   = $v("SELECT MAX(issued_at) FROM tickets");
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }
    return $out;
}

function landlord_dispatch(array $seg, string $method): void {
    // ascuns complet pe instantele clientilor
    if (!empty($GLOBALS['__tenant'])) { http_response_code(404); echo 'Pagina nu a fost gasita.'; return; }

    $pass = (string) cfg('landlord_pass', '');
    if ($pass === '') {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;max-width:620px;margin:4rem auto;padding:0 1rem;line-height:1.6">'
           . '<h2>Panoul landlord este dezactivat</h2>'
           . '<p>Pentru a-l activa, adauga in <code>config/config.php</code> o parola puternica:</p>'
           . '<pre style="background:#0f1115;color:#7CFFB2;padding:1rem;border-radius:8px">\'landlord_pass\' => \'parola-ta-foarte-lunga\',</pre>'
           . '<p>Apoi revino la <code>/landlord</code>.</p>';
        return;
    }

    $a = $seg[1] ?? '';
    if ($a === 'logout') { unset($_SESSION['landlord_ok']); redirect('landlord'); }

    // ---- autentificare separata de conturile instantelor ----
    if (empty($_SESSION['landlord_ok'])) {
        if ($method === 'POST') {
            csrf_check();
            $_SESSION['ll_tries'] = (int)($_SESSION['ll_tries'] ?? 0);
            if ($_SESSION['ll_tries'] >= 8) sleep(2);                       // incetineste brute-force
            if (hash_equals($pass, (string)($_POST['password'] ?? ''))) {
                session_regenerate_id(true);
                $_SESSION['landlord_ok'] = 1; $_SESSION['ll_tries'] = 0;
                redirect('landlord');
            }
            $_SESSION['ll_tries']++;
            flash('Parola incorecta.', 'error');
            redirect('landlord');
        }
        view('landlord/login');
        return;
    }

    $tenants = qms_tenants_load();

    if ($method === 'POST') {
        csrf_check();

        if ($a === 'save') {
            $orig = strtolower(trim((string)($_POST['orig'] ?? '')));
            $host = strtolower(trim((string)($_POST['host'] ?? '')));
            $host = preg_replace('#^https?://#', '', $host);
            $host = preg_replace('#[/?].*$#', '', $host);
            if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $host)) { flash('Host invalid. Exemplu: client1.domeniu.ro', 'error'); redirect('landlord'); }
            $dbName = trim((string)($_POST['db_name'] ?? '')); $dbUser = trim((string)($_POST['db_user'] ?? ''));
            if ($dbName === '' || $dbUser === '') { flash('Completeaza numele bazei de date si utilizatorul.', 'error'); redirect('landlord'); }
            // host duplicat la alta instanta?
            foreach ($tenants as $t) {
                if (strtolower((string)$t['host']) === $host && strtolower((string)$t['host']) !== $orig) {
                    flash('Exista deja o instanta pe acest host.', 'error'); redirect('landlord');
                }
            }
            $prev = null;
            foreach ($tenants as $t) if (strtolower((string)$t['host']) === ($orig ?: $host)) { $prev = $t; break; }
            $puIn = trim((string)($_POST['paid_until'] ?? ''));
            $entry = [
                'host'    => $host,
                'name'    => trim((string)($_POST['name'] ?? '')),
                'db'      => ['host' => trim((string)($_POST['db_host'] ?? '')) ?: 'localhost',
                              'name' => $dbName, 'user' => $dbUser,
                              'pass' => (string)($_POST['db_pass'] ?? ''), 'charset' => 'utf8mb4'],
                'active'      => isset($_POST['active']),
                'paid_until'  => preg_match('/^\d{4}-\d{2}-\d{2}$/', $puIn) ? $puIn : '',  // abonament: data pana la care e platit
                'grace_days'  => max(0, min(90, (int)($_POST['grace_days'] ?? 0))),         // zile de gratie dupa expirare
                'limits'  => ['branches' => max(0, (int)($_POST['lim_branches'] ?? 0)),     // limite de plan (0 = nelimitat)
                              'counters' => max(0, (int)($_POST['lim_counters'] ?? 0)),
                              'users'    => max(0, (int)($_POST['lim_users'] ?? 0)),
                              'services' => max(0, (int)($_POST['lim_services'] ?? 0))],
                'note'    => trim((string)($_POST['note'] ?? '')),
                'created' => $prev['created'] ?? date('Y-m-d'),
            ];
            // inlocuieste sau adauga
            $tenants = array_values(array_filter($tenants, fn($t) => strtolower((string)$t['host']) !== ($orig ?: $host)));
            $tenants[] = $entry;
            usort($tenants, fn($x, $y) => strcmp($x['host'], $y['host']));
            if (!landlord_tenants_save($tenants)) { flash('Nu am putut scrie config/tenants.json — verifica permisiunile folderului config/.', 'error'); redirect('landlord'); }
            $h = landlord_health($entry['db']);
            flash('Instanta salvata.' . ($h['ok'] ? ' Conexiunea la baza de date functioneaza ✔' : ' ATENTIE: conexiunea DB a esuat — ' . $h['error']), $h['ok'] ? 'info' : 'error');
            redirect('landlord');
        }

        if ($a === 'toggle') {
            $host = strtolower(trim((string)($_POST['host'] ?? '')));
            foreach ($tenants as &$t) if (strtolower((string)$t['host']) === $host) { $t['active'] = empty($t['active']); break; }
            unset($t);
            landlord_tenants_save($tenants);
            flash('Stare schimbata.');
            redirect('landlord');
        }

        if ($a === 'delete') {
            $host = strtolower(trim((string)($_POST['host'] ?? '')));
            $tenants = array_values(array_filter($tenants, fn($t) => strtolower((string)$t['host']) !== $host));
            landlord_tenants_save($tenants);
            flash('Instanta scoasa din registru (baza de date NU a fost stearsa).');
            redirect('landlord');
        }

        redirect('landlord');
    }

    // ---- dashboard: instanta principala + toate instantele, cu health-check ----
    $rows = [];
    $rows[] = ['main' => true, 'host' => preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''),
               'name' => '(instanta principala)', 'active' => true, 'state' => 'ok', 'paid_until' => '', 'note' => '',
               'db' => cfg('db'), 'health' => landlord_health(cfg('db'))];
    foreach ($tenants as $t) {
        $st = function_exists('bdo_tenant_state') ? bdo_tenant_state($t, time()) : (!empty($t['active']) ? 'ok' : 'suspended');
        $rows[] = ['main' => false, 'host' => $t['host'], 'name' => $t['name'] ?? '',
                   'active' => !empty($t['active']), 'state' => $st,
                   'paid_until' => (string)($t['paid_until'] ?? ''), 'grace_days' => (int)($t['grace_days'] ?? 0),
                   'note' => $t['note'] ?? '', 'db' => $t['db'] ?? [],
                   'health' => $st === 'ok' ? landlord_health($t['db'] ?? []) : null];
    }
    // pre-completare formular la editare (?edit=host)
    $edit = null;
    $editHost = strtolower(trim((string)($_GET['edit'] ?? '')));
    if ($editHost !== '') foreach ($tenants as $t) if (strtolower((string)$t['host']) === $editHost) { $edit = $t; break; }

    view('landlord/dashboard', ['rows' => $rows, 'edit' => $edit, 'writable' => is_writable(APP_ROOT . '/config') || is_file(qms_tenants_file()) && is_writable(qms_tenants_file())]);
}
