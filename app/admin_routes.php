<?php
/** Rutare si logica pentru zona de administrare (backoffice). */

function admin_dispatch(array $seg, string $method): void {
    $res = $seg[1] ?? 'dashboard';
    $a   = $seg[2] ?? null;            // id sau sub-actiune
    $b   = $seg[3] ?? null;            // sub-actiune (ex: delete)

    // ---- permisiuni: dashboard mereu permis; restul dupa rol ----
    $area = $res ?: 'dashboard';
    if (!in_array($area, ['dashboard',''], true)) {
        if ($area === 'roles') { if (current_user()['role'] !== 'admin') { http_response_code(403); echo 'Acces interzis.'; return; } }
        elseif (in_array($area, array_keys(perm_areas()), true) && !can($area)) { flash('Nu ai acces la sectiunea respectiva.', 'error'); redirect('admin'); }
    }

    switch ($res) {
        case 'dashboard': case '': admin_dashboard(); return;

        case 'statistics': admin_statistics(); return;

        case 'branches':
            if ($method === 'POST' && $a === null) { admin_branch_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check();
                if ((int)val('SELECT COUNT(*) FROM branches') > 1) { q('DELETE FROM branches WHERE id=?', [(int)$a]); flash('Filiala stearsa.'); }
                else flash('Nu poti sterge ultima filiala.', 'error');
                redirect('admin/branches'); }
            if ($a === 'new') { admin_branch_form(null); return; }
            if (ctype_digit((string)$a) && $b === 'edit') { admin_branch_form((int)$a); return; }
            if (ctype_digit((string)$a)) { admin_branch_detail((int)$a); return; }
            admin_branches_list(); return;

        case 'services':
            if ($method === 'POST' && $a === null) { admin_service_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check(); q('DELETE FROM services WHERE id=?', [(int)$a]); flash('Serviciu sters.'); redirect('admin/services'); }
            if ($a === 'new') { admin_service_form(null); return; }
            if (ctype_digit((string)$a)) { admin_service_form((int)$a); return; }
            admin_services_list(); return;

        case 'counters':
            if ($method === 'POST' && $a === null) { admin_counter_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check(); q('DELETE FROM counters WHERE id=?', [(int)$a]); flash('Ghiseu sters.'); redirect('admin/counters'); }
            if ($a === 'new') { admin_counter_form(null); return; }
            if (ctype_digit((string)$a)) { admin_counter_form((int)$a); return; }
            admin_counters_list(); return;

        case 'users':
            if ($method === 'POST' && $a === null) { admin_user_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check(); q('DELETE FROM users WHERE id=? AND id<>?', [(int)$a, current_user()['id']]); flash('Utilizator sters.'); redirect('admin/users'); }
            if ($a === 'new') { admin_user_form(null); return; }
            if (ctype_digit((string)$a)) { admin_user_form((int)$a); return; }
            admin_users_list(); return;

        case 'devices':
            if ($method === 'POST' && $a === null) { admin_device_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check(); q('DELETE FROM devices WHERE id=?', [(int)$a]); flash('Dispozitiv sters.'); redirect('admin/devices'); }
            if (ctype_digit((string)$a) && $b === 'player') {   // editor canvas afisaj
                if ($method === 'POST') { admin_player_save((int)$a); return; }
                admin_player_builder((int)$a); return;
            }
            if (ctype_digit((string)$a) && $b === 'dispenser') { // editor configurare dispenser
                if ($method === 'POST') { admin_dispenser_save((int)$a); return; }
                admin_dispenser_builder((int)$a); return;
            }
            if ($a === 'new') { admin_device_form(null); return; }
            if (ctype_digit((string)$a)) { admin_device_form((int)$a); return; }
            admin_devices_list(); return;

        case 'tickets':  admin_tickets(); return;

        case 'appointments':
            if ($method === 'POST' && $a === null) { admin_appointment_create(); return; }
            if ($method === 'POST' && $b === 'checkin') { admin_appointment_action((int)$a, 'checkin'); return; }
            if ($method === 'POST' && $b === 'cancel')  { admin_appointment_action((int)$a, 'cancel'); return; }
            admin_appointments_list(); return;

        case 'media':
            if ($method === 'POST' && $a === 'upload') { admin_media_upload(); return; }
            if ($method === 'POST' && $b === 'delete') { admin_media_delete((int)$a); return; }
            admin_media_list(); return;

        case 'forms':
            if ($method === 'POST' && $a === null) { admin_form_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check();
                q('DELETE FROM forms WHERE id=?', [(int)$a]); q('UPDATE services SET form_id=NULL WHERE form_id=?', [(int)$a]);
                flash('Formular sters.'); redirect('admin/forms'); }
            if ($a === 'new') { admin_form_builder(null); return; }
            if (ctype_digit((string)$a)) { admin_form_builder((int)$a); return; }
            admin_forms_list(); return;

        case 'settings':
            if ($method === 'POST') { admin_settings_save(); return; }
            admin_settings_form(); return;

        case 'roles':
            if ($method === 'POST') { csrf_check();
                $m = []; foreach (array_keys(perm_areas()) as $ar) $m[$ar] = isset($_POST['manager'][$ar]);
                set_setting('role_perms', json_encode(['manager'=>$m], JSON_UNESCAPED_UNICODE));
                flash('Permisiuni salvate.'); redirect('admin/roles'); }
            admin_roles(); return;
    }
    http_response_code(404); echo 'Sectiune inexistenta.';
}

/* ----------------------- DASHBOARD ----------------------- */
function admin_dashboard(): void {
    $today = date('Y-m-d');
    $stats = [
        'today'   => (int) val('SELECT COUNT(*) FROM tickets WHERE DATE(issued_at)=?', [$today]),
        'waiting' => (int) val('SELECT COUNT(*) FROM tickets WHERE status="waiting"'),
        'serving' => (int) val('SELECT COUNT(*) FROM tickets WHERE status IN ("called","serving")'),
        'served'  => (int) val('SELECT COUNT(*) FROM tickets WHERE status="served" AND DATE(issued_at)=?', [$today]),
        'avg_wait'=> (int) (val('SELECT AVG(TIMESTAMPDIFF(SECOND, issued_at, called_at)) FROM tickets WHERE called_at IS NOT NULL AND DATE(issued_at)=?', [$today]) ?? 0),
    ];
    $per_service = all('SELECT s.name, s.color, COUNT(t.id) cnt
        FROM services s LEFT JOIN tickets t ON t.service_id=s.id AND DATE(t.issued_at)=?
        GROUP BY s.id ORDER BY s.sort_order', [$today]);
    $devices = all('SELECT name, type, connection_key, last_seen,
        (last_seen IS NOT NULL AND last_seen > (NOW() - INTERVAL 2 MINUTE)) AS online FROM devices ORDER BY type');
    view('admin/dashboard', compact('stats','per_service','devices'));
}

/* ----------------------- SERVICES ----------------------- */
function admin_services_list(): void {
    $rows = all('SELECT s.*, b.name AS branch_name FROM services s JOIN branches b ON b.id=s.branch_id ORDER BY b.name, s.sort_order, s.id');
    view('admin/services', ['rows' => $rows]);
}
function admin_service_form(?int $id): void {
    $row = $id ? one('SELECT * FROM services WHERE id=?', [$id]) : null;
    $branches = all('SELECT id, name FROM branches ORDER BY name');
    $forms = all('SELECT id, name FROM forms ORDER BY name');
    view('admin/service_edit', ['row' => $row, 'branches' => $branches, 'forms' => $forms]);
}
function admin_service_save(): void {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    // program de functionare (active_hours JSON)
    $ah = '';
    if (isset($_POST['sched_enabled'])) {
        $days = [];
        foreach ([1,2,3,4,5,6,0] as $d) {
            if (isset($_POST["day_{$d}_open"])) {
                $fr = trim((string)($_POST["day_{$d}_from"] ?? '')); $tt = trim((string)($_POST["day_{$d}_to"] ?? ''));
                if ($fr && $tt) $days[(string)$d] = [$fr, $tt];
            }
        }
        $ah = json_encode(['enabled'=>true,'days'=>$days], JSON_UNESCAPED_UNICODE);
    }
    $f = [
        'branch_id'=>(int)($_POST['branch_id'] ?? 1), 'prefix'=>strtoupper(trim($_POST['prefix'] ?? 'A')),
        'name'=>trim($_POST['name'] ?? ''), 'abbreviation'=>trim($_POST['abbreviation'] ?? ''),
        'description'=>trim($_POST['description'] ?? ''), 'color'=>trim($_POST['color'] ?? '#2563eb'),
        'status'=>($_POST['status'] ?? 'active'), 'num_from'=>(int)($_POST['num_from'] ?? 1),
        'num_to'=>(int)($_POST['num_to'] ?? 999), 'pad_length'=>(int)($_POST['pad_length'] ?? 3),
        'include_zeros'=>isset($_POST['include_zeros'])?1:0, 'allow_priority'=>isset($_POST['allow_priority'])?1:0,
        'terminate_on_call'=>isset($_POST['terminate_on_call'])?1:0,
        'kpi_wait_sec'=>(int)($_POST['kpi_wait_sec'] ?? 600), 'kpi_service_sec'=>(int)($_POST['kpi_service_sec'] ?? 300),
        'max_queued'=>(int)($_POST['max_queued'] ?? 0), 'sort_order'=>(int)($_POST['sort_order'] ?? 0),
        'active_hours'=>$ah,
        'form_id'=>((int)($_POST['form_id'] ?? 0)) ?: null,
        'appt_enabled'=>isset($_POST['appt_enabled'])?1:0,
        'appt_slot_min'=>max(5,(int)($_POST['appt_slot_min'] ?? 15)),
        'appt_capacity'=>max(1,(int)($_POST['appt_capacity'] ?? 1)),
    ];
    if ($f['name'] === '' || $f['prefix'] === '') { flash('Nume si prefix obligatorii.', 'error'); redirect('admin/services' . ($id ? "/$id" : '/new')); }
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($f)));
        q("UPDATE services SET $set WHERE id=?", array_merge(array_values($f), [$id]));
        flash('Serviciu actualizat.');
    } else {
        $cols = implode(',', array_keys($f)); $ph = implode(',', array_fill(0, count($f), '?'));
        q("INSERT INTO services ($cols) VALUES ($ph)", array_values($f));
        flash('Serviciu creat.');
    }
    redirect('admin/services');
}

/* ----------------------- COUNTERS ----------------------- */
function admin_counters_list(): void {
    $rows = all('SELECT c.*, b.name AS branch_name, (SELECT COUNT(*) FROM counter_services cs WHERE cs.counter_id=c.id) svc_count FROM counters c JOIN branches b ON b.id=c.branch_id ORDER BY b.name, c.code');
    view('admin/counters', ['rows' => $rows]);
}
function admin_counter_form(?int $id): void {
    $row = $id ? one('SELECT * FROM counters WHERE id=?', [$id]) : null;
    $branches = all('SELECT id,name FROM branches ORDER BY name');
    $services = all('SELECT id,prefix,name FROM services ORDER BY sort_order');
    $selected = $id ? array_column(all('SELECT service_id FROM counter_services WHERE counter_id=?', [$id]), 'service_id') : [];
    view('admin/counter_edit', compact('row','branches','services','selected'));
}
function admin_counter_save(): void {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $f = ['branch_id'=>(int)($_POST['branch_id'] ?? 1), 'code'=>trim($_POST['code'] ?? ''),
          'name'=>trim($_POST['name'] ?? ''), 'status'=>($_POST['status'] ?? 'closed'),
          'all_services'=>isset($_POST['all_services'])?1:0, 'priority'=>(int)($_POST['priority'] ?? 0)];
    if ($f['code'] === '' || $f['name'] === '') { flash('Cod si nume obligatorii.', 'error'); redirect('admin/counters'); }
    if ($id) {
        $set = implode(', ', array_map(fn($k)=>"$k=?", array_keys($f)));
        q("UPDATE counters SET $set WHERE id=?", array_merge(array_values($f), [$id]));
    } else {
        $cols = implode(',', array_keys($f)); $ph = implode(',', array_fill(0, count($f), '?'));
        q("INSERT INTO counters ($cols) VALUES ($ph)", array_values($f)); $id = insert_id();
    }
    q('DELETE FROM counter_services WHERE counter_id=?', [$id]);
    if (!$f['all_services']) foreach (($_POST['services'] ?? []) as $sid) q('INSERT IGNORE INTO counter_services (counter_id,service_id) VALUES (?,?)', [$id,(int)$sid]);
    flash('Ghiseu salvat.'); redirect('admin/counters');
}

/* ----------------------- USERS ----------------------- */
function admin_users_list(): void { view('admin/users', ['rows' => all('SELECT * FROM users ORDER BY name')]); }
function admin_user_form(?int $id): void {
    view('admin/user_edit', ['row' => $id ? one('SELECT * FROM users WHERE id=?', [$id]) : null]);
}
function admin_user_save(): void {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $role=$_POST['role']??'agent';
    $active=isset($_POST['active'])?1:0; $pass=(string)($_POST['password']??'');
    if ($name==='' || $email==='') { flash('Nume si email obligatorii.', 'error'); redirect('admin/users'); }
    if ($id) {
        if ($pass !== '') q('UPDATE users SET name=?,email=?,role=?,active=?,password_hash=? WHERE id=?',
            [$name,$email,$role,$active,password_hash($pass,PASSWORD_DEFAULT),$id]);
        else q('UPDATE users SET name=?,email=?,role=?,active=? WHERE id=?', [$name,$email,$role,$active,$id]);
    } else {
        if ($pass === '') { flash('Parola obligatorie la utilizator nou.', 'error'); redirect('admin/users/new'); }
        try { q('INSERT INTO users (name,email,role,active,password_hash) VALUES (?,?,?,?,?)',
            [$name,$email,$role,$active,password_hash($pass,PASSWORD_DEFAULT)]); }
        catch (Throwable $e) { flash('Email deja folosit.', 'error'); redirect('admin/users/new'); }
    }
    flash('Utilizator salvat.'); redirect('admin/users');
}

/* ----------------------- DEVICES ----------------------- */
function admin_devices_list(): void {
    $rows = all('SELECT d.*, b.name AS branch_name, (d.last_seen IS NOT NULL AND d.last_seen > (NOW() - INTERVAL 2 MINUTE)) AS online FROM devices d JOIN branches b ON b.id=d.branch_id ORDER BY b.name, d.type, d.name');
    view('admin/devices', ['rows' => $rows]);
}
function admin_device_form(?int $id): void {
    $row = $id ? one('SELECT * FROM devices WHERE id=?', [$id]) : null;
    $branches = all('SELECT id,name FROM branches ORDER BY name');
    $services = all('SELECT id,prefix,name FROM services ORDER BY sort_order');
    $selected = $id ? array_column(all('SELECT service_id FROM device_services WHERE device_id=?', [$id]), 'service_id') : [];
    view('admin/device_edit', compact('row','branches','services','selected'));
}
function admin_device_save(): void {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $f = ['branch_id'=>(int)($_POST['branch_id'] ?? 1), 'type'=>$_POST['type'] ?? 'dispenser',
          'name'=>trim($_POST['name'] ?? ''), 'all_services'=>isset($_POST['all_services'])?1:0,
          'printer_mode'=>$_POST['printer_mode'] ?? 'browser', 'printer_ip'=>trim($_POST['printer_ip'] ?? ''),
          'printer_port'=>(int)($_POST['printer_port'] ?? 9100)];
    if ($f['name'] === '') { flash('Nume obligatoriu.', 'error'); redirect('admin/devices'); }
    if ($id) {
        $set = implode(', ', array_map(fn($k)=>"$k=?", array_keys($f)));
        q("UPDATE devices SET $set WHERE id=?", array_merge(array_values($f), [$id]));
    } else {
        do { $key = gen_key(6); } while (one('SELECT id FROM devices WHERE connection_key=?', [$key]));
        $f['connection_key'] = $key;
        $cols = implode(',', array_keys($f)); $ph = implode(',', array_fill(0, count($f), '?'));
        q("INSERT INTO devices ($cols) VALUES ($ph)", array_values($f)); $id = insert_id();
    }
    q('DELETE FROM device_services WHERE device_id=?', [$id]);
    if (!$f['all_services']) foreach (($_POST['services'] ?? []) as $sid) q('INSERT IGNORE INTO device_services (device_id,service_id) VALUES (?,?)', [$id,(int)$sid]);
    flash('Dispozitiv salvat.'); redirect('admin/devices');
}

/* ----------------------- TICKETS ----------------------- */
function admin_tickets(): void {
    $date = $_GET['date'] ?? date('Y-m-d');
    $rows = all("SELECT t.*, s.name service_name, s.color, c.code counter_code
                 FROM tickets t JOIN services s ON s.id=t.service_id
                 LEFT JOIN counters c ON c.id=t.counter_id
                 WHERE DATE(t.issued_at)=? ORDER BY t.issued_at DESC LIMIT 500", [$date]);
    view('admin/tickets', compact('rows','date'));
}

/* ----------------------- SETTINGS ----------------------- */
function admin_settings_form(): void { view('admin/settings'); }
function admin_settings_save(): void {
    csrf_check();
    $keys = ['brand_name','accent_color','brand_logo','language','display_voice','display_repeat',
             'ticket_footer','dispenser_title','org_name'];
    foreach ($keys as $k) if (isset($_POST[$k])) set_setting($k, trim((string)$_POST[$k]));
    set_setting('display_say_number', isset($_POST['display_say_number']) ? '1' : '0');
    set_setting('display_say_counter', isset($_POST['display_say_counter']) ? '1' : '0');
    set_setting('virtual_enabled', isset($_POST['virtual_enabled']) ? '1' : '0');
    flash('Setari salvate.'); redirect('admin/settings');
}

/* ----------------------- PLAYER (editor afisaj canvas) ----------------------- */
function admin_player_builder(int $id): void {
    $dev = one('SELECT * FROM devices WHERE id=?', [$id]);
    if (!$dev) { http_response_code(404); echo 'Dispozitiv inexistent.'; return; }
    view('admin/player_builder', ['dev' => $dev]);
}
function admin_player_save(int $id): void {
    csrf_check();
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['screens'])) json_out(['ok'=>false,'error'=>'Layout invalid'], 422);
    q('UPDATE devices SET config=? WHERE id=?', [json_encode($data, JSON_UNESCAPED_UNICODE), $id]);
    json_out(['ok'=>true]);
}

/* ----------------------- BRANCHES (filiale) ----------------------- */
function admin_branches_list(): void {
    $rows = all('SELECT b.*,
        (SELECT COUNT(*) FROM services s WHERE s.branch_id=b.id) svc,
        (SELECT COUNT(*) FROM counters c WHERE c.branch_id=b.id) cnt,
        (SELECT COUNT(*) FROM devices d WHERE d.branch_id=b.id) dev
        FROM branches b ORDER BY b.name');
    view('admin/branches', ['rows' => $rows]);
}
function admin_branch_form(?int $id): void {
    $row = $id ? one('SELECT * FROM branches WHERE id=?', [$id]) : null;
    view('admin/branch_edit', ['row' => $row]);
}
function admin_branch_save(): void {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $f = ['name'=>trim($_POST['name'] ?? ''), 'city'=>trim($_POST['city'] ?? ''),
          'country'=>trim($_POST['country'] ?? 'Romania'), 'address'=>trim($_POST['address'] ?? ''),
          'timezone'=>trim($_POST['timezone'] ?? 'Europe/Bucharest'), 'active'=>isset($_POST['active'])?1:0];
    if ($f['name'] === '') { flash('Numele filialei este obligatoriu.', 'error'); redirect('admin/branches'); }
    if ($id) {
        $set = implode(', ', array_map(fn($k)=>"$k=?", array_keys($f)));
        q("UPDATE branches SET $set WHERE id=?", array_merge(array_values($f), [$id]));
    } else {
        $cols = implode(',', array_keys($f)); $ph = implode(',', array_fill(0, count($f), '?'));
        q("INSERT INTO branches ($cols) VALUES ($ph)", array_values($f));
    }
    flash('Filiala salvata.'); redirect('admin/branches');
}
function admin_branch_detail(int $id): void {
    $branch = one('SELECT * FROM branches WHERE id=?', [$id]);
    if (!$branch) { http_response_code(404); echo 'Filiala inexistenta.'; return; }
    $tab = $_GET['tab'] ?? 'services';
    $services = all('SELECT * FROM services WHERE branch_id=? ORDER BY sort_order,id', [$id]);
    $counters = all('SELECT c.*, (SELECT COUNT(*) FROM counter_services cs WHERE cs.counter_id=c.id) svc_count FROM counters c WHERE c.branch_id=? ORDER BY code', [$id]);
    $devices  = all('SELECT *, (last_seen IS NOT NULL AND last_seen > (NOW() - INTERVAL 2 MINUTE)) AS online FROM devices WHERE branch_id=? ORDER BY type,name', [$id]);
    view('admin/branch_detail', compact('branch','tab','services','counters','devices'));
}

/* ----------------------- MULTIMEDIA (galerie) ----------------------- */
function media_in_use(array $m): bool {
    $logo = (string) setting('brand_logo', '');
    if ($logo !== '' && str_contains($logo, $m['filename'])) return true;
    $n = (int) val('SELECT COUNT(*) FROM devices WHERE config LIKE ?', ['%'.$m['filename'].'%']);
    return $n > 0;
}
function admin_media_list(): void {
    $rows = all('SELECT * FROM media ORDER BY created_at DESC');
    foreach ($rows as &$m) { $m['url'] = url($m['path']); $m['in_use'] = media_in_use($m); }
    unset($m);
    if (($_GET['format'] ?? '') === 'json' || want_json()) {
        json_out(['ok' => true, 'media' => array_map(fn($m)=>[
            'id'=>(int)$m['id'],'url'=>$m['url'],'filename'=>$m['filename'],'mime'=>$m['mime']], $rows)]);
    }
    view('admin/media', ['rows' => $rows]);
}
function admin_media_upload(): void {
    csrf_check();
    if (empty($_FILES['file']) || !is_array($_FILES['file']['name'])) { flash('Niciun fisier selectat.', 'error'); redirect('admin/media'); }
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/svg+xml'=>'svg',
                'video/mp4'=>'mp4','video/webm'=>'webm'];
    $dir = APP_ROOT . '/assets/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $okCount = 0; $files = $_FILES['file'];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $tmp = $files['tmp_name'][$i];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: ($files['type'][$i] ?? '');
        if (!isset($allowed[$mime])) { flash('Tip de fisier neacceptat: '.e($mime), 'error'); continue; }
        if ($files['size'][$i] > 25 * 1024 * 1024) { flash('Fisier prea mare (max 25MB).', 'error'); continue; }
        $ext = $allowed[$mime];
        $base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($files['name'][$i], PATHINFO_FILENAME));
        $base = substr($base, 0, 40) ?: 'fisier';
        $fname = $base . '_' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
        if (@move_uploaded_file($tmp, $dir . '/' . $fname)) {
            q('INSERT INTO media (branch_id, filename, path, mime) VALUES (NULL, ?, ?, ?)',
              [$fname, 'assets/uploads/' . $fname, $mime]);
            $okCount++;
        }
    }
    if ($okCount) flash("$okCount fisier(e) incarcate.");
    redirect('admin/media');
}
function admin_media_delete(int $id): void {
    csrf_check();
    $m = one('SELECT * FROM media WHERE id=?', [$id]);
    if ($m) {
        $path = APP_ROOT . '/' . $m['path'];
        if (is_file($path)) @unlink($path);
        q('DELETE FROM media WHERE id=?', [$id]);
        flash('Fisier sters.');
    }
    redirect('admin/media');
}

/* ----------------------- DISPENSER (editor configurare) ----------------------- */
function admin_dispenser_builder(int $id): void {
    $dev = one('SELECT * FROM devices WHERE id=?', [$id]);
    if (!$dev) { http_response_code(404); echo 'Dispozitiv inexistent.'; return; }
    view('admin/dispenser_builder', ['dev' => $dev]);
}
function admin_dispenser_save(int $id): void {
    csrf_check();
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) json_out(['ok' => false, 'error' => 'Config invalid'], 422);
    q('UPDATE devices SET config=? WHERE id=?', [json_encode($data, JSON_UNESCAPED_UNICODE), $id]);
    json_out(['ok' => true]);
}

/* ----------------------- STATISTICS (rapoarte) ----------------------- */
function admin_statistics(): void {
    $from   = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));
    $to     = $_GET['to']   ?? date('Y-m-d');
    $branch = (int)($_GET['branch'] ?? 0);
    if (strtotime($from) > strtotime($to)) { [$from,$to] = [$to,$from]; }

    $where = 'DATE(t.issued_at) BETWEEN ? AND ?'; $args = [$from, $to];
    if ($branch) { $where .= ' AND t.branch_id = ?'; $args[] = $branch; }

    // export CSV (toate biletele din interval)
    if (($_GET['export'] ?? '') === 'csv') {
        $rows = all("SELECT t.label, s.name service, t.status, t.priority, t.channel,
                        t.issued_at, t.called_at, t.finished_at, c.code counter
                     FROM tickets t JOIN services s ON s.id=t.service_id
                     LEFT JOIN counters c ON c.id=t.counter_id
                     WHERE $where ORDER BY t.issued_at", $args);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="statistici_'.$from.'_'.$to.'.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pt Excel
        fputcsv($out, ['Bon','Serviciu','Status','Prioritar','Canal','Emis','Apelat','Finalizat','Ghiseu']);
        foreach ($rows as $r) fputcsv($out, [$r['label'],$r['service'],$r['status'],$r['priority']?'Da':'Nu',
            $r['channel'],$r['issued_at'],$r['called_at'],$r['finished_at'],$r['counter']]);
        fclose($out); exit;
    }

    $kpi = one("SELECT COUNT(*) total,
                SUM(status='served') served, SUM(status='no_show') no_show, SUM(status='cancelled') cancelled,
                AVG(TIMESTAMPDIFF(SECOND,issued_at,called_at)) avg_wait,
                AVG(CASE WHEN status='served' THEN TIMESTAMPDIFF(SECOND,called_at,finished_at) END) avg_service
                FROM tickets t WHERE $where", $args) ?: [];

    $per_day = all("SELECT DATE(t.issued_at) d, COUNT(*) c,
                    AVG(TIMESTAMPDIFF(SECOND,t.issued_at,t.called_at)) w
                    FROM tickets t WHERE $where GROUP BY d ORDER BY d", $args);
    $per_service = all("SELECT s.name, s.color, COUNT(t.id) c,
                        SUM(t.status='served') served,
                        AVG(TIMESTAMPDIFF(SECOND,t.issued_at,t.called_at)) w,
                        AVG(CASE WHEN t.status='served' THEN TIMESTAMPDIFF(SECOND,t.called_at,t.finished_at) END) sv
                        FROM services s LEFT JOIN tickets t ON t.service_id=s.id AND ($where)
                        GROUP BY s.id ORDER BY c DESC", $args);
    $per_hour = all("SELECT HOUR(t.issued_at) h, COUNT(*) c FROM tickets t WHERE $where GROUP BY h", $args);
    $per_counter = all("SELECT c.code, c.name, COUNT(t.id) cnt
                        FROM counters c LEFT JOIN tickets t ON t.counter_id=c.id AND ($where)
                        GROUP BY c.id ORDER BY cnt DESC", $args);
    $branches = all('SELECT id,name FROM branches ORDER BY name');

    view('admin/statistics', compact('from','to','branch','branches','kpi','per_day','per_service','per_hour','per_counter'));
}

/* ----------------------- FORMS (formulare) ----------------------- */
function admin_forms_list(): void {
    $rows = all('SELECT * FROM forms ORDER BY name');
    foreach ($rows as &$r) {
        $r['count'] = count(json_decode($r['fields'] ?: '[]', true) ?: []);
        $r['used']  = (int) val('SELECT COUNT(*) FROM services WHERE form_id=?', [$r['id']]);
    } unset($r);
    view('admin/forms', ['rows' => $rows]);
}
function admin_form_builder(?int $id): void {
    $row = $id ? one('SELECT * FROM forms WHERE id=?', [$id]) : null;
    view('admin/form_builder', ['row' => $row]);
}
function admin_form_save(): void {
    csrf_check();
    $d = json_decode(file_get_contents('php://input'), true);
    if (!is_array($d) || trim($d['name'] ?? '') === '') json_out(['ok' => false, 'error' => 'Nume formular obligatoriu'], 422);
    $fields = json_encode($d['fields'] ?? [], JSON_UNESCAPED_UNICODE);
    $id = (int)($d['id'] ?? 0);
    if ($id) { q('UPDATE forms SET name=?, fields=? WHERE id=?', [trim($d['name']), $fields, $id]); }
    else { q('INSERT INTO forms (name, fields) VALUES (?, ?)', [trim($d['name']), $fields]); $id = insert_id(); }
    json_out(['ok' => true, 'id' => $id]);
}

/* ----------------------- APPOINTMENTS (programari) ----------------------- */
function admin_appointments_list(): void {
    $date   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
    $branch = (int)($_GET['branch'] ?? 0);
    $w = 'DATE(a.slot_start)=?'; $args = [$date];
    if ($branch) { $w .= ' AND a.branch_id=?'; $args[] = $branch; }
    $rows = all("SELECT a.*, s.name service_name, s.color, s.prefix, b.name branch_name,
                    t.label ticket_label, t.public_token ticket_token
                 FROM appointments a JOIN services s ON s.id=a.service_id JOIN branches b ON b.id=a.branch_id
                 LEFT JOIN tickets t ON t.id=a.ticket_id WHERE $w ORDER BY a.slot_start", $args);
    $branches = all('SELECT id,name FROM branches ORDER BY name');
    $services = all('SELECT id,prefix,name,branch_id FROM services WHERE appt_enabled=1 AND status="active" ORDER BY name');
    view('admin/appointments', compact('rows','date','branch','branches','services'));
}
function admin_appointment_create(): void {
    csrf_check();
    $sid = (int)($_POST['service_id'] ?? 0); $d = $_POST['date'] ?? ''; $tm = $_POST['time'] ?? '';
    $ts = strtotime(trim($d.' '.$tm));
    if (!$sid || !$ts) { flash('Completeaza serviciul, data si ora.', 'error'); redirect('admin/appointments'); }
    try { appt_book($sid, date('Y-m-d H:i:00', $ts), trim($_POST['name'] ?? '') ?: null,
            trim($_POST['phone'] ?? '') ?: null, trim($_POST['email'] ?? '') ?: null, 'manual'); flash('Programare creata.'); }
    catch (Throwable $e) { flash($e->getMessage(), 'error'); }
    redirect('admin/appointments?date='.urlencode($d ?: date('Y-m-d')));
}
function admin_appointment_action(int $id, string $act): void {
    csrf_check();
    $a = one('SELECT * FROM appointments WHERE id=?', [$id]);
    if (!$a) redirect('admin/appointments');
    if ($act === 'checkin') { try { appt_checkin($a); flash('Check-in efectuat, bilet generat.'); } catch (Throwable $e) { flash($e->getMessage(), 'error'); } }
    elseif ($act === 'cancel') { q("UPDATE appointments SET status='cancelled' WHERE id=?", [$id]); flash('Programare anulata.'); }
    redirect('admin/appointments?date='.urlencode(substr($a['slot_start'],0,10)));
}

/* ----------------------- ROLES (permisiuni) ----------------------- */
function admin_roles(): void {
    $areas = perm_areas();
    $cfg = role_perms();
    $mgr = $cfg['manager'] ?? [];
    // valori implicite manager (cand nu exista config)
    $managerCan = [];
    foreach (array_keys($areas) as $ar) $managerCan[$ar] = isset($mgr[$ar]) ? (bool)$mgr[$ar] : !in_array($ar, ['users','settings'], true);
    view('admin/roles', compact('areas','managerCan'));
}
