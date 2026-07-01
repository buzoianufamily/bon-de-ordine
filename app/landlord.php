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

/* ----------------------- Facturare (landlord, fara DB: in fisiere JSON) ----------------------- */
function landlord_billing_file(): string { return $GLOBALS['__billing_file'] ?? (APP_ROOT . '/config/billing.json'); }
function landlord_invoices_file(): string { return $GLOBALS['__invoices_file'] ?? (APP_ROOT . '/config/invoices.json'); }
/** Datele emitentului (firma ta). */
function landlord_billing_load(): array {
    $j = json_decode((string)@file_get_contents(landlord_billing_file()), true);
    return is_array($j) ? $j : [];
}
function landlord_billing_save(array $b): bool {
    return @file_put_contents(landlord_billing_file(),
        json_encode($b, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}
/** Lista facturilor emise. */
function landlord_invoices_load(): array {
    $j = json_decode((string)@file_get_contents(landlord_invoices_file()), true);
    return is_array($j['invoices'] ?? null) ? $j['invoices'] : [];
}
function landlord_invoices_save(array $inv): bool {
    return @file_put_contents(landlord_invoices_file(),
        json_encode(['invoices' => array_values($inv)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}
/** Totalul unei facturi: net + TVA. Returneaza ['net','vat','total']. */
function landlord_invoice_total(array $inv): array {
    $net = round((float)($inv['amount'] ?? 0), 2);
    $vatp = max(0.0, (float)($inv['vat_percent'] ?? 0));
    $vat = round($net * $vatp / 100, 2);
    return ['net' => $net, 'vat' => $vat, 'total' => round($net + $vat, 2)];
}
/**
 * Urmatorul numar pentru o serie + an, separat pe tip (factura fiscala vs proforma)
 * ca seria fiscala sa ramana fara goluri (cerinta legala RO). Proformele au pool propriu.
 */
function landlord_next_invoice_number(array $invoices, string $series, int $year, bool $proforma = false): int {
    $max = 0;
    foreach ($invoices as $iv)
        if (($iv['series'] ?? '') === $series && (int)($iv['year'] ?? 0) === $year
            && (bool)($iv['proforma'] ?? false) === $proforma)
            $max = max($max, (int)($iv['number'] ?? 0));
    return $max + 1;
}
/** Cheia pragului de numerotare (high-water) pe serie+an+tip — nu coboara la stergere. */
function landlord_seq_key(string $series, int $year, bool $proforma): string {
    return $series . '|' . $year . '|' . ($proforma ? 'P' : 'F');
}
/**
 * Numarul de atribuit: max(urmatorul din lista, pragul high-water + 1). Pragul (billing['seq'])
 * nu coboara cand se sterge o factura, deci doua facturi distincte nu pot primi acelasi numar.
 */
function landlord_assign_number(array $invoices, array $billing, string $series, int $year, bool $proforma): int {
    $key = landlord_seq_key($series, $year, $proforma);
    return max(landlord_next_invoice_number($invoices, $series, $year, $proforma),
               (int)($billing['seq'][$key] ?? 0) + 1);
}
/** Executa o sectiune critica sub lock exclusiv (serializeaza scrierile concurente in JSON). */
function landlord_with_lock(callable $fn) {
    $lf = @fopen(landlord_invoices_file() . '.lock', 'c');
    if ($lf) flock($lf, LOCK_EX);
    try { return $fn(); }
    finally { if ($lf) { flock($lf, LOCK_UN); fclose($lf); } }
}
/** Eticheta afisata a unei facturi, ex: BDO 00007 / 2026 (PF = proforma). */
function landlord_invoice_label(array $inv): string {
    return ($inv['proforma'] ?? false ? 'PF ' : '') . ($inv['series'] ?? '') . ' '
         . sprintf('%05d', (int)($inv['number'] ?? 0)) . ' / ' . (int)($inv['year'] ?? 0);
}

/** Verifica sanatatea unei instante: conexiune + cativa indicatori cheie. */
function landlord_health(array $db): array {
    $out = ['ok' => false, 'error' => '', 'brand' => '', 'schema' => 0,
            'users' => null, 'dev_total' => null, 'dev_online' => null,
            'tickets_today' => null, 'last_ticket' => null,
            'cron_token' => '', 'checks' => [], 'checks_crit' => 0, 'checks_warn' => 0];
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
        $out['cron_token']    = (string)($v("SELECT v FROM settings WHERE k='cron_token'") ?? '');
        // verificari „pregatit de productie" derivate din DB (per instanta) — vizibile doar in landlord
        $out['checks'] = landlord_instance_checks($pdo);
        foreach ($out['checks'] as $c) { if ($c['level'] === 'crit') $out['checks_crit']++; elseif ($c['level'] === 'warn') $out['checks_warn']++; }
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }
    return $out;
}

/**
 * Verificari de pregatire pentru productie, calculate DIN baza de date a unei instante
 * (nu depind de request-ul curent). Returneaza [level,title,detail], level in ok|warn|crit.
 * Complementeaza landlord_health() — panoul „Verificare" per instanta din landlord.
 */
function landlord_instance_checks(PDO $pdo): array {
    $checks = [];
    $add = function (string $level, string $title, string $detail) use (&$checks) { $checks[] = compact('level', 'title', 'detail'); };
    $val = function (string $sql) use ($pdo) {
        try { $r = $pdo->query($sql)->fetchColumn(); return $r === false ? null : $r; }
        catch (Throwable $e) { return null; }
    };
    // setari citite in bloc
    $s = [];
    try {
        $st = $pdo->query("SELECT k,v FROM settings WHERE k IN ('retention_months','legal_operator','legal_email',"
            . "'backup_auto_enabled','cron_last_run','webhook_url','webhook_secret','mail_enabled','smtp_host')");
        foreach ($st->fetchAll(PDO::FETCH_NUM) as $r) $s[$r[0]] = $r[1];
    } catch (Throwable $e) {}

    // 1) parola de admin implicita
    $defPw = (int)($val("SELECT COUNT(*) FROM users WHERE role='admin' AND must_change_pw=1") ?? 0);
    $defPw > 0
        ? $add('crit', 'Parola admin implicita', "$defPw cont(uri) de admin inca folosesc parola implicita.")
        : $add('ok', 'Parole admin', 'Niciun admin nu mai foloseste parola implicita.');

    // 2) 2FA pe conturile de admin
    $admins  = (int)($val("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1") ?? 0);
    $with2fa = (int)($val("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1 AND totp_enabled=1") ?? 0);
    ($admins > 0 && $with2fa < $admins)
        ? $add('warn', 'Autentificare in doi pasi', "$with2fa din $admins admini au 2FA activ.")
        : $add('ok', 'Autentificare in doi pasi', 'Toti adminii activi au 2FA.');

    // 3) email configurat
    (($s['mail_enabled'] ?? '0') === '1' && trim((string)($s['smtp_host'] ?? '')) !== '')
        ? $add('ok', 'Email', 'Trimiterea de emailuri este configurata.')
        : $add('warn', 'Email', 'Emailul nu este configurat (reset parola, remindere, rapoarte nu functioneaza).');

    // 4) backup automat
    (($s['backup_auto_enabled'] ?? '0') === '1')
        ? $add('ok', 'Backup automat', 'Backup-ul automat zilnic este activ.')
        : $add('warn', 'Backup automat', 'Backup-ul automat este oprit (necesita cron).');

    // 5) cron ruleaza?
    $last = (int)($s['cron_last_run'] ?? 0);
    if ($last === 0) $add('warn', 'Cron', 'Cron-ul nu a rulat niciodata. Configureaza-l (vezi mai jos).');
    elseif (time() - $last > 7200) $add('warn', 'Cron', 'Cron-ul nu a mai rulat de peste 2 ore (ultima: ' . date('d.m.Y H:i', $last) . ').');
    else $add('ok', 'Cron', 'Cron-ul a rulat recent (' . date('d.m.Y H:i', $last) . ').');

    // 6) webhook fara secret
    if (trim((string)($s['webhook_url'] ?? '')) !== '' && trim((string)($s['webhook_secret'] ?? '')) === '')
        $add('warn', 'Webhook fara secret', 'URL de webhook fara secret de semnatura.');

    // 7) retentie date (GDPR)
    ((int)($s['retention_months'] ?? 0) > 0)
        ? $add('ok', 'Retentie date', 'Datele vechi se sterg automat.')
        : $add('warn', 'Retentie date', 'Nu este setata stergerea automata a datelor vechi (GDPR).');

    // 8) date operator pentru paginile legale
    (trim((string)($s['legal_operator'] ?? '')) !== '' || trim((string)($s['legal_email'] ?? '')) !== '')
        ? $add('ok', 'Date legale', 'Datele operatorului pentru paginile legale sunt completate.')
        : $add('warn', 'Date legale', 'Completeaza datele operatorului (pagini confidentialitate/termeni).');

    return $checks;
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

        // ---- facturare ----
        if ($a === 'billing-settings') {
            $b = [
                'name'    => trim((string)($_POST['b_name'] ?? '')),
                'cui'     => trim((string)($_POST['b_cui'] ?? '')),
                'regcom'  => trim((string)($_POST['b_regcom'] ?? '')),
                'address' => trim((string)($_POST['b_address'] ?? '')),
                'iban'    => trim((string)($_POST['b_iban'] ?? '')),
                'bank'    => trim((string)($_POST['b_bank'] ?? '')),
                'email'   => trim((string)($_POST['b_email'] ?? '')),
                'series'  => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['b_series'] ?? 'BDO'))) ?: 'BDO',
                'currency'=> strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_POST['b_currency'] ?? 'RON'))) ?: 'RON',
                'vat_percent' => max(0, min(100, (int)($_POST['b_vat'] ?? 0))),
            ];
            landlord_billing_save($b);
            flash('Datele de facturare au fost salvate.');
            redirect('landlord/billing');
        }

        if ($a === 'invoice-save') {
            $billing = landlord_billing_load();
            if (empty($billing['name'])) { flash('Completeaza intai datele emitentului (firma ta).', 'error'); redirect('landlord/billing'); }
            if (trim((string)($_POST['client_name'] ?? '')) === '') { flash('Completeaza numele clientului pe factura.', 'error'); redirect('landlord/billing'); }
            $series = (string)($billing['series'] ?? 'BDO');
            $year = (int) date('Y');
            $isPro = isset($_POST['proforma']);
            $amount = max(0, (float)str_replace(',', '.', (string)($_POST['amount'] ?? 0)));
            $pf = trim((string)($_POST['period_from'] ?? '')); $pt = trim((string)($_POST['period_to'] ?? ''));
            $due = (string)($_POST['due_date'] ?? '');
            // sectiune critica: calcul numar + scriere, sub lock (evita numere duplicate la cereri concurente)
            $inv = landlord_with_lock(function () use ($series, $year, $isPro, $amount, $pf, $pt, $due, $billing) {
                $invoices = landlord_invoices_load();
                $key = landlord_seq_key($series, $year, $isPro);
                $num = landlord_assign_number($invoices, $billing, $series, $year, $isPro);  // high-water: fara reutilizare
                $inv = [
                    'id'      => bin2hex(random_bytes(8)),
                    'host'    => strtolower(trim((string)($_POST['host'] ?? ''))),
                    'series'  => $series, 'number' => $num, 'year' => $year,
                    'date'    => date('Y-m-d'),
                    'due_date'=> preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : date('Y-m-d', strtotime('+15 days')),
                    'client_name'    => trim((string)($_POST['client_name'] ?? '')),
                    'client_cui'     => trim((string)($_POST['client_cui'] ?? '')),
                    'client_address' => trim((string)($_POST['client_address'] ?? '')),
                    'description' => trim((string)($_POST['description'] ?? '')) ?: 'Abonament Bon de ordine',
                    'period_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $pf) ? $pf : '',
                    'period_to'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $pt) ? $pt : '',
                    'amount'  => round($amount, 2),
                    'vat_percent' => max(0, min(100, (int)($_POST['vat_percent'] ?? ($billing['vat_percent'] ?? 0)))),
                    'currency'=> (string)($billing['currency'] ?? 'RON'),
                    'proforma'=> $isPro,
                    'paid_at' => '',
                    'note'    => trim((string)($_POST['inv_note'] ?? '')),
                ];
                $invoices[] = $inv;
                if (!landlord_invoices_save($invoices)) return null;
                $billing['seq'][$key] = $num;                  // urca pragul (persista chiar daca factura e stearsa)
                landlord_billing_save($billing);
                return $inv;
            });
            if (!$inv) { flash('Nu am putut scrie config/invoices.json — verifica permisiunile folderului config/.', 'error'); redirect('landlord/billing'); }
            flash('Factura ' . landlord_invoice_label($inv) . ' a fost creata.');
            redirect('landlord/invoice?id=' . $inv['id']);
        }

        if ($a === 'invoice-paid') {
            $id = (string)($_POST['id'] ?? '');
            $invoices = landlord_invoices_load();
            foreach ($invoices as &$iv) if (($iv['id'] ?? '') === $id) { $iv['paid_at'] = ($iv['paid_at'] ?? '') ? '' : date('Y-m-d'); break; }
            unset($iv);
            landlord_invoices_save($invoices);
            flash('Stare plata actualizata.');
            redirect('landlord/billing');
        }

        if ($a === 'invoice-delete') {
            $id = (string)($_POST['id'] ?? '');
            $invoices = array_values(array_filter(landlord_invoices_load(), fn($iv) => ($iv['id'] ?? '') !== $id));
            landlord_invoices_save($invoices);
            flash('Factura a fost stearsa.');
            redirect('landlord/billing');
        }

        redirect('landlord');
    }

    // ---- facturare: pagina de gestiune + factura printabila (GET) ----
    if ($a === 'billing') {
        view('landlord/billing', [
            'billing'  => landlord_billing_load(),
            'invoices' => landlord_invoices_load(),
            'tenants'  => $tenants,
            'prefillHost' => strtolower(trim((string)($_GET['host'] ?? ''))),
        ]);
        return;
    }
    if ($a === 'invoice') {
        $id = (string)($_GET['id'] ?? '');
        $inv = null;
        foreach (landlord_invoices_load() as $iv) if (($iv['id'] ?? '') === $id) { $inv = $iv; break; }
        if (!$inv) { http_response_code(404); echo 'Factura inexistenta.'; return; }
        view('landlord/invoice', ['inv' => $inv, 'billing' => landlord_billing_load()]);
        return;
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
