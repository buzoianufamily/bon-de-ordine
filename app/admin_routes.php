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
            if ($method === 'POST' && $b === 'duplicate') { admin_branch_duplicate((int)$a); return; }
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

        case 'tickets':
            if ($method === 'POST' && $a === 'reset') { admin_tickets_reset(); return; }
            admin_tickets(); return;

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
        'no_show' => (int) val('SELECT COUNT(*) FROM tickets WHERE status="no_show" AND DATE(issued_at)=?', [$today]),
        'avg_wait'=> (int) (val('SELECT AVG(TIMESTAMPDIFF(SECOND, issued_at, called_at)) FROM tickets WHERE called_at IS NOT NULL AND DATE(issued_at)=?', [$today]) ?? 0),
    ];
    $per_service = all('SELECT s.name, s.color, COUNT(t.id) cnt
        FROM services s LEFT JOIN tickets t ON t.service_id=s.id AND DATE(t.issued_at)=?
        GROUP BY s.id ORDER BY cnt DESC, s.sort_order', [$today]);
    // aflux pe ora (azi) — pentru graficul de tendinta
    $per_hour = array_fill(0, 24, 0);
    foreach (all('SELECT HOUR(issued_at) h, COUNT(*) c FROM tickets WHERE DATE(issued_at)=? GROUP BY h', [$today]) as $r)
        $per_hour[(int)$r['h']] = (int)$r['c'];
    $devices = all('SELECT name, type, connection_key, last_seen,
        (last_seen IS NOT NULL AND last_seen > (NOW() - INTERVAL 2 MINUTE)) AS online FROM devices ORDER BY type');
    view('admin/dashboard', compact('stats','per_service','per_hour','devices'));
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
    $notify=isset($_POST['notify_browser'])?1:0;
    if ($name==='' || $email==='') { flash('Nume si email obligatorii.', 'error'); redirect('admin/users'); }
    if ($id) {
        if ($pass !== '') q('UPDATE users SET name=?,email=?,role=?,active=?,notify_browser=?,password_hash=? WHERE id=?',
            [$name,$email,$role,$active,$notify,password_hash($pass,PASSWORD_DEFAULT),$id]);
        else q('UPDATE users SET name=?,email=?,role=?,active=?,notify_browser=? WHERE id=?', [$name,$email,$role,$active,$notify,$id]);
    } else {
        if ($pass === '') { flash('Parola obligatorie la utilizator nou.', 'error'); redirect('admin/users/new'); }
        try { q('INSERT INTO users (name,email,role,active,notify_browser,password_hash) VALUES (?,?,?,?,?,?)',
            [$name,$email,$role,$active,$notify,password_hash($pass,PASSWORD_DEFAULT)]); }
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
    $branches = all('SELECT id,name FROM branches ORDER BY name');
    view('admin/tickets', compact('rows','date','branches'));
}

/** Reset bonuri: STERGE complet biletele (coada + istoric/statistici) si reincepe numerotarea de la 0. */
function admin_tickets_reset(): void {
    csrf_check();
    $branch = (int)($_POST['branch'] ?? 0);
    try {
        db()->beginTransaction();
        if ($branch) {
            q("DELETE FROM tickets WHERE branch_id=?", [$branch]);
            q("DELETE FROM ticket_sequences WHERE service_id IN (SELECT id FROM services WHERE branch_id=?)", [$branch]);
        } else {
            q("DELETE FROM tickets");
            q("DELETE FROM ticket_sequences");
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('Resetarea a esuat: '.$e->getMessage(), 'error');
        redirect('admin/tickets');
    }
    flash('Bonuri sterse complet: coada, statisticile si numerotarea au fost resetate de la 0.');
    redirect('admin/tickets');
}

/* ----------------------- SETTINGS ----------------------- */
function admin_settings_form(): void { view('admin/settings'); }
function admin_settings_save(): void {
    csrf_check();
    $keys = ['brand_name','accent_color','brand_logo','language','display_voice','display_repeat',
             'ticket_footer','ticket_header','dispenser_title','org_name','ticket_num_size',
             'alert_called','alert_transfer','alert_delay'];
    foreach ($keys as $k) if (isset($_POST[$k])) set_setting($k, trim((string)$_POST[$k]));
    set_setting('display_say_number', isset($_POST['display_say_number']) ? '1' : '0');
    set_setting('display_say_counter', isset($_POST['display_say_counter']) ? '1' : '0');
    set_setting('counter_voice', isset($_POST['counter_voice']) ? '1' : '0');
    set_setting('virtual_enabled', isset($_POST['virtual_enabled']) ? '1' : '0');
    set_setting('ticket_show_position', isset($_POST['ticket_show_position']) ? '1' : '0');
    set_setting('ticket_show_datetime', isset($_POST['ticket_show_datetime']) ? '1' : '0');
    set_setting('ticket_show_qr', isset($_POST['ticket_show_qr']) ? '1' : '0');
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
/** Duplica o filiala impreuna cu serviciile, ghiseele si dispozitivele ei (chei noi). */
function admin_branch_duplicate(int $id): void {
    csrf_check();
    $src = one('SELECT * FROM branches WHERE id=?', [$id]);
    if (!$src) { flash('Filiala inexistenta.', 'error'); redirect('admin/branches'); }
    try {
        db()->beginTransaction();

        // 1) filiala noua (numarul de copii este urcat in nume)
        $base = preg_replace('/\s*\(copie( \d+)?\)$/u', '', $src['name']);
        $n = 1; do { $newName = $base.' (copie'.($n>1?' '.$n:'').')'; $n++; }
        while ($n <= 50 && (int)val('SELECT COUNT(*) FROM branches WHERE name=?', [$newName]) > 0);
        q('INSERT INTO branches (name,city,country,address,timezone,active) VALUES (?,?,?,?,?,?)',
          [$newName, $src['city'], $src['country'], $src['address'], $src['timezone'], $src['active']]);
        $newBranch = insert_id();

        // 2) servicii (pastreaza maparea vechi->nou pt ghisee/dispozitive)
        $svcMap = [];
        foreach (all('SELECT * FROM services WHERE branch_id=? ORDER BY id', [$id]) as $s) {
            q('INSERT INTO services (branch_id,prefix,name,abbreviation,description,color,status,num_from,num_to,include_zeros,pad_length,allow_priority,terminate_on_call,kpi_wait_sec,kpi_service_sec,max_queued,sort_order,active_hours,form_id,appt_enabled,appt_slot_min,appt_capacity)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [$newBranch,$s['prefix'],$s['name'],$s['abbreviation'],$s['description'],$s['color'],$s['status'],$s['num_from'],$s['num_to'],$s['include_zeros'],$s['pad_length'],$s['allow_priority'],$s['terminate_on_call'],$s['kpi_wait_sec'],$s['kpi_service_sec'],$s['max_queued'],$s['sort_order'],$s['active_hours'],$s['form_id'],$s['appt_enabled'],$s['appt_slot_min'],$s['appt_capacity']]);
            $svcMap[(int)$s['id']] = insert_id();
        }

        // 3) ghisee + serviciile lor
        foreach (all('SELECT * FROM counters WHERE branch_id=? ORDER BY id', [$id]) as $c) {
            q('INSERT INTO counters (branch_id,code,name,status,all_services,priority) VALUES (?,?,?,?,?,?)',
              [$newBranch,$c['code'],$c['name'],$c['status'],$c['all_services'],$c['priority']]);
            $newCounter = insert_id();
            foreach (all('SELECT service_id FROM counter_services WHERE counter_id=?', [(int)$c['id']]) as $cs) {
                if (isset($svcMap[(int)$cs['service_id']]))
                    q('INSERT IGNORE INTO counter_services (counter_id,service_id) VALUES (?,?)', [$newCounter, $svcMap[(int)$cs['service_id']]]);
            }
        }

        // 4) dispozitive (cu cheie de conectare NOUA) + serviciile lor
        foreach (all('SELECT * FROM devices WHERE branch_id=? ORDER BY id', [$id]) as $d) {
            do { $key = gen_key(6); } while (one('SELECT id FROM devices WHERE connection_key=?', [$key]));
            q('INSERT INTO devices (branch_id,type,name,connection_key,all_services,config,printer_mode,printer_ip,printer_port) VALUES (?,?,?,?,?,?,?,?,?)',
              [$newBranch,$d['type'],$d['name'],$key,$d['all_services'],$d['config'],$d['printer_mode'],$d['printer_ip'],$d['printer_port']]);
            $newDev = insert_id();
            foreach (all('SELECT service_id FROM device_services WHERE device_id=?', [(int)$d['id']]) as $ds) {
                if (isset($svcMap[(int)$ds['service_id']]))
                    q('INSERT IGNORE INTO device_services (device_id,service_id) VALUES (?,?)', [$newDev, $svcMap[(int)$ds['service_id']]]);
            }
        }

        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('Duplicarea a esuat: '.$e->getMessage(), 'error');
        redirect('admin/branches');
    }
    flash('Filiala duplicata (servicii, ghisee si dispozitive incluse).');
    redirect('admin/branches');
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

    // export CSV (toate biletele din interval) — fara dataset specific
    if (($_GET['export'] ?? '') === 'csv' && empty($_GET['dataset'])) {
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
    // bilete pe utilizator (operator) + timp mediu (ca la Moviik)
    $per_user = all("SELECT u.name, COUNT(t.id) c, SUM(t.status='served') served,
                     AVG(TIMESTAMPDIFF(SECOND,t.issued_at,t.called_at)) w,
                     AVG(CASE WHEN t.status='served' THEN TIMESTAMPDIFF(SECOND,t.called_at,t.finished_at) END) sv
                     FROM tickets t JOIN users u ON u.id=t.agent_id
                     WHERE $where GROUP BY u.id ORDER BY c DESC", $args);
    $branches = all('SELECT id,name FROM branches ORDER BY name');

    // feedback client (satisfactie) in interval
    $fbWhere = 'DATE(f.created_at) BETWEEN ? AND ?'; $fbArgs = [$from, $to];
    if ($branch) { $fbWhere .= ' AND f.branch_id=?'; $fbArgs[] = $branch; }
    $feedback = one("SELECT COUNT(*) n, AVG(rating) avg FROM feedback f WHERE $fbWhere", $fbArgs) ?: ['n'=>0,'avg'=>null];
    $fb_dist  = all("SELECT rating, COUNT(*) c FROM feedback f WHERE $fbWhere GROUP BY rating", $fbArgs);
    $fb_recent = all("SELECT rating, comment, created_at FROM feedback f WHERE $fbWhere AND comment IS NOT NULL AND comment<>'' ORDER BY created_at DESC LIMIT 15", $fbArgs);

    // export CSV per set de date (fiecare grafic are buton propriu de download)
    if (($_GET['export'] ?? '') === 'csv' && in_array($_GET['dataset'] ?? '', ['day','service','counter','hour','user'], true)) {
        $ds = $_GET['dataset'];
        $sets = [
          'day'     => ['bilete_pe_zi', ['Data','Bilete','Timp mediu asteptare (s)'],
                        array_map(fn($r)=>[$r['d'],(int)$r['c'],round((float)$r['w'])], $per_day)],
          'service' => ['bilete_pe_serviciu', ['Serviciu','Total','Servite','Timp mediu asteptare (s)','Timp mediu servire (s)'],
                        array_map(fn($r)=>[$r['name'],(int)$r['c'],(int)$r['served'],round((float)$r['w']),round((float)$r['sv'])], $per_service)],
          'counter' => ['bilete_pe_ghiseu', ['Cod','Ghiseu','Bilete'],
                        array_map(fn($r)=>[$r['code'],$r['name'],(int)$r['cnt']], $per_counter)],
          'hour'    => ['aflux_orar', ['Ora','Bilete'],
                        array_map(fn($r)=>[((int)$r['h']).':00',(int)$r['c']], $per_hour)],
          'user'    => ['bilete_pe_utilizator', ['Utilizator','Total','Servite','Timp mediu asteptare (s)','Timp mediu servire (s)'],
                        array_map(fn($r)=>[$r['name'],(int)$r['c'],(int)$r['served'],round((float)$r['w']),round((float)$r['sv'])], $per_user)],
        ];
        [$fname,$head,$data] = $sets[$ds];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$fname.'_'.$from.'_'.$to.'.csv"');
        $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,$head); foreach($data as $row) fputcsv($out,$row);
        fclose($out); exit;
    }

    // export Excel (.xlsx) cu grafice native
    if (($_GET['export'] ?? '') === 'xlsx') {
        $brand = (string) setting('brand_name', 'Bon de ordine');
        $branchLabel = $branch
            ? ('Filiala: ' . ((string)(val('SELECT name FROM branches WHERE id=?', [$branch]) ?? $branch)))
            : 'Toate filialele';
        $accent = (string) setting('accent_color', '#2563eb');
        $xl = build_stats_xlsx($brand, $branchLabel, $from, $to, $kpi, $per_day, $per_service, $per_hour, $per_counter, $accent);
        $xl->download('statistici_' . $from . '_' . $to . '.xlsx');
    }

    view('admin/statistics', compact('from','to','branch','branches','kpi','per_day','per_service','per_hour','per_counter','per_user','feedback','fb_dist','fb_recent'));
}

/**
 * Construieste workbook-ul de statistici (.xlsx) cu grafice native Excel:
 * placinta (status), linie (bilete/zi), bare (serviciu/ghiseu), coloane (aflux orar).
 */
function build_stats_xlsx(string $brand, string $branchLabel, string $from, string $to,
                          array $kpi, array $per_day, array $per_service, array $per_hour,
                          array $per_counter, string $accent): Xlsx {
    $mins = fn($s) => round(((float)$s) / 60, 1);
    $served = (int)($kpi['served'] ?? 0); $noshow = (int)($kpi['no_show'] ?? 0); $canc = (int)($kpi['cancelled'] ?? 0);

    $xl = new Xlsx(['title' => 'Raport statistici · ' . $brand, 'author' => $brand, 'accent' => $accent]);

    /* ---- Foaia 1: Rezumat (KPI + placinta status) ---- */
    $s = $xl->add_sheet('Rezumat');
    $s->set_col_widths([1 => 30, 2 => 14]);
    $s->row([['v' => $brand, 's' => Xlsx::S_TITLE]]);
    $s->row([['v' => 'Raport statistici', 's' => Xlsx::S_MUTED]]);
    $s->row([['v' => 'Perioada: ' . $from . ' – ' . $to . '   ·   ' . $branchLabel . '   ·   generat ' . date('d.m.Y H:i'), 's' => Xlsx::S_MUTED]]);
    $s->blank();
    $s->row(['Indicator', 'Valoare'], Xlsx::S_HEAD);
    $s->row(['Total bilete', (int)($kpi['total'] ?? 0)]);
    $s->row(['Servite', $served]);
    $s->row(['Neprezentate', $noshow]);
    $s->row(['Anulate', $canc]);
    $s->row(['Timp mediu asteptare (min)', ['v' => $mins($kpi['avg_wait'] ?? 0), 's' => Xlsx::S_NUM1]]);
    $s->row(['Timp mediu servire (min)', ['v' => $mins($kpi['avg_service'] ?? 0), 's' => Xlsx::S_NUM1]]);
    $s->blank();
    $s->row(['Status', 'Bilete'], Xlsx::S_HEAD);
    $r1 = $s->row(['Servite', $served]);
    $s->row(['Neprezentate', $noshow]);
    $r3 = $s->row(['Anulate', $canc]);
    $s->chart([
        'type' => 'pie', 'title' => 'Defalcare status', 'legend' => true, 'legend_pos' => 'r',
        'anchor' => ['col' => 3, 'row' => 4, 'col2' => 11, 'row2' => 20],
        'cat' => ['ref' => '$A$' . $r1 . ':$A$' . $r3, 'vals' => ['Servite', 'Neprezentate', 'Anulate']],
        'series' => [['ref' => '$B$' . $r1 . ':$B$' . $r3, 'vals' => [$served, $noshow, $canc],
                      'colors' => ['#16a34a', '#94a3b8', '#ef4444']]],
    ]);

    /* ---- Foaia 2: Pe zi (linie) ---- */
    $s = $xl->add_sheet('Pe zi');
    $s->set_col_widths([1 => 14, 2 => 12, 3 => 26]);
    $s->row([['v' => 'Bilete pe zi', 's' => Xlsx::S_TITLE]]);
    $hdr = $s->row(['Data', 'Bilete', 'Timp mediu asteptare (min)'], Xlsx::S_HEAD);
    $cats = []; $vals = []; $first = $hdr + 1;
    foreach ($per_day as $r) {
        $s->row([date('d.m.Y', strtotime($r['d'])), (int)$r['c'], ['v' => $mins($r['w'] ?? 0), 's' => Xlsx::S_NUM1]]);
        $cats[] = date('d.m', strtotime($r['d'])); $vals[] = (int)$r['c'];
    }
    $last = $s->row_count();
    if ($vals) $s->chart([
        'type' => 'line', 'title' => 'Bilete pe zi',
        'anchor' => ['col' => 4, 'row' => 1, 'col2' => 14, 'row2' => 21],
        'cat' => ['ref' => '$A$' . $first . ':$A$' . $last, 'vals' => $cats],
        'series' => [['name' => 'Bilete', 'name_ref' => '$B$' . $hdr, 'ref' => '$B$' . $first . ':$B$' . $last, 'vals' => $vals, 'color' => $accent]],
    ]);

    /* ---- Foaia 3: Pe serviciu (bare colorate) ---- */
    $s = $xl->add_sheet('Pe serviciu');
    $s->set_col_widths([1 => 24, 2 => 10, 3 => 10, 4 => 22, 5 => 20]);
    $s->row([['v' => 'Bilete pe serviciu', 's' => Xlsx::S_TITLE]]);
    $hdr = $s->row(['Serviciu', 'Total', 'Servite', 'Timp asteptare (min)', 'Timp servire (min)'], Xlsx::S_HEAD);
    $cats = []; $vals = []; $cols = []; $first = $hdr + 1;
    foreach ($per_service as $r) {
        $s->row([$r['name'], (int)$r['c'], (int)($r['served'] ?? 0),
                 ['v' => $mins($r['w'] ?? 0), 's' => Xlsx::S_NUM1], ['v' => $mins($r['sv'] ?? 0), 's' => Xlsx::S_NUM1]]);
        $cats[] = $r['name']; $vals[] = (int)$r['c']; $cols[] = $r['color'] ?: $accent;
    }
    $last = $s->row_count();
    if ($vals) $s->chart([
        'type' => 'bar', 'title' => 'Total bilete pe serviciu',
        'anchor' => ['col' => 6, 'row' => 1, 'col2' => 15, 'row2' => 21],
        'cat' => ['ref' => '$A$' . $first . ':$A$' . $last, 'vals' => $cats],
        'series' => [['name' => 'Total', 'name_ref' => '$B$' . $hdr, 'ref' => '$B$' . $first . ':$B$' . $last, 'vals' => $vals, 'colors' => $cols]],
    ]);

    /* ---- Foaia 4: Pe ora (coloane) ---- */
    $s = $xl->add_sheet('Pe ora');
    $s->set_col_widths([1 => 10, 2 => 12]);
    $s->row([['v' => 'Aflux pe ora din zi', 's' => Xlsx::S_TITLE]]);
    $hdr = $s->row(['Ora', 'Bilete'], Xlsx::S_HEAD);
    $hours = array_fill(0, 24, 0); foreach ($per_hour as $r) $hours[(int)$r['h']] = (int)$r['c'];
    $cats = []; $vals = []; $first = $hdr + 1;
    for ($h = 0; $h < 24; $h++) { $s->row([sprintf('%02d:00', $h), $hours[$h]]); $cats[] = sprintf('%02d', $h); $vals[] = $hours[$h]; }
    $last = $s->row_count();
    $s->chart([
        'type' => 'col', 'title' => 'Aflux pe ora',
        'anchor' => ['col' => 3, 'row' => 1, 'col2' => 14, 'row2' => 22],
        'cat' => ['ref' => '$A$' . $first . ':$A$' . $last, 'vals' => $cats],
        'series' => [['name' => 'Bilete', 'name_ref' => '$B$' . $hdr, 'ref' => '$B$' . $first . ':$B$' . $last, 'vals' => $vals, 'color' => $accent]],
    ]);

    /* ---- Foaia 5: Pe ghiseu (coloane) ---- */
    $s = $xl->add_sheet('Pe ghiseu');
    $s->set_col_widths([1 => 26, 2 => 12]);
    $s->row([['v' => 'Bilete pe ghiseu', 's' => Xlsx::S_TITLE]]);
    $hdr = $s->row(['Ghiseu', 'Bilete'], Xlsx::S_HEAD);
    $cats = []; $vals = []; $first = $hdr + 1;
    foreach ($per_counter as $r) {
        if ((int)$r['cnt'] === 0) continue;
        $label = trim(($r['code'] ?? '') . ' ' . ($r['name'] ?? ''));
        $s->row([$label, (int)$r['cnt']]); $cats[] = $label; $vals[] = (int)$r['cnt'];
    }
    $last = $s->row_count();
    if ($vals) $s->chart([
        'type' => 'col', 'title' => 'Bilete pe ghiseu',
        'anchor' => ['col' => 3, 'row' => 1, 'col2' => 13, 'row2' => 20],
        'cat' => ['ref' => '$A$' . $first . ':$A$' . $last, 'vals' => $cats],
        'series' => [['name' => 'Bilete', 'name_ref' => '$B$' . $hdr, 'ref' => '$B$' . $first . ':$B$' . $last, 'vals' => $vals, 'color' => $accent]],
    ]);

    return $xl;
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
