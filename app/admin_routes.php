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

    // ---- politica: adminii sunt obligati sa aiba 2FA activ (pot accesa doar pagina Securitate) ----
    if ($area !== 'security' && current_user()['role'] === 'admin' && setting('force_2fa_admin', '0') === '1') {
        try {
            if ((int) val('SELECT totp_enabled FROM users WHERE id=?', [current_user()['id']]) !== 1) {
                flash('Politica de securitate: activeaza autentificarea in doi pasi (2FA) pentru a continua.', 'error');
                redirect('admin/security');
            }
        } catch (Throwable $e) {}
    }

    switch ($res) {
        case 'dashboard': case '':
            if ($method === 'POST' && $a === 'dismiss-onboarding') { csrf_check(); set_setting('onboarding_dismissed', '1'); redirect('admin'); }
            admin_dashboard(); return;

        case 'search': admin_global_search(); return;

        case 'statistics': admin_statistics(); return;

        case 'branches':
            if ($method === 'POST' && $a === 'import') { admin_branches_import(); return; }
            if ($a === 'export') { admin_branches_export(); return; }
            if ($method === 'POST' && $a === null) { admin_branch_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check();
                if ((int)val('SELECT COUNT(*) FROM branches') > 1) { q('DELETE FROM branches WHERE id=?', [(int)$a]); audit('delete','branch',(int)$a); flash('Filiala stearsa.'); }
                else flash('Nu poti sterge ultima filiala.', 'error');
                redirect('admin/branches'); }
            if ($method === 'POST' && $b === 'duplicate') { admin_branch_duplicate((int)$a); return; }
            if ($a === 'new') { admin_branch_form(null); return; }
            if (ctype_digit((string)$a) && $b === 'edit') { admin_branch_form((int)$a); return; }
            if (ctype_digit((string)$a)) { admin_branch_detail((int)$a); return; }
            admin_branches_list(); return;

        case 'closures':
            if (!can('branches')) { flash('Nu ai acces la sectiunea respectiva.', 'error'); redirect('admin'); }
            if ($method === 'POST' && $a === 'import') { admin_closures_import(); return; }
            if ($a === 'export') { admin_closures_export(); return; }
            if ($method === 'POST' && $a === null) { admin_closure_save(); return; }
            if ($method === 'POST' && $b === 'delete') { admin_closure_delete((int)$a); return; }
            admin_closures_page(); return;

        case 'services':
            if ($method === 'POST' && $a === 'reorder') { admin_services_reorder(); return; }
            if ($method === 'POST' && $a === 'import') { admin_services_import(); return; }
            if ($a === 'export') { admin_services_export(); return; }
            if ($method === 'POST' && $b === 'pause') { csrf_check();
                $p = (int)!val('SELECT paused FROM services WHERE id=?', [(int)$a]);
                $note = $p ? (mb_substr(trim((string)($_POST['note'] ?? '')), 0, 120) ?: null) : null;
                q('UPDATE services SET paused=?, pause_note=? WHERE id=?', [$p, $note, (int)$a]); audit('update','service',(int)$a, $p?'pauza':'reluat');
                flash($p ? 'Serviciu oprit temporar.' : 'Serviciu reluat.'); redirect('admin/services'); }
            if ($method === 'POST' && $a === null) { admin_service_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check(); q('DELETE FROM services WHERE id=?', [(int)$a]); audit('delete','service',(int)$a); flash('Serviciu sters.'); redirect('admin/services'); }
            if ($a === 'new') { admin_service_form(null); return; }
            if (ctype_digit((string)$a)) { admin_service_form((int)$a); return; }
            admin_services_list(); return;

        case 'groups':
            if ($method === 'POST' && $a === 'reorder') { admin_groups_reorder(); return; }
            if ($method === 'POST' && $a === 'assign') { admin_group_assign(); return; }
            if ($method === 'POST' && $a === null) { admin_group_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check();
                q('UPDATE services SET group_id=NULL WHERE group_id=?', [(int)$a]);
                q('UPDATE service_groups SET parent_id=NULL WHERE parent_id=?', [(int)$a]);  // subgrupurile devin grupuri de nivel 0
                q('DELETE FROM service_groups WHERE id=?', [(int)$a]); audit('delete','group',(int)$a); flash('Grup sters.'); redirect('admin/groups'); }
            admin_groups_list(); return;

        case 'counters':
            if ($method === 'POST' && $a === 'import') { admin_counters_import(); return; }
            if ($a === 'export') { admin_counters_export(); return; }
            if ($method === 'POST' && $a === null) { admin_counter_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check(); q('DELETE FROM counters WHERE id=?', [(int)$a]); audit('delete','counter',(int)$a); flash('Ghiseu sters.'); redirect('admin/counters'); }
            if ($a === 'new') { admin_counter_form(null); return; }
            if (ctype_digit((string)$a)) { admin_counter_form((int)$a); return; }
            admin_counters_list(); return;

        case 'users':
            if ($method === 'POST' && $a === 'import') { admin_users_import(); return; }
            if ($a === 'export') { admin_users_export(); return; }
            if ($method === 'POST' && $a === null) { admin_user_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check(); q('DELETE FROM users WHERE id=? AND id<>?', [(int)$a, current_user()['id']]); audit('delete','user',(int)$a); flash('Utilizator sters.'); redirect('admin/users'); }
            if ($a === 'new') { admin_user_form(null); return; }
            if (ctype_digit((string)$a)) { admin_user_form((int)$a); return; }
            admin_users_list(); return;

        case 'devices':
            if ($method === 'POST' && $a === null) { admin_device_save(); return; }
            if ($method === 'POST' && $b === 'delete') { csrf_check(); q('DELETE FROM devices WHERE id=?', [(int)$a]); audit('delete','device',(int)$a); flash('Dispozitiv sters.'); redirect('admin/devices'); }
            if (ctype_digit((string)$a) && $b === 'player') {   // editor canvas afisaj
                if ($method === 'POST') { admin_player_save((int)$a); return; }
                admin_player_builder((int)$a); return;
            }
            if (ctype_digit((string)$a) && $b === 'dispenser') { // editor configurare dispenser (clasic, form)
                if ($method === 'POST') { admin_dispenser_save((int)$a); return; }
                admin_dispenser_builder((int)$a); return;
            }
            if (ctype_digit((string)$a) && $b === 'dispenser-canvas') { // editor canvas dispenser (ca la TV)
                if ($method === 'POST') { admin_dispenser_canvas_save((int)$a); return; }
                admin_dispenser_canvas_builder((int)$a); return;
            }
            if ($a === 'qr') { admin_devices_qr(); return; }
            if ($a === 'new') { admin_device_form(null); return; }
            if (ctype_digit((string)$a)) { admin_device_form((int)$a); return; }
            admin_devices_list(); return;

        case 'tickets':
            if ($method === 'POST' && $a === 'reset') { admin_tickets_reset(); return; }
            if ($a === 'export') { admin_tickets_export(); return; }
            if (ctype_digit((string)$a)) { admin_ticket_detail((int)$a); return; }
            admin_tickets(); return;

        case 'feedback':
            if ($method === 'POST' && $b === 'delete') { csrf_check(); q('DELETE FROM feedback WHERE id=?', [(int)$a]); audit('delete','feedback',(int)$a); flash('Feedback sters.'); redirect('admin/feedback'); }
            if ($a === 'export') { admin_feedback_export(); return; }
            admin_feedback_list(); return;

        case 'appointments':
            if ($method === 'POST' && $a === null) { admin_appointment_create(); return; }
            if ($method === 'POST' && $a === 'waitlist-del') { csrf_check(); q('DELETE FROM appointment_waitlist WHERE id=?', [(int)$b]); audit('delete','waitlist',(int)$b); flash('Intrare stearsa din lista de asteptare.'); redirect('admin/appointments'); }
            if ($method === 'POST' && $b === 'checkin') { admin_appointment_action((int)$a, 'checkin'); return; }
            if ($method === 'POST' && $b === 'cancel')  { admin_appointment_action((int)$a, 'cancel'); return; }
            if ($a === 'export') { admin_appointments_export(); return; }
            admin_appointments_list(); return;

        case 'media':
            if ($method === 'POST' && $a === 'upload') { admin_media_upload(); return; }
            if ($method === 'POST' && $a === 'delete-bulk') { admin_media_bulk_delete(); return; }
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
            // export/import configuratie = unealta de furnizor -> mutata in panoul landlord (404 la client)
            if ($a === 'export' || ($method === 'POST' && $a === 'import')) { http_response_code(404); echo 'Sectiune inexistenta.'; return; }
            if ($method === 'POST') { admin_settings_save(); return; }
            admin_settings_form(); return;

        case 'roles':
            if ($method === 'POST') { csrf_check();
                $m = []; foreach (array_keys(perm_areas()) as $ar) $m[$ar] = isset($_POST['manager'][$ar]);
                set_setting('role_perms', json_encode(['manager'=>$m], JSON_UNESCAPED_UNICODE));
                audit('update','roles'); flash('Permisiuni salvate.'); redirect('admin/roles'); }
            admin_roles(); return;

        case 'api':
            if (current_user()['role'] !== 'admin') { http_response_code(403); echo 'Acces interzis.'; return; }
            if ($method === 'POST' && $a === 'test-webhook') { admin_api_test_webhook(); return; }
            if ($a === 'webhook-log-export') { admin_webhook_log_export(); return; }
            if ($method === 'POST' && $a === 'clear-webhook-log') { csrf_check(); q('DELETE FROM webhook_log'); audit('clear','webhook_log'); flash('Jurnal webhook golit.'); redirect('admin/api'); }
            if ($method === 'POST') { admin_api_save(); return; }
            admin_api_page(); return;

        case 'audit':
            if (current_user()['role'] !== 'admin') { http_response_code(403); echo 'Acces interzis.'; return; }
            if ($a === 'export') { admin_audit_export(); return; }
            admin_audit_list(); return;

        case 'backup':
            // Backup-ul bazei de date (date clienti) e o operatiune de furnizor -> doar in panoul landlord.
            http_response_code(404); echo 'Sectiune inexistenta.'; return;

        case 'security':
            if ($method === 'POST') { admin_security_save(); return; }
            admin_security_page(); return;

        case 'reset':
            if (current_user()['role'] !== 'admin') { http_response_code(403); echo 'Acces interzis.'; return; }
            if ($method === 'POST') { admin_reset_data(); return; }
            redirect('admin/settings');

        case 'gdpr':
            if (current_user()['role'] !== 'admin') { http_response_code(403); echo 'Acces interzis.'; return; }
            if ($method === 'POST' && $a === 'export') { admin_gdpr_export(); return; }
            if ($method === 'POST' && $a === 'erase')  { admin_gdpr_erase(); return; }
            admin_gdpr_page(); return;

        case 'help':
            view('admin/help', []); return;

        case 'apps':
            admin_apps(); return;
    }
    http_response_code(404); echo 'Sectiune inexistenta.';
}

/* ----------------------- APLICATII (lansator) -----------------------
 * Pagina-hub in stil Moviik: carduri pentru fiecare aplicatie (terminal operator,
 * concierge, programari, dozator, afisaj TV, bilet digital, coada publica) cu
 * actiuni „Deschide" + „Configureaza". E doar un lansator catre rutele/builderele
 * existente — NU dubleaza functionalitate. */
function admin_apps(): void {
    $apps = [
        'dispenser'      => one("SELECT id, connection_key, name FROM devices WHERE type='dispenser' ORDER BY id LIMIT 1"),
        'player'         => one("SELECT id, connection_key, name FROM devices WHERE type IN ('player','widget_player') ORDER BY id LIMIT 1"),
        'digital_ticket' => one("SELECT id, connection_key, name FROM devices WHERE type='digital_ticket' ORDER BY id LIMIT 1"),
    ];
    $counts = [
        'counters' => (int) val('SELECT COUNT(*) FROM counters'),
        'services' => (int) val('SELECT COUNT(*) FROM services'),
        'appt'     => (int) val("SELECT COUNT(*) FROM services WHERE appt_enabled=1"),
        'devices'  => (int) val('SELECT COUNT(*) FROM devices'),
    ];
    view('admin/apps', compact('apps', 'counts'));
}

/* ----------------------- VERIFICARE PRODUCTIE (readiness) ----------------------- */
/**
 * Diagnoza „pregatit de productie?": semnaleaza proactiv configurari gresite
 * inainte sa devina reclamatii. Returneaza o lista de [level, title, detail],
 * level in 'ok' | 'warn' | 'crit'.
 */
function system_checkup(): array {
    $c = [];
    $add = function (string $level, string $title, string $detail) use (&$c) { $c[] = compact('level', 'title', 'detail'); };
    $cfg = $GLOBALS['__config'] ?? [];

    // 1) Parola implicita de admin inca activa
    $defPw = 0;
    try { $defPw = (int) val("SELECT COUNT(*) FROM users WHERE role='admin' AND must_change_pw=1"); } catch (Throwable $e) {}
    $defPw > 0
        ? $add('crit', 'Parolă de admin implicită', "$defPw cont(uri) de admin încă folosesc parola implicită. Schimbă-le din „Contul meu”.")
        : $add('ok', 'Parole admin', 'Niciun admin nu mai folosește parola implicită.');

    // 2) HTTPS
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? $add('ok', 'HTTPS', 'Conexiunea este criptată.')
        : $add('warn', 'HTTPS', 'Pagina nu pare servită prin HTTPS. Activează un certificat (Let’s Encrypt din cPanel) și redirect HTTP→HTTPS.');

    // 3) Mediu
    (($cfg['app']['env'] ?? 'production') === 'production')
        ? $add('ok', 'Mediu', 'env=production (erorile nu se afișează vizitatorilor).')
        : $add('warn', 'Mediu', 'env nu este „production": detaliile erorilor pot fi afișate. Setează app.env=production în config/config.php.');

    // 4) Email
    (function_exists('mail_enabled') && mail_enabled())
        ? $add('ok', 'Email', 'Trimiterea de emailuri este activă (reset parolă, remindere, rapoarte).')
        : $add('warn', 'Email', 'Emailul nu este configurat: resetarea parolei, reminderele și rapoartele nu funcționează. Configurează SMTP în Setări → Email.');

    // 5) Backup automat
    (setting('backup_auto_enabled', '0') === '1')
        ? $add('ok', 'Backup automat', 'Backup-ul automat zilnic este activ.')
        : $add('warn', 'Backup automat', 'Backup-ul automat este oprit. Activează-l în Setări → Backup (necesită cron).');

    // 6) Cron rulează?
    $last = (int) setting('cron_last_run', '0');
    if ($last === 0) $add('warn', 'Cron', 'Cron-ul nu a rulat încă niciodată. Configurează un Cron Job către /cron?key=… (remindere, backup, curățare).');
    elseif (time() - $last > 7200) $add('warn', 'Cron', 'Cron-ul nu a mai rulat de peste 2 ore (ultima dată: ' . date('d.m.Y H:i', $last) . '). Verifică Cron Job-ul din cPanel.');
    else $add('ok', 'Cron', 'Cron-ul a rulat recent (' . date('d.m.Y H:i', $last) . ').');

    // 7) 2FA pe conturile de admin
    try {
        $admins = (int) val("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1");
        $with2fa = (int) val("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1 AND totp_enabled=1");
        if ($admins > 0 && $with2fa < $admins)
            $add('warn', 'Autentificare în doi pași', "$with2fa din $admins admini au 2FA activ. Recomandat pentru toți (Securitate → 2FA; poți și impune-o).");
        else $add('ok', 'Autentificare în doi pași', 'Toți adminii activi au 2FA.');
    } catch (Throwable $e) {}

    // 8) Webhook fara secret
    if (trim((string) setting('webhook_url', '')) !== '' && trim((string) setting('webhook_secret', '')) === '')
        $add('warn', 'Webhook fără secret', 'Ai un URL de webhook fără secret de semnătură. Adaugă un secret în API & Webhooks ca destinatarul să verifice autenticitatea.');

    // 9) Retentie date (minimizare GDPR)
    ((int) setting('retention_months', '0') > 0)
        ? $add('ok', 'Retenție date', 'Datele vechi se șterg automat (minimizarea datelor).')
        : $add('warn', 'Retenție date', 'Nu este setată ștergerea automată a datelor vechi (Setări → Automatizări). GDPR cere păstrarea doar cât e necesar.');

    // 10) Date operator pentru paginile legale
    (trim((string) setting('legal_operator', '')) !== '' || trim((string) setting('legal_email', '')) !== '')
        ? $add('ok', 'Date legale', 'Datele operatorului pentru paginile legale sunt completate.')
        : $add('warn', 'Date legale', 'Completează datele operatorului (Setări → Legal & GDPR) pentru paginile de confidențialitate/termeni.');

    // 11) Foldere scriibile
    $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
    $unwritable = [];
    if (!is_writable($root . '/config')) $unwritable[] = 'config/';
    if (is_dir($root . '/assets/uploads') && !is_writable($root . '/assets/uploads')) $unwritable[] = 'assets/uploads/';
    $unwritable
        ? $add('warn', 'Permisiuni foldere', 'Nu sunt scriibile: ' . implode(', ', $unwritable) . '. Setează 755/775 pe ele.')
        : $add('ok', 'Permisiuni foldere', 'Folderele necesare sunt scriibile.');

    // 12) Limite de plan (doar pe instante-client cu limite setate de furnizor)
    foreach (['branches' => 'filiale', 'counters' => 'ghișee', 'users' => 'utilizatori', 'services' => 'servicii'] as $w => $lbl) {
        $lim = tenant_limit($w);
        if ($lim <= 0) continue;
        $used = (int) val("SELECT COUNT(*) FROM `$w`");          // $w din whitelist fix
        $add($used >= $lim ? 'warn' : 'ok', 'Plan: ' . $lbl, "$used / $lim folosite" . ($used >= $lim ? ' — ai atins limita planului.' : '.'));
    }

    return $c;
}

/* ----------------------- DASHBOARD ----------------------- */
function admin_dashboard(): void {
    $today = date('Y-m-d');
    $branch = (int)($_GET['branch'] ?? 0);
    $branches = all('SELECT id,name FROM branches ORDER BY name');
    $bc = $branch ? ' AND branch_id=?' : '';            // conditie filtru filiala
    $td = fn(array $extra=[]) => array_merge([$today], $branch ? array_merge($extra,[$branch]) : $extra);
    $nb = fn() => $branch ? [$branch] : [];

    $stats = [
        'today'     => (int) val("SELECT COUNT(*) FROM tickets WHERE DATE(issued_at)=?$bc", $td()),
        'waiting'   => (int) val("SELECT COUNT(*) FROM tickets WHERE status='waiting'$bc", $nb()),
        'serving'   => (int) val("SELECT COUNT(*) FROM tickets WHERE status IN ('called','serving')$bc", $nb()),
        'served'    => (int) val("SELECT COUNT(*) FROM tickets WHERE status='served' AND DATE(issued_at)=?$bc", $td()),
        'no_show'   => (int) val("SELECT COUNT(*) FROM tickets WHERE status='no_show' AND DATE(issued_at)=?$bc", $td()),
        'cancelled' => (int) val("SELECT COUNT(*) FROM tickets WHERE status='cancelled' AND DATE(issued_at)=?$bc", $td()),
        'avg_wait'  => (int) (val("SELECT AVG(TIMESTAMPDIFF(SECOND, issued_at, called_at)) FROM tickets WHERE called_at IS NOT NULL AND DATE(issued_at)=?$bc", $td()) ?? 0),
    ];
    $closed = $stats['served'] + $stats['no_show'] + $stats['cancelled'];
    $stats['abandon'] = $closed > 0 ? (int) round(($stats['no_show'] + $stats['cancelled']) / $closed * 100) : 0;

    $per_service = all("SELECT s.name, s.color, COUNT(t.id) cnt
        FROM services s LEFT JOIN tickets t ON t.service_id=s.id AND DATE(t.issued_at)=?
        WHERE 1=1" . ($branch ? ' AND s.branch_id=?' : '') . "
        GROUP BY s.id ORDER BY cnt DESC, s.sort_order", $branch ? [$today,$branch] : [$today]);

    $per_hour = array_fill(0, 24, 0);
    foreach (all("SELECT HOUR(issued_at) h, COUNT(*) c FROM tickets WHERE DATE(issued_at)=?$bc GROUP BY h", $td()) as $r)
        $per_hour[(int)$r['h']] = (int)$r['c'];
    $peakVal = 0; $peakH = -1;
    foreach ($per_hour as $h => $v) if ($v > $peakVal) { $peakVal = $v; $peakH = $h; }
    $stats['peak'] = $peakH >= 0 ? sprintf('%02d:00', $peakH) : '—';
    $stats['peak_val'] = $peakVal;

    // depasiri SLA live: bilete care asteapta ACUM peste tinta serviciului (kpi_wait_sec)
    $sla = all("SELECT s.id, s.name, s.color, s.prefix, s.kpi_wait_sec,
                    COUNT(t.id) breaching,
                    MAX(TIMESTAMPDIFF(SECOND, t.issued_at, NOW())) worst
                FROM tickets t JOIN services s ON s.id=t.service_id
                WHERE t.status='waiting' AND s.kpi_wait_sec > 0
                  AND TIMESTAMPDIFF(SECOND, t.issued_at, NOW()) > s.kpi_wait_sec"
                . ($branch ? ' AND t.branch_id=?' : '') . "
                GROUP BY s.id ORDER BY worst DESC", $nb());
    $stats['sla_breaches'] = array_sum(array_map(fn($r) => (int)$r['breaching'], $sla));

    // programari azi (doar daca modulul e activ)
    $stats['appt_today'] = 0; $stats['appt_checkin'] = 0;
    if (setting('mod_booking', '1') === '1') {
        $stats['appt_today']   = (int) val("SELECT COUNT(*) FROM appointments WHERE DATE(slot_start)=?" . ($branch?' AND branch_id=?':''), $td());
        $stats['appt_checkin'] = (int) val("SELECT COUNT(*) FROM appointments WHERE DATE(slot_start)=? AND status='checked_in'" . ($branch?' AND branch_id=?':''), $td());
    }

    $devices = all("SELECT name, type, connection_key, last_seen,
        (last_seen IS NOT NULL AND last_seen > (NOW() - INTERVAL 2 MINUTE)) AS online FROM devices" .
        ($branch ? ' WHERE branch_id=?' : '') . " ORDER BY type", $nb());
    $operators = all("SELECT name, role, work_status, last_seen,
        (last_seen IS NOT NULL AND last_seen > (NOW() - INTERVAL 2 MINUTE)) AS online
        FROM users WHERE active=1 AND (role='agent' OR last_seen IS NOT NULL)
        ORDER BY online DESC, name");

    // tendinte pe ultimele 7 zile (pentru sparkline-urile din statcards)
    $trend = ['total'=>[], 'served'=>[], 'wait'=>[], 'abandon'=>[]];
    $byDay = [];
    foreach (all("SELECT DATE(issued_at) d, COUNT(*) c, SUM(status='served') s,
                  SUM(status IN('no_show','cancelled')) ab,
                  AVG(TIMESTAMPDIFF(SECOND,issued_at,called_at)) w
                  FROM tickets WHERE issued_at >= CURDATE() - INTERVAL 6 DAY$bc GROUP BY d", $nb()) as $r) $byDay[$r['d']] = $r;
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $r = $byDay[$d] ?? null;
        $closed = $r ? ((int)$r['s'] + (int)$r['ab']) : 0;
        $trend['total'][]   = $r ? (int)$r['c'] : 0;
        $trend['served'][]  = $r ? (int)$r['s'] : 0;
        $trend['wait'][]    = $r ? (int)round((float)$r['w']) : 0;
        $trend['abandon'][] = $closed ? (int)round((int)$r['ab'] / $closed * 100) : 0;
    }

    // checklist de onboarding (ascuns dupa finalizare sau inchidere manuala)
    $onboarding = [];
    if (setting('onboarding_dismissed', '0') !== '1' && current_user()['role'] === 'admin') {
        $defaultAdmin = (int) val("SELECT COUNT(*) FROM users WHERE email='admin@example.ro' AND active=1") > 0;
        $onboarding = [
            ['done' => !$defaultAdmin, 'label' => 'Schimba contul implicit de admin (admin@example.ro)', 'url' => url('admin/users')],
            ['done' => setting('brand_name', '') !== '' && setting('brand_name') !== 'Compania Mea', 'label' => 'Seteaza numele si culoarea brandului', 'url' => url('admin/settings')],
            ['done' => (int) val('SELECT COUNT(*) FROM services') > 0, 'label' => 'Creeaza serviciile tale', 'url' => url('admin/services')],
            ['done' => (int) val('SELECT COUNT(*) FROM counters') > 0, 'label' => 'Creeaza ghiseele', 'url' => url('admin/counters')],
            ['done' => (int) val("SELECT COUNT(*) FROM users WHERE role IN ('agent','manager')") > 0, 'label' => 'Adauga operatori (deservesc ghiseele)', 'url' => url('admin/users')],
            ['done' => (int) val('SELECT COUNT(*) FROM devices') > 0, 'label' => 'Configureaza un dispozitiv (dispenser / afisaj)', 'url' => url('admin/devices')],
            ['done' => (int) val('SELECT COUNT(*) FROM tickets') > 0, 'label' => 'Emite primul bon de test', 'url' => url('admin/devices')],
        ];
        if (!array_filter($onboarding, fn($s) => !$s['done'])) $onboarding = []; // tot bifat -> nu mai aratam
    }

    // comparatie filiale (azi) — doar cand privesti toate filialele
    $branch_cmp = [];
    if (!$branch && count($branches) > 1) {
        $branch_cmp = all("SELECT b.name, COUNT(t.id) total,
            SUM(t.status='served') served, SUM(t.status='waiting') waiting,
            AVG(CASE WHEN t.called_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND,t.issued_at,t.called_at) END) avg_wait
            FROM branches b LEFT JOIN tickets t ON t.branch_id=b.id AND DATE(t.issued_at)=?
            GROUP BY b.id ORDER BY total DESC", [$today]);
    }

    // actualizare live a panourilor (poll JSON)
    if (($_GET['format'] ?? '') === 'json' || want_json()) {
        json_out(['ok'=>true, 'stats'=>$stats,
          'sla'=>array_map(fn($r)=>['name'=>$r['name'],'color'=>$r['color'],'prefix'=>$r['prefix'],
              'breaching'=>(int)$r['breaching'],'worst'=>(int)$r['worst'],'target'=>(int)$r['kpi_wait_sec']], $sla),
          'devices'=>array_map(fn($d)=>['name'=>$d['name'],'type'=>$d['type'],'key'=>$d['connection_key'],'online'=>(bool)$d['online']], $devices),
          'operators'=>array_map(fn($o)=>['name'=>$o['name'],'role'=>$o['role'],
              'status'=>$o['online']?$o['work_status']:'offline','online'=>(bool)$o['online'],
              'last_seen'=>$o['last_seen']?date('H:i',strtotime($o['last_seen'])):'—'], $operators)]);
    }
    view('admin/dashboard', compact('stats','per_service','per_hour','devices','operators','branches','branch','branch_cmp','onboarding','trend','sla'));
}

/* ----------------------- SERVICES ----------------------- */
function admin_services_list(): void {
    $rows = all('SELECT s.*, b.name AS branch_name FROM services s JOIN branches b ON b.id=s.branch_id ORDER BY b.name, s.sort_order, s.id');
    $branches = all('SELECT id, name FROM branches ORDER BY name');
    view('admin/services', ['rows' => $rows, 'branches' => $branches]);
}
/** Export servicii in CSV (prefix,nume,culoare) — round-trip cu importul. */
function admin_services_export(): void {
    $branch = (int)($_GET['branch'] ?? 0);
    $where = '1=1'; $args = [];
    if ($branch) { $where .= ' AND branch_id=?'; $args[] = $branch; }
    $tmpl = isset($_GET['template']);
    $rows = $tmpl ? [] : all("SELECT prefix, name, color FROM services WHERE $where ORDER BY branch_id, sort_order, id", $args);
    if (!$tmpl) audit('export', 'services');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($tmpl ? 'sablon_servicii.csv' : 'servicii_' . date('Ymd_His') . '.csv') . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv_safe($out, ['prefix', 'nume', 'culoare']);
    foreach ($rows as $r) fputcsv_safe($out, [$r['prefix'], $r['name'], $r['color']]);
    fclose($out);
    exit;
}
/** Import servicii din CSV (prefix,nume,culoare) pentru o filiala. */
function admin_services_import(): void {
    csrf_check();
    $branch = (int)($_POST['branch_id'] ?? 0) ?: (int) val('SELECT id FROM branches ORDER BY id LIMIT 1');
    $csv = (string)($_POST['csv'] ?? '');
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
        $csv = (string) file_get_contents($_FILES['file']['tmp_name']);
    $rows = parse_services_csv($csv);
    // prefixele deja existente in filiala (re-import sigur, fara dubluri)
    $existing = array_map('strtoupper', array_column(all('SELECT prefix FROM services WHERE branch_id=?', [$branch]), 'prefix'));
    $existing = array_flip($existing);
    $pos = (int) val('SELECT COALESCE(MAX(sort_order),0) FROM services WHERE branch_id=?', [$branch]);
    $n = 0; $skipped = 0;
    $lim = tenant_limit('services'); $cur = (int) val('SELECT COUNT(*) FROM services'); $capped = false;
    foreach ($rows as $r) {
        if (isset($existing[$r['prefix']])) { $skipped++; continue; }
        if ($lim > 0 && $cur >= $lim) { $capped = true; break; }   // respecta limita de plan si la import
        q("INSERT INTO services (branch_id,prefix,name,color,status,num_from,num_to,pad_length,include_zeros,kpi_wait_sec,kpi_service_sec,sort_order)
           VALUES (?,?,?,?,'active',1,999,3,1,600,300,?)", [$branch, $r['prefix'], $r['name'], $r['color'], ++$pos]);
        $existing[$r['prefix']] = 1; $n++; $cur++;
    }
    audit('import', 'services', $branch, $n . ' servicii');
    $msg = $n > 0 ? "$n servicii importate." : 'Niciun serviciu nou de importat.';
    if ($skipped) $msg .= " $skipped sarite (prefix existent).";
    if ($capped) $msg .= ' Limita planului pentru servicii a fost atinsa.';
    flash($msg, $n > 0 ? 'info' : 'error');
    redirect('admin/services');
}
function admin_service_form(?int $id): void {
    $row = $id ? one('SELECT * FROM services WHERE id=?', [$id]) : null;
    $branches = all('SELECT id, name FROM branches ORDER BY name');
    $forms = all('SELECT id, name FROM forms ORDER BY name');
    $groups = all('SELECT g.id, g.name, b.name branch_name FROM service_groups g JOIN branches b ON b.id=g.branch_id ORDER BY b.name, g.sort_order, g.name');
    view('admin/service_edit', ['row' => $row, 'branches' => $branches, 'forms' => $forms, 'groups' => $groups]);
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
    // traduceri serviciu (cod | nume | descriere pe linie)
    $i18n = null;
    $rawI = trim((string)($_POST['svc_i18n'] ?? ''));
    if ($rawI !== '') {
        $map = [];
        foreach (preg_split('/\r?\n/', $rawI) as $ln) {
            $parts = array_map('trim', explode('|', $ln));
            $code = strtolower($parts[0] ?? '');
            if ($code === '' || ($parts[1] ?? '') === '') continue;
            $map[$code] = ['name'=>$parts[1], 'description'=>($parts[2] ?? '')];
        }
        if ($map) $i18n = json_encode($map, JSON_UNESCAPED_UNICODE);
    }
    // limiteaza valorile numerice ca sa nu se creeze servicii „rupte"
    // (eticheta peste 16 caractere, interval inversat, valori negative)
    $branchId = (int)($_POST['branch_id'] ?? 1);
    $prefix   = strtoupper(trim($_POST['prefix'] ?? 'A'));
    $numFrom  = max(1, (int)($_POST['num_from'] ?? 1));
    $numTo    = max($numFrom, (int)($_POST['num_to'] ?? 999));
    $pad      = min(10, max(1, (int)($_POST['pad_length'] ?? 3)));   // tickets.label e VARCHAR(16)
    $color    = trim($_POST['color'] ?? '#2563eb');                  // doar hex valid (anti-XSS prin culoarea afisata)
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $color = '#2563eb';
    $f = [
        'branch_id'=>$branchId, 'prefix'=>$prefix,
        'name'=>trim($_POST['name'] ?? ''),
        'description'=>trim($_POST['description'] ?? ''), 'color'=>$color,
        'status'=>(($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active'),
        'num_from'=>$numFrom, 'num_to'=>$numTo, 'pad_length'=>$pad,
        'include_zeros'=>isset($_POST['include_zeros'])?1:0, 'allow_priority'=>isset($_POST['allow_priority'])?1:0,
        'terminate_on_call'=>isset($_POST['terminate_on_call'])?1:0,
        'kpi_wait_sec'=>max(0,(int)($_POST['kpi_wait_sec'] ?? 600)), 'kpi_service_sec'=>max(0,(int)($_POST['kpi_service_sec'] ?? 300)),
        'max_queued'=>max(0,(int)($_POST['max_queued'] ?? 0)), 'max_per_day'=>max(0,(int)($_POST['max_per_day'] ?? 0)), 'sort_order'=>(int)($_POST['sort_order'] ?? 0),
        'active_hours'=>$ah, 'i18n'=>$i18n,
        'group_id'=>((int)($_POST['group_id'] ?? 0)) ?: null,
        'form_id'=>((int)($_POST['form_id'] ?? 0)) ?: null,
        'appt_enabled'=>isset($_POST['appt_enabled'])?1:0,
        'appt_slot_min'=>max(5,(int)($_POST['appt_slot_min'] ?? 15)),
        'appt_capacity'=>max(1,(int)($_POST['appt_capacity'] ?? 1)),
    ];
    if ($f['name'] === '' || $f['prefix'] === '') { flash('Nume si prefix obligatorii.', 'error'); redirect('admin/services' . ($id ? "/$id" : '/new')); }
    // prefix unic pe filiala (evita bilete ambigue cu acelasi prefix)
    $dup = (int) val('SELECT COUNT(*) FROM services WHERE branch_id=? AND prefix=? AND id<>?', [$branchId, $prefix, $id]);
    if ($dup > 0) { flash('Există deja un serviciu cu prefixul „'.$prefix.'" în această filială. Alege alt prefix.', 'error'); redirect('admin/services' . ($id ? "/$id" : '/new')); }
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($f)));
        q("UPDATE services SET $set WHERE id=?", array_merge(array_values($f), [$id]));
        flash('Serviciu actualizat.');
    } else {
        if (tenant_limit_reached('services')) { flash('Ai atins limita planului pentru servicii ('.tenant_limit('services').'). Contactează furnizorul pentru un pachet mai mare.', 'error'); redirect('admin/services'); }
        $cols = implode(',', array_keys($f)); $ph = implode(',', array_fill(0, count($f), '?'));
        q("INSERT INTO services ($cols) VALUES ($ph)", array_values($f));
        flash('Serviciu creat.');
    }
    audit($id?'update':'create','service',$id ?: insert_id());
    redirect('admin/services');
}

/* ----------------------- SERVICE GROUPS ----------------------- */
function admin_groups_list(): void {
    $rows = all('SELECT g.*, b.name branch_name,
                        (SELECT COUNT(*) FROM services s WHERE s.group_id=g.id) svc,
                        (SELECT COUNT(*) FROM service_groups c WHERE c.parent_id=g.id) subgroups
                 FROM service_groups g JOIN branches b ON b.id=g.branch_id ORDER BY b.name, g.sort_order, g.name');
    $branches = all('SELECT id,name FROM branches ORDER BY name');
    // serviciile fiecarei filiale (pentru afisarea structurii + atribuire rapida)
    $services = all('SELECT id,prefix,name,color,group_id,branch_id FROM services WHERE status="active" ORDER BY branch_id, sort_order, name');
    view('admin/groups', compact('rows','branches','services'));
}
function admin_groups_reorder(): void {
    csrf_check();
    $ids = input('ids', []);
    if (!is_array($ids) || !$ids) json_out(['ok' => false, 'error' => 'Lista goala'], 422);
    $pos = 0;
    foreach ($ids as $id) { $id = (int)$id; if ($id > 0) q('UPDATE service_groups SET sort_order = ? WHERE id = ?', [$pos++, $id]); }
    audit('reorder', 'groups');
    json_out(['ok' => true]);
}
function admin_group_save(): void {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $branch = (int)($_POST['branch_id'] ?? 1);
    $parent = (int)($_POST['parent_id'] ?? 0);
    // valideaza grupul parinte: aceeasi filiala, nu el insusi, fara cicluri (subgrup al propriului descendent)
    if ($parent > 0) {
        $pg = one('SELECT id, branch_id, parent_id FROM service_groups WHERE id=?', [$parent]);
        if (!$pg || (int)$pg['branch_id'] !== $branch || ($id && $parent === $id)) $parent = 0;
        else {
            $cur = $pg; $guard = 0;
            while ($cur && !empty($cur['parent_id']) && $guard++ < 30) {
                if ((int)$cur['parent_id'] === $id) { $parent = 0; break; }   // ciclu
                $cur = one('SELECT id, parent_id FROM service_groups WHERE id=?', [(int)$cur['parent_id']]);
            }
        }
    }
    $f = ['branch_id'=>$branch, 'name'=>trim($_POST['name'] ?? ''),
          'color'=>trim($_POST['color'] ?? '#64748b'), 'parent_id'=>($parent > 0 ? $parent : null),
          'sort_order'=>(int)($_POST['sort_order'] ?? 0)];
    if ($f['name'] === '') { flash('Numele grupului este obligatoriu.', 'error'); redirect('admin/groups'); }
    if ($id) {
        $set = implode(', ', array_map(fn($k)=>"$k=?", array_keys($f)));
        q("UPDATE service_groups SET $set WHERE id=?", array_merge(array_values($f), [$id]));
        flash('Grup actualizat.');
    } else {
        $cols = implode(',', array_keys($f)); $ph = implode(',', array_fill(0, count($f), '?'));
        q("INSERT INTO service_groups ($cols) VALUES ($ph)", array_values($f));
        flash('Grup creat.');
    }
    audit($id?'update':'create','group',$id ?: insert_id());
    redirect('admin/groups');
}
/** Atribuie rapid un serviciu unui grup (din pagina Grupuri). group_id=0 => scoate din grup. */
function admin_group_assign(): void {
    csrf_check();
    $sid = (int)($_POST['service_id'] ?? 0);
    $gid = (int)($_POST['group_id'] ?? 0);
    if ($sid <= 0) { flash('Serviciu invalid.', 'error'); redirect('admin/groups'); }
    // grupul trebuie sa fie in aceeasi filiala ca serviciul
    if ($gid > 0) {
        $sv = one('SELECT branch_id FROM services WHERE id=?', [$sid]);
        $g  = one('SELECT branch_id FROM service_groups WHERE id=?', [$gid]);
        if (!$sv || !$g || (int)$sv['branch_id'] !== (int)$g['branch_id']) { flash('Grupul nu e din filiala serviciului.', 'error'); redirect('admin/groups'); }
    }
    q('UPDATE services SET group_id=? WHERE id=?', [$gid ?: null, $sid]);
    audit('update', 'service', $sid, 'group_id='.$gid);
    flash('Serviciu mutat.');
    redirect('admin/groups');
}

/** Salveaza ordinea serviciilor (drag & drop): sort_order = pozitia in lista primita. */
function admin_services_reorder(): void {
    csrf_check();
    $ids = input('ids', []);
    if (!is_array($ids) || !$ids) json_out(['ok' => false, 'error' => 'Lista goala'], 422);
    $pos = 0;
    foreach ($ids as $id) { $id = (int)$id; if ($id > 0) q('UPDATE services SET sort_order = ? WHERE id = ?', [$pos++, $id]); }
    audit('reorder', 'services');
    json_out(['ok' => true]);
}

/* ----------------------- COUNTERS ----------------------- */
function admin_counters_list(): void {
    $rows = all('SELECT c.*, b.name AS branch_name, (SELECT COUNT(*) FROM counter_services cs WHERE cs.counter_id=c.id) svc_count FROM counters c JOIN branches b ON b.id=c.branch_id ORDER BY b.name, c.code');
    $branches = all('SELECT id, name FROM branches ORDER BY name');
    view('admin/counters', ['rows' => $rows, 'branches' => $branches]);
}
/** Export ghisee in CSV (cod,nume) — round-trip cu importul. */
function admin_counters_export(): void {
    $branch = (int)($_GET['branch'] ?? 0);
    $where = '1=1'; $args = [];
    if ($branch) { $where .= ' AND branch_id=?'; $args[] = $branch; }
    $tmpl = isset($_GET['template']);
    $rows = $tmpl ? [] : all("SELECT code, name FROM counters WHERE $where ORDER BY branch_id, code", $args);
    if (!$tmpl) audit('export', 'counters');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($tmpl ? 'sablon_ghisee.csv' : 'ghisee_' . date('Ymd_His') . '.csv') . '"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv_safe($out, ['cod', 'nume']);
    foreach ($rows as $r) fputcsv_safe($out, [$r['code'], $r['name']]);
    fclose($out); exit;
}
/** Import ghisee din CSV (cod,nume) pentru o filiala; sare peste codurile existente. */
function admin_counters_import(): void {
    csrf_check();
    $branch = (int)($_POST['branch_id'] ?? 0) ?: (int) val('SELECT id FROM branches ORDER BY id LIMIT 1');
    $csv = (string)($_POST['csv'] ?? '');
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
        $csv = (string) file_get_contents($_FILES['file']['tmp_name']);
    $rows = parse_counters_csv($csv);
    $existing = array_flip(array_map('strtoupper', array_column(all('SELECT code FROM counters WHERE branch_id=?', [$branch]), 'code')));
    $n = 0; $skipped = 0;
    $lim = tenant_limit('counters'); $cur = (int) val('SELECT COUNT(*) FROM counters'); $capped = false;
    foreach ($rows as $r) {
        if (isset($existing[strtoupper($r['code'])])) { $skipped++; continue; }
        if ($lim > 0 && $cur >= $lim) { $capped = true; break; }   // respecta limita de plan si la import
        q("INSERT INTO counters (branch_id, code, name, status, all_services) VALUES (?,?,?,'closed',1)", [$branch, $r['code'], $r['name']]);
        $existing[strtoupper($r['code'])] = 1; $n++; $cur++;
    }
    audit('import', 'counters', $branch, $n . ' ghisee');
    $msg = $n > 0 ? "$n ghisee importate." : 'Niciun ghiseu nou de importat.';
    if ($skipped) $msg .= " $skipped sarite (cod existent).";
    if ($capped) $msg .= ' Limita planului pentru ghisee a fost atinsa.';
    flash($msg, $n > 0 ? 'info' : 'error');
    redirect('admin/counters');
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
          'name'=>trim($_POST['name'] ?? ''), 'status'=>(in_array($_POST['status'] ?? 'closed', ['open','paused','closed'], true) ? $_POST['status'] : 'closed'),
          'cd_hint_idle'=>mb_substr(trim((string)($_POST['cd_hint_idle'] ?? '')), 0, 80),
          'cd_hint_serving'=>mb_substr(trim((string)($_POST['cd_hint_serving'] ?? '')), 0, 80),
          'all_services'=>isset($_POST['all_services'])?1:0, 'priority'=>(int)($_POST['priority'] ?? 0)];
    if ($f['code'] === '' || $f['name'] === '') { flash('Cod si nume obligatorii.', 'error'); redirect('admin/counters'); }
    // cod unic pe filiala (evita ghisee ambigue)
    if ((int) val('SELECT COUNT(*) FROM counters WHERE branch_id=? AND code=? AND id<>?', [$f['branch_id'], $f['code'], $id]) > 0) {
        flash('Există deja un ghișeu cu codul „'.$f['code'].'" în această filială.', 'error'); redirect('admin/counters');
    }
    if ($id) {
        $set = implode(', ', array_map(fn($k)=>"$k=?", array_keys($f)));
        q("UPDATE counters SET $set WHERE id=?", array_merge(array_values($f), [$id]));
    } else {
        if (tenant_limit_reached('counters')) { flash('Ai atins limita planului pentru ghișee ('.tenant_limit('counters').'). Contactează furnizorul pentru un pachet mai mare.', 'error'); redirect('admin/counters'); }
        $cols = implode(',', array_keys($f)); $ph = implode(',', array_fill(0, count($f), '?'));
        q("INSERT INTO counters ($cols) VALUES ($ph)", array_values($f)); $id = insert_id();
    }
    q('DELETE FROM counter_services WHERE counter_id=?', [$id]);
    if (!$f['all_services']) foreach (($_POST['services'] ?? []) as $sid) q('INSERT IGNORE INTO counter_services (counter_id,service_id) VALUES (?,?)', [$id,(int)$sid]);
    audit($id?'update':'create','counter',$id); flash('Ghiseu salvat.'); redirect('admin/counters');
}

/* ----------------------- USERS ----------------------- */
function admin_users_list(): void { view('admin/users', ['rows' => all('SELECT * FROM users ORDER BY name')]); }
function admin_user_form(?int $id): void {
    $counters = all('SELECT c.id, c.code, c.name, b.name branch_name FROM counters c JOIN branches b ON b.id=c.branch_id ORDER BY b.name, c.code');
    view('admin/user_edit', ['row' => $id ? one('SELECT * FROM users WHERE id=?', [$id]) : null, 'counters' => $counters]);
}
function admin_user_save(): void {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $role=$_POST['role']??'agent';
    if (!in_array($role, ['admin','manager','agent'], true)) $role = 'agent';   // whitelist (defense-in-depth)
    $active=isset($_POST['active'])?1:0; $pass=(string)($_POST['password']??'');
    $notify=isset($_POST['notify_browser'])?1:0;
    $allowed = implode(',', array_filter(array_map('intval', (array)($_POST['allowed_counters'] ?? [])))) ?: null;
    if ($name==='' || $email==='') { flash('Nume si email obligatorii.', 'error'); redirect('admin/users'); }
    if ($id) {
        if ($pass !== '') q('UPDATE users SET name=?,email=?,role=?,active=?,notify_browser=?,allowed_counters=?,password_hash=? WHERE id=?',
            [$name,$email,$role,$active,$notify,$allowed,password_hash($pass,PASSWORD_DEFAULT),$id]);
        else q('UPDATE users SET name=?,email=?,role=?,active=?,notify_browser=?,allowed_counters=? WHERE id=?', [$name,$email,$role,$active,$notify,$allowed,$id]);
    } else {
        if (tenant_limit_reached('users')) { flash('Ai atins limita planului pentru utilizatori ('.tenant_limit('users').'). Contactează furnizorul pentru un pachet mai mare.', 'error'); redirect('admin/users'); }
        if ($pass === '') { flash('Parola obligatorie la utilizator nou.', 'error'); redirect('admin/users/new'); }
        try { q('INSERT INTO users (name,email,role,active,notify_browser,allowed_counters,password_hash) VALUES (?,?,?,?,?,?,?)',
            [$name,$email,$role,$active,$notify,$allowed,password_hash($pass,PASSWORD_DEFAULT)]); }
        catch (Throwable $e) { flash('Email deja folosit.', 'error'); redirect('admin/users/new'); }
    }
    if ($id && isset($_POST['reset_2fa'])) { q('UPDATE users SET totp_secret=NULL, totp_enabled=0, totp_backup=NULL WHERE id=?', [$id]); audit('2fa_reset','user',$id); }
    audit($id?'update':'create','user',$id); flash('Utilizator salvat.'); redirect('admin/users');
}
/** Export operatori (CSV: nume,email,rol). NU exporta parole/hash-uri din motive de securitate. */
function admin_users_export(): void {
    $tmpl = isset($_GET['template']);
    $rows = $tmpl ? [] : all('SELECT name, email, role FROM users ORDER BY name');
    if (!$tmpl) audit('export', 'users');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($tmpl ? 'sablon_utilizatori.csv' : 'utilizatori_' . date('Ymd_His') . '.csv') . '"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    // sablonul include coloana 'parola' (necesara la import); exportul real NU contine parole/hash-uri
    fputcsv_safe($out, $tmpl ? ['nume', 'email', 'rol', 'parola'] : ['nume', 'email', 'rol']);
    foreach ($rows as $r) fputcsv_safe($out, [$r['name'], $r['email'], $r['role']]);
    fclose($out); exit;
}
/** Import operatori din CSV (nume,email,rol,parola); sare peste emailurile deja existente. */
function admin_users_import(): void {
    csrf_check();
    $csv = (string)($_POST['csv'] ?? '');
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
        $csv = (string) file_get_contents($_FILES['file']['tmp_name']);
    $rows = parse_users_csv($csv);
    $existing = array_flip(array_map('strtolower', array_column(all('SELECT email FROM users'), 'email')));
    $n = 0; $skipped = 0;
    $lim = tenant_limit('users'); $cur = (int) val('SELECT COUNT(*) FROM users'); $capped = false;
    foreach ($rows as $r) {
        if (isset($existing[$r['email']])) { $skipped++; continue; }
        if ($lim > 0 && $cur >= $lim) { $capped = true; break; }   // respecta limita de plan si la import
        try {
            q('INSERT INTO users (name,email,role,active,password_hash) VALUES (?,?,?,1,?)',
                [$r['name'], $r['email'], $r['role'], password_hash($r['password'], PASSWORD_DEFAULT)]);
            $existing[$r['email']] = 1; $n++; $cur++;
        } catch (Throwable $e) { $skipped++; }
    }
    audit('import', 'users', null, $n . ' utilizatori');
    $msg = $n > 0 ? "$n utilizatori importati." : 'Niciun utilizator nou de importat.';
    if ($skipped) $msg .= " $skipped sariti (email existent sau date invalide).";
    if ($capped) $msg .= ' Limita planului pentru utilizatori a fost atinsa.';
    flash($msg, $n > 0 ? 'info' : 'error');
    redirect('admin/users');
}

/* ----------------------- DEVICES ----------------------- */
function admin_devices_list(): void {
    $rows = all('SELECT d.*, b.name AS branch_name, (d.last_seen IS NOT NULL AND d.last_seen > (NOW() - INTERVAL 2 MINUTE)) AS online FROM devices d JOIN branches b ON b.id=d.branch_id ORDER BY b.name, d.type, d.name');
    view('admin/devices', ['rows' => $rows]);
}
/** Foaie printabila cu codurile QR + linkurile dispozitivelor (pentru instalare rapida). */
function admin_devices_qr(): void {
    $rows = all('SELECT d.*, b.name AS branch_name FROM devices d JOIN branches b ON b.id=d.branch_id ORDER BY b.name, d.type, d.name');
    view('admin/devices_qr', ['rows' => $rows]);
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
    // printarea in retea deschide o conexiune catre IP-ul configurat -> accepta doar un IP valid (anti-SSRF)
    if ($f['printer_mode'] === 'network' && $f['printer_ip'] !== '' && !filter_var($f['printer_ip'], FILTER_VALIDATE_IP)) {
        flash('IP imprimanta invalid (introdu o adresa IP, ex: 192.168.1.50).', 'error'); redirect('admin/devices');
    }
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
    audit($id?'update':'create','device',$id); flash('Dispozitiv salvat.'); redirect('admin/devices');
}

/* ----------------------- TICKETS ----------------------- */
/** Construieste filtrul (WHERE + args) pentru lista de bilete din parametrii GET. */
function admin_tickets_filter(): array {
    $date    = $_GET['date'] ?? date('Y-m-d');
    $status  = (string)($_GET['status'] ?? '');
    $service = (int)($_GET['service'] ?? 0);
    $fbranch = (int)($_GET['fbranch'] ?? 0);
    $qstr    = trim((string)($_GET['q'] ?? ''));

    $where = 'DATE(t.issued_at)=?'; $args = [$date];
    $valid = ['waiting','called','serving','served','no_show','cancelled','transferred'];
    if (in_array($status, $valid, true)) { $where .= ' AND t.status=?'; $args[] = $status; }
    if ($service) { $where .= ' AND t.service_id=?'; $args[] = $service; }
    if ($fbranch) { $where .= ' AND t.branch_id=?'; $args[] = $fbranch; }
    if ($qstr !== '') { $where .= ' AND t.label LIKE ?'; $args[] = '%'.$qstr.'%'; }
    return [$where, $args, compact('date','status','service','fbranch','qstr')];
}

function admin_tickets(): void {
    [$where, $args, $f] = admin_tickets_filter();
    $rows = all("SELECT t.*, s.name service_name, s.color, c.code counter_code
                 FROM tickets t JOIN services s ON s.id=t.service_id
                 LEFT JOIN counters c ON c.id=t.counter_id
                 WHERE $where ORDER BY t.issued_at DESC LIMIT 500", $args);
    $branches = all('SELECT id,name FROM branches ORDER BY name');
    $services = all('SELECT id,prefix,name FROM services ORDER BY sort_order, name');
    $date=$f['date']; $status=$f['status']; $service=$f['service']; $fbranch=$f['fbranch']; $qstr=$f['qstr'];
    view('admin/tickets', compact('rows','date','branches','services','status','service','fbranch','qstr'));
}

/** Export CSV al biletelor filtrate (Excel-friendly: BOM UTF-8 + separator ;). */
function admin_tickets_export(): void {
    [$where, $args, $f] = admin_tickets_filter();
    audit('export', 'tickets', $f['date']);
    $rows = all("SELECT t.label, t.status, s.name service_name, c.code counter_code, b.name branch_name,
                        t.priority, t.issued_at, t.called_at, t.served_at
                 FROM tickets t JOIN services s ON s.id=t.service_id
                 LEFT JOIN counters c ON c.id=t.counter_id
                 LEFT JOIN branches b ON b.id=t.branch_id
                 WHERE $where ORDER BY t.issued_at ASC LIMIT 20000", $args);
    $statusRo = ['waiting'=>'In asteptare','called'=>'Chemat','serving'=>'In servire','served'=>'Finalizat',
                 'no_show'=>'Neprezentat','cancelled'=>'Anulat','transferred'=>'Transferat'];
    $secs = fn($a,$b) => ($a && $b) ? max(0, strtotime($b) - strtotime($a)) : null;
    $mmss = function($s){ if ($s === null) return ''; return sprintf('%d:%02d', intdiv($s,60), $s%60); };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bilete_' . $f['date'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM pentru diacritice corecte in Excel
    $head = ['Bon','Serviciu','Filiala','Ghiseu','Status','Prioritar','Emis','Chemat','Finalizat','Asteptare','Servire'];
    fputcsv_safe($out, $head, ';');
    foreach ($rows as $r) {
        fputcsv_safe($out, [
            $r['label'],
            $r['service_name'],
            $r['branch_name'] ?? '',
            $r['counter_code'] ?? '',
            $statusRo[$r['status']] ?? $r['status'],
            !empty($r['priority']) ? 'da' : '',
            $r['issued_at'],
            $r['called_at'] ?? '',
            $r['served_at'] ?? '',
            $mmss($secs($r['issued_at'], $r['called_at'])),
            $mmss($secs($r['called_at'], $r['served_at'])),
        ], ';');
    }
    fclose($out);
    exit;
}

/** Detaliu bilet: ciclul de viata complet (emis → chemat → servit), ghiseu, operator, formular. */
function admin_ticket_detail(int $id): void {
    $t = one("SELECT t.*, s.name service_name, s.prefix, s.color, s.kpi_wait_sec, s.kpi_service_sec,
                     b.name branch_name, c.code counter_code, c.name counter_name,
                     tc.code target_code, u.name agent_name
              FROM tickets t JOIN services s ON s.id=t.service_id
              LEFT JOIN branches b ON b.id=t.branch_id
              LEFT JOIN counters c ON c.id=t.counter_id
              LEFT JOIN counters tc ON tc.id=t.target_counter_id
              LEFT JOIN users u ON u.id=t.agent_id
              WHERE t.id=?", [$id]);
    if (!$t) { http_response_code(404); echo 'Bilet inexistent.'; return; }
    view('admin/ticket_detail', ['t' => $t]);
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
    audit('reset','tickets', $branch ?: 'all');
    flash('Bonuri sterse complet: coada, statisticile si numerotarea au fost resetate de la 0.');
    redirect('admin/tickets');
}

/* ----------------------- FEEDBACK ----------------------- */
function admin_feedback_list(): void {
    $page = max(1, (int)($_GET['p'] ?? 1)); $per = 50; $off = ($page-1)*$per;
    $rating = (int)($_GET['rating'] ?? 0);
    $where = '1=1'; $args = [];
    if ($rating >= 1 && $rating <= 5) { $where .= ' AND f.rating=?'; $args[] = $rating; }
    $total = (int) val("SELECT COUNT(*) FROM feedback f WHERE $where", $args);
    $rows  = all("SELECT f.*, b.name branch_name, t.label ticket_label, s.name service_name
                  FROM feedback f LEFT JOIN branches b ON b.id=f.branch_id
                  LEFT JOIN tickets t ON t.id=f.ticket_id LEFT JOIN services s ON s.id=t.service_id
                  WHERE $where ORDER BY f.created_at DESC LIMIT $per OFFSET $off", $args);
    $stat = one("SELECT COUNT(*) n, AVG(rating) avg FROM feedback") ?: ['n'=>0,'avg'=>null];
    view('admin/feedback', compact('rows','page','per','total','rating','stat'));
}
/** Export CSV al evaluarilor (respecta filtrul de rating). */
function admin_feedback_export(): void {
    $rating = (int)($_GET['rating'] ?? 0);
    $where = '1=1'; $args = [];
    if ($rating >= 1 && $rating <= 5) { $where .= ' AND f.rating=?'; $args[] = $rating; }
    audit('export', 'feedback');
    $rows = all("SELECT f.created_at, f.rating, f.comment, b.name branch_name, t.label ticket_label
                 FROM feedback f LEFT JOIN branches b ON b.id=f.branch_id LEFT JOIN tickets t ON t.id=f.ticket_id
                 WHERE $where ORDER BY f.created_at DESC LIMIT 50000", $args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="feedback_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv_safe($out, ['Data/ora','Nota','Comentariu','Filiala','Bon'], ';');
    foreach ($rows as $r)
        fputcsv_safe($out, [$r['created_at'], $r['rating'], $r['comment'] ?? '', $r['branch_name'] ?? '', $r['ticket_label'] ?? ''], ';');
    fclose($out);
    exit;
}

/* ----------------------- CAUTARE GLOBALA (Ctrl+K) ----------------------- */
function admin_global_search(): void {
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) json_out(['ok' => true, 'results' => []]);
    $like = '%' . $q . '%';
    $out = [];
    $add = function(string $grp, string $label, string $url, string $sub = '') use (&$out) {
        $out[] = ['group' => $grp, 'label' => $label, 'url' => $url, 'sub' => $sub];
    };
    if (can('services'))
        foreach (all("SELECT id, prefix, name FROM services WHERE name LIKE ? OR prefix LIKE ? LIMIT 5", [$like, $like]) as $r)
            $add('Servicii', $r['prefix'].' · '.$r['name'], url('admin/services/'.$r['id']));
    if (can('counters'))
        foreach (all("SELECT id, code, name FROM counters WHERE name LIKE ? OR code LIKE ? LIMIT 5", [$like, $like]) as $r)
            $add('Ghisee', $r['code'].' · '.$r['name'], url('admin/counters/'.$r['id']));
    if (can('branches'))
        foreach (all("SELECT id, name, city FROM branches WHERE name LIKE ? OR city LIKE ? LIMIT 5", [$like, $like]) as $r)
            $add('Filiale', $r['name'], url('admin/branches/'.$r['id']), $r['city'] ?? '');
    if (can('devices'))
        foreach (all("SELECT id, name, type, connection_key FROM devices WHERE name LIKE ? OR connection_key LIKE ? LIMIT 5", [$like, $like]) as $r)
            $add('Dispozitive', $r['name'], url('admin/devices/'.$r['id']), $r['type'].' · '.$r['connection_key']);
    if (can('users'))
        foreach (all("SELECT id, name, email FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 5", [$like, $like]) as $r)
            $add('Utilizatori', $r['name'], url('admin/users/'.$r['id']), $r['email']);
    if (can('tickets'))
        foreach (all("SELECT label, DATE(issued_at) d FROM tickets WHERE label LIKE ? ORDER BY issued_at DESC LIMIT 5", [$like]) as $r)
            $add('Bilete', $r['label'], url('admin/tickets').'?date='.$r['d'].'&q='.rawurlencode($r['label']), $r['d']);
    json_out(['ok' => true, 'results' => array_slice($out, 0, 20)]);
}

/* ----------------------- SECURITATE (2FA) ----------------------- */
function admin_security_page(): void {
    $u = current_user();
    $row = one('SELECT totp_enabled, totp_backup FROM users WHERE id=?', [$u['id']]) ?: [];
    $enabled = (int)($row['totp_enabled'] ?? 0) === 1;
    $backupLeft = $enabled ? count(json_decode($row['totp_backup'] ?? '[]', true) ?: []) : 0;
    $secret = '';
    if (!$enabled) {
        if (empty($_SESSION['2fa_setup_secret'])) $_SESSION['2fa_setup_secret'] = totp_secret();
        $secret = $_SESSION['2fa_setup_secret'];
    }
    // codurile noi se afiseaza O SINGURA DATA (puse in sesiune la activare/regenerare)
    $newCodes = $_SESSION['2fa_new_codes'] ?? null;
    unset($_SESSION['2fa_new_codes']);
    $force2fa = setting('force_2fa_admin', '0') === '1';
    view('admin/security', compact('u', 'enabled', 'secret', 'backupLeft', 'newCodes', 'force2fa'));
}
function admin_security_save(): void {
    csrf_check();
    $u = current_user();
    $act = $_POST['act'] ?? '';
    if ($act === 'enable') {
        $secret = (string)($_SESSION['2fa_setup_secret'] ?? '');
        // foloseste totp_match_slice (nu doar verify) ca sa CONSUMAM slotul codului de setup:
        // altfel acelasi cod ar fi acceptat inca o data la prima logare (anti-replay).
        $slice = $secret !== '' ? totp_match_slice($secret, (string)($_POST['code'] ?? '')) : null;
        if ($slice !== null) {
            [$plain, $hashes] = totp_backup_generate();
            q('UPDATE users SET totp_secret=?, totp_enabled=1, totp_backup=?, totp_last_slice=? WHERE id=?',
              [$secret, json_encode($hashes), $slice, $u['id']]);
            unset($_SESSION['2fa_setup_secret']);
            $_SESSION['2fa_new_codes'] = $plain;
            audit('2fa_enabled', 'user', $u['id']);
            flash('Autentificarea in doi pasi a fost activata. Salveaza codurile de recuperare de mai jos!');
        } else { flash('Cod incorect. Verifica ora telefonului si mai incearca.', 'error'); }
    } elseif ($act === 'disable') {
        q('UPDATE users SET totp_secret=NULL, totp_enabled=0, totp_backup=NULL WHERE id=?', [$u['id']]);
        audit('2fa_disabled', 'user', $u['id']);
        flash('Autentificarea in doi pasi a fost dezactivata.');
    } elseif ($act === 'regen_codes') {
        if ((int) val('SELECT totp_enabled FROM users WHERE id=?', [$u['id']]) === 1) {
            [$plain, $hashes] = totp_backup_generate();
            q('UPDATE users SET totp_backup=? WHERE id=?', [json_encode($hashes), $u['id']]);
            $_SESSION['2fa_new_codes'] = $plain;
            audit('2fa_codes_regen', 'user', $u['id']);
            flash('Coduri de recuperare noi generate. Cele vechi nu mai sunt valabile.');
        }
    } elseif ($act === 'password') {
        $res = change_own_password((int)$u['id'], (string)($_POST['cur_pass'] ?? ''),
            (string)($_POST['new_pass'] ?? ''), (string)($_POST['new_pass2'] ?? ''));
        if ($res['ok']) { audit('password_change', 'user', $u['id']); flash('Parola a fost schimbata.'); }
        else { flash($res['error'], 'error'); }
    } elseif ($act === 'policy' && $u['role'] === 'admin') {
        set_setting('force_2fa_admin', isset($_POST['force_2fa_admin']) ? '1' : '0');
        audit('update', 'security_policy');
        flash('Politica de securitate salvata.');
    }
    redirect('admin/security');
}

/* ----------------------- GDPR: drepturile persoanei vizate ----------------------- */
/**
 * Cauta toate datele personale legate de un email si/sau telefon, pe toate
 * tabelele care contin asa ceva (programari, lista de asteptare, bilete).
 * Returneaza ['appointments'=>[...], 'waitlist'=>[...], 'tickets'=>[...]].
 */
function gdpr_find(string $email, string $phone): array {
    $email = trim($email); $phone = trim($phone);
    $out = ['appointments' => [], 'waitlist' => [], 'tickets' => []];
    if ($email === '' && $phone === '') return $out;
    $apW = []; $apA = [];
    if ($email !== '') { $apW[] = 'customer_email = ?'; $apA[] = $email; }
    if ($phone !== '') { $apW[] = 'customer_phone = ?'; $apA[] = $phone; }
    $cond = implode(' OR ', $apW);
    $out['appointments'] = all("SELECT id, branch_id, service_id, customer_name, customer_phone, customer_email, slot_start, status, consent_at, consent_ip, created_at
                                FROM appointments WHERE $cond ORDER BY id DESC", $apA);
    $out['waitlist'] = all("SELECT id, service_id, customer_name, customer_email, customer_phone, slot_start, created_at
                            FROM appointment_waitlist WHERE $cond ORDER BY id DESC", $apA);
    if ($phone !== '')
        $out['tickets'] = all("SELECT id, branch_id, service_id, label, customer_phone, status, issued_at
                               FROM tickets WHERE customer_phone = ? ORDER BY id DESC", [$phone]);
    return $out;
}
/**
 * Anonimizeaza datele personale legate de email/telefon (dreptul de a fi uitat).
 * Pastreaza randurile pentru statistici, dar fara date de identificare.
 * Returneaza numarul de inregistrari afectate pe categorie.
 */
function gdpr_erase(string $email, string $phone): array {
    $email = trim($email); $phone = trim($phone);
    $n = ['appointments' => 0, 'waitlist' => 0, 'tickets' => 0];
    if ($email === '' && $phone === '') return $n;
    $apW = []; $apA = [];
    if ($email !== '') { $apW[] = 'customer_email = ?'; $apA[] = $email; }
    if ($phone !== '') { $apW[] = 'customer_phone = ?'; $apA[] = $phone; }
    $cond  = implode(' OR ', $apW);
    $condA = implode(' OR ', array_map(fn($c) => 'a.' . $c, $apW));   // acelasi filtru, calificat pentru JOIN
    // biletele LEGATE de programarile vizate (acopera cautarea doar dupa email, fiindca tickets n-are email)
    $linked = q("UPDATE tickets t JOIN appointments a ON a.ticket_id = t.id
                 SET t.customer_phone=NULL, t.form_data=NULL WHERE $condA", $apA)->rowCount();
    // programari: anonimizeaza identificarea + IP-ul de consimtamant + jetonul public (PII)
    $n['appointments'] = q("UPDATE appointments SET customer_name=NULL, customer_phone=NULL, customer_email=NULL, consent_ip=NULL, public_token=NULL WHERE $cond", $apA)->rowCount();
    // lista de asteptare: efemera (customer_email e NOT NULL) -> stergem randurile
    $n['waitlist'] = q("DELETE FROM appointment_waitlist WHERE $cond", $apA)->rowCount();
    // bilete dupa telefon: anonimizeaza telefonul + raspunsurile de formular
    if ($phone !== '')
        $n['tickets'] = q("UPDATE tickets SET customer_phone=NULL, form_data=NULL WHERE customer_phone = ?", [$phone])->rowCount();
    $n['tickets'] += $linked;
    return $n;
}
function admin_gdpr_page(): void {
    $email = trim((string)($_GET['q_email'] ?? ''));
    $phone = trim((string)($_GET['q_phone'] ?? ''));
    $found = ($email !== '' || $phone !== '') ? gdpr_find($email, $phone) : null;
    if ($found !== null) audit('gdpr_search', 'subject', null, trim($email . ' ' . $phone));
    view('admin/gdpr', compact('email', 'phone', 'found'));
}
function admin_gdpr_export(): void {
    csrf_check();
    $email = trim((string)($_POST['q_email'] ?? ''));
    $phone = trim((string)($_POST['q_phone'] ?? ''));
    if ($email === '' && $phone === '') { flash('Completeaza email sau telefon.', 'error'); redirect('admin/gdpr'); }
    $data = gdpr_find($email, $phone);
    audit('gdpr_export', 'subject', null, trim($email . ' ' . $phone));
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="date-personale_' . date('Ymd_His') . '.json"');
    echo json_encode(['subject' => ['email' => $email, 'phone' => $phone], 'exported_at' => date('c'), 'data' => $data],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function admin_gdpr_erase(): void {
    csrf_check();
    $email = trim((string)($_POST['q_email'] ?? ''));
    $phone = trim((string)($_POST['q_phone'] ?? ''));
    if ($email === '' && $phone === '') { flash('Completeaza email sau telefon.', 'error'); redirect('admin/gdpr'); }
    $n = gdpr_erase($email, $phone);
    audit('gdpr_erase', 'subject', null, trim($email . ' ' . $phone) . ' → ' . json_encode($n));
    $total = array_sum($n);
    flash($total > 0
        ? "Date anonimizate: {$n['appointments']} programari, {$n['waitlist']} lista de asteptare, {$n['tickets']} bilete."
        : 'Nicio inregistrare gasita pentru aceste date.', $total > 0 ? 'info' : 'error');
    redirect('admin/gdpr');
}

/* ----------------------- BACKUP BAZA DE DATE ----------------------- */
function admin_db_backup(): void {
    csrf_check();
    audit('backup', 'database');
    while (ob_get_level() > 0) @ob_end_clean();    // dump direct, fara buffering (fisiere mari)
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup_' . date('Ymd_His') . '.sql"');
    db_dump_write(function (string $s) { echo $s; });
    exit;
}
/** Ruleaza un backup pe server (scris in backups/) + curata vechile copii. */
function admin_backup_run(): void {
    csrf_check();
    $name = backup_to_file();
    if ($name === '') { flash('Backup eșuat: nu pot scrie în folderul backups/ (verifică permisiunile).', 'error'); redirect('admin/settings'); }
    $pruned = backup_prune((int) setting('backup_keep', '14'));
    audit('backup', 'database', null, $name . ($pruned ? " (sterse $pruned vechi)" : ''));
    flash("Backup creat pe server: $name" . ($pruned ? " · $pruned copii vechi șterse" : ''));
    redirect('admin/settings');
}
/** Descarca un backup existent din backups/ (nume validat strict, fara traversare de cai). */
function admin_backup_download(): void {
    $name = basename((string)($_GET['file'] ?? ''));
    if (!preg_match('/^backup_\d{8}_\d{6}\.sql$/', $name)) { http_response_code(404); echo 'Fisier inexistent.'; return; }
    $path = backup_dir() . '/' . $name;
    if (!is_file($path)) { http_response_code(404); echo 'Fisier inexistent.'; return; }
    audit('download', 'backup', null, $name);
    while (ob_get_level() > 0) @ob_end_clean();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . (string)filesize($path));
    readfile($path);
    exit;
}
/** Sterge un backup existent (nume validat strict). */
function admin_backup_delete(): void {
    csrf_check();
    $name = basename((string)($_POST['file'] ?? ''));
    if (preg_match('/^backup_\d{8}_\d{6}\.sql$/', $name)) {
        $path = backup_dir() . '/' . $name;
        if (is_file($path) && @unlink($path)) { audit('delete', 'backup', null, $name); flash('Backup șters.'); }
        else flash('Nu am putut șterge fișierul.', 'error');
    }
    redirect('admin/settings');
}

/* ----------------------- PREGATIRE PRODUCTIE: stergerea datelor de test ----------------------- */
/**
 * Sterge datele OPERATIONALE (de test) la trecerea in productie, pastrand TOATA configuratia
 * (filiale, servicii, ghisee, utilizatori, dispozitive, setari, formulare, zile inchise, media).
 * Returneaza numarul de randuri sterse pe tabel. Tabelele sunt o lista alba (fara interpolare nesigura).
 */
function reset_operational_data(): array {
    $pdo = db();
    $tables = ['tickets','ticket_sequences','appointments','appointment_waitlist',
               'feedback','counter_sessions','user_status_log','webhook_log'];   // audit_log se pastreaza (jurnal de securitate)
    $n = [];
    try { $pdo->exec('SET FOREIGN_KEY_CHECKS=0'); } catch (Throwable $e) {}
    foreach ($tables as $t) { try { $n[$t] = (int) $pdo->exec("DELETE FROM `$t`"); } catch (Throwable $e) { $n[$t] = 0; } }
    try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $e) {}
    // toti operatorii devin offline (sesiunile/biletele au disparut)
    try { $pdo->exec("UPDATE users SET work_status='offline', last_seen=NULL"); } catch (Throwable $e) {}
    return $n;
}
function admin_reset_data(): void {
    csrf_check();
    if (mb_strtoupper(trim((string)($_POST['confirm'] ?? ''))) !== 'STERGE') {
        flash('Confirmare incorecta. Scrie STERGE (cu majuscule) pentru a continua.', 'error'); redirect('admin/settings');
    }
    $bk = '';
    try { $bk = backup_to_file(); backup_prune((int) setting('backup_keep', '14')); } catch (Throwable $e) {}
    $n = reset_operational_data();
    $total = array_sum($n);
    audit('reset', 'operational_data', null, ($bk !== '' ? "backup=$bk; " : 'fara backup; ') . json_encode($n));
    flash("Date de test sterse: $total inregistrari (bilete, programari, feedback, sesiuni)."
        . ($bk !== '' ? " Backup de siguranta creat: $bk." : ' ATENTIE: nu am putut crea backup automat — verifica permisiunile folderului backups/.'),
        $bk !== '' ? 'info' : 'error');
    redirect('admin/settings');
}

/* ----------------------- AUDIT LOG ----------------------- */
/** Construieste filtrul (WHERE + args) pentru jurnalul de audit din parametrii GET. */
function admin_audit_filter(): array {
    $action = trim((string)($_GET['action'] ?? ''));
    $qstr   = trim((string)($_GET['q'] ?? ''));
    $from   = trim((string)($_GET['from'] ?? ''));
    $to     = trim((string)($_GET['to'] ?? ''));
    $where = '1=1'; $args = [];
    if ($action !== '' && preg_match('/^[a-z0-9_]+$/i', $action)) { $where .= ' AND action=?'; $args[] = $action; }
    if ($qstr !== '') { $where .= ' AND (user_name LIKE ? OR details LIKE ? OR entity LIKE ?)'; $like='%'.$qstr.'%'; array_push($args,$like,$like,$like); }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where .= ' AND created_at >= ?'; $args[] = $from.' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where .= ' AND created_at <= ?'; $args[] = $to.' 23:59:59'; }
    return [$where, $args, compact('action','qstr','from','to')];
}

function admin_audit_list(): void {
    [$where, $args, $f] = admin_audit_filter();
    $page = max(1, (int)($_GET['p'] ?? 1)); $per = 80; $off = ($page-1)*$per;
    $total = (int) val("SELECT COUNT(*) FROM audit_log WHERE $where", $args);
    $rows  = all("SELECT * FROM audit_log WHERE $where ORDER BY id DESC LIMIT $per OFFSET $off", $args);
    $actions = array_column(all("SELECT DISTINCT action FROM audit_log ORDER BY action"), 'action');
    $action=$f['action']; $qstr=$f['qstr']; $from=$f['from']; $to=$f['to'];
    view('admin/audit', compact('rows','page','per','total','actions','action','qstr','from','to'));
}

/** Export CSV al jurnalului de audit filtrat (BOM UTF-8, separator ;). */
function admin_audit_export(): void {
    [$where, $args, $f] = admin_audit_filter();
    audit('export', 'audit_log');
    $rows = all("SELECT created_at, user_name, action, entity, entity_id, details, ip
                 FROM audit_log WHERE $where ORDER BY id DESC LIMIT 50000", $args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv_safe($out, ['Data/ora','Utilizator','Actiune','Obiect','ID','Detalii','IP'], ';');
    foreach ($rows as $r) {
        fputcsv_safe($out, [$r['created_at'], $r['user_name'] ?? '', $r['action'], $r['entity'] ?? '',
                       $r['entity_id'] ?? '', $r['details'] ?? '', $r['ip'] ?? ''], ';');
    }
    fclose($out);
    exit;
}

/* ----------------------- API & WEBHOOKS ----------------------- */
function admin_api_page(): void {
    if (setting('api_key', '') === '') set_setting('api_key', bin2hex(random_bytes(24)));
    if (setting('cron_token', '') === '') set_setting('cron_token', bin2hex(random_bytes(16)));
    view('admin/api');
}
function admin_api_save(): void {
    csrf_check();
    if (isset($_POST['regen'])) { set_setting('api_key', bin2hex(random_bytes(24))); audit('regenerate','api_key'); flash('Cheie API regenerata.'); redirect('admin/api'); }
    set_setting('webhook_url', trim((string)($_POST['webhook_url'] ?? '')));
    set_setting('webhook_secret', trim((string)($_POST['webhook_secret'] ?? '')));
    $valid = ['ticket.created','ticket.called','ticket.serving','ticket.served','ticket.no_show','ticket.cancelled','ticket.transferred','ticket.recalled',
              'appointment.created','appointment.cancelled','appointment.checked_in','appointment.rescheduled','appointment.no_show','sla.breach','feedback.low'];
    $evs = array_values(array_intersect($valid, (array)($_POST['webhook_events'] ?? [])));
    set_setting('webhook_events', implode(',', $evs));
    audit('update','webhook');
    flash('Setari API salvate.'); redirect('admin/api');
}
/** Trimite un webhook de test ('ping') si raporteaza rezultatul (AJAX). */
function admin_api_test_webhook(): void {
    csrf_check();
    audit('test', 'webhook');
    json_out(test_webhook());
}
/** Export CSV al jurnalului de livrari webhook. */
function admin_webhook_log_export(): void {
    $rows = all('SELECT created_at, event, ok, status_code, url, error FROM webhook_log ORDER BY id DESC');
    audit('export', 'webhook_log');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="webhook_log_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv_safe($out, ['data', 'eveniment', 'ok', 'cod_http', 'url', 'eroare']);
    foreach ($rows as $r) fputcsv_safe($out, [$r['created_at'], $r['event'], $r['ok'] ? 'da' : 'nu', $r['status_code'], $r['url'], $r['error']]);
    fclose($out); exit;
}

/* ----------------------- SETTINGS ----------------------- */
function admin_settings_form(): void { view('admin/settings'); }
/** Chei excluse la export/import config (specifice instantei / sensibile / runtime). */
function settings_export_denylist(): array {
    return ['schema_version','api_key','cron_token','webhook_secret','onboarding_dismissed',
            'last_daily_report','sla_alert_last','backup_last','cron_last_run'];
}
/** Export config (settings) ca JSON — pentru backup sau clonare pe alta instanta. */
function admin_settings_export(): void {
    $deny = settings_export_denylist();
    $out = [];
    foreach (all('SELECT k, v FROM settings ORDER BY k') as $r)
        if (!in_array($r['k'], $deny, true)) $out[$r['k']] = $r['v'];
    audit('export', 'settings');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="config_' . date('Ymd_His') . '.json"');
    echo json_encode(['brand' => setting('brand_name',''), 'exported_at' => date('c'), 'settings' => $out],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
/** Import config dintr-un JSON exportat (fisier sau text). Suprascrie cheile (mai putin cele excluse). */
function admin_settings_import(): void {
    csrf_check();
    $raw = '';
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
        $raw = (string) file_get_contents($_FILES['file']['tmp_name']);
    elseif (trim((string)($_POST['json'] ?? '')) !== '')
        $raw = (string) $_POST['json'];
    if ($raw === '') { flash('Adauga un fisier .json sau lipeste continutul.', 'error'); redirect('admin/settings'); }
    $data = json_decode($raw, true);
    $kv = is_array($data['settings'] ?? null) ? $data['settings'] : (is_array($data) ? $data : null);
    if (!is_array($kv)) { flash('Fisier invalid: nu contine setari.', 'error'); redirect('admin/settings'); }
    $deny = settings_export_denylist();
    $n = 0;
    foreach ($kv as $k => $v) {
        $k = (string)$k;
        if ($k === '' || in_array($k, $deny, true) || !is_scalar($v)) continue;
        set_setting($k, (string)$v); $n++;
    }
    audit('import', 'settings', null, $n.' chei');
    flash($n > 0 ? ("Configuratie importata ($n setari). Verifica branding/texte.") : 'Nicio setare valida de importat.', $n>0?'info':'error');
    redirect('admin/settings');
}
function admin_settings_save(): void {
    csrf_check();
    $keys = ['brand_name','accent_color','brand_logo','language','display_voice','display_repeat',
             'ticket_footer','ticket_header','dispenser_title','org_name','ticket_num_size','priority_escalate_min','max_recalls',
             'alert_called','alert_transfer','alert_delay','near_turn_alert','notice_text','notice_until',
             'mail_from','mail_from_name','smtp_host','smtp_port','smtp_user','smtp_pass','daily_report_to','retention_months',
             'sla_alert_to','sla_alert_min','sla_alert_cooldown_min','auto_close_min','auto_offline_min','appt_noshow_min','feedback_alert_rating',
             'legal_operator','legal_address','legal_email','privacy_url','terms_url','legal_extra','legal_privacy_text','legal_terms_text','legal_slug_privacy','legal_slug_terms','backup_keep','admin_idle_min',
             'cd_hint_idle','cd_hint_serving'];
    foreach ($keys as $k) if (isset($_POST[$k])) set_setting($k, trim((string)$_POST[$k]));
    // backup_auto_enabled se controleaza din panoul landlord (backup = operatiune de furnizor), nu de aici
    if (isset($_POST['smtp_secure']) && in_array($_POST['smtp_secure'], ['tls','ssl','none'], true)) set_setting('smtp_secure', $_POST['smtp_secure']);
    set_setting('mail_enabled', isset($_POST['mail_enabled']) ? '1' : '0');
    set_setting('reminder_enabled', isset($_POST['reminder_enabled']) ? '1' : '0');
    set_setting('daily_report_enabled', isset($_POST['daily_report_enabled']) ? '1' : '0');
    set_setting('sla_alert_enabled', isset($_POST['sla_alert_enabled']) ? '1' : '0');
    set_setting('display_say_number', isset($_POST['display_say_number']) ? '1' : '0');
    set_setting('display_say_counter', isset($_POST['display_say_counter']) ? '1' : '0');
    set_setting('counter_voice', isset($_POST['counter_voice']) ? '1' : '0');
    set_setting('virtual_enabled', isset($_POST['virtual_enabled']) ? '1' : '0');
    set_setting('mod_booking', isset($_POST['mod_booking']) ? '1' : '0');
    set_setting('mod_feedback', isset($_POST['mod_feedback']) ? '1' : '0');
    set_setting('mod_concierge', isset($_POST['mod_concierge']) ? '1' : '0');
    set_setting('mod_public_status', isset($_POST['mod_public_status']) ? '1' : '0');
    set_setting('release_on_pause', isset($_POST['release_on_pause']) ? '1' : '0');
    set_setting('ticket_show_position', isset($_POST['ticket_show_position']) ? '1' : '0');
    set_setting('ticket_show_datetime', isset($_POST['ticket_show_datetime']) ? '1' : '0');
    set_setting('ticket_show_qr', isset($_POST['ticket_show_qr']) ? '1' : '0');
    // limbi dispenser (romana mereu inclusa)
    $langOk = array_keys(disp_lang_meta());
    $langs = array_values(array_intersect($langOk, (array)($_POST['dispenser_langs'] ?? [])));
    if (!in_array('ro', $langs, true)) array_unshift($langs, 'ro');
    set_setting('dispenser_langs', implode(',', array_unique($langs)));
    audit('update','settings');

    // email de test (dupa salvare, cu valorile proaspete)
    if (isset($_POST['mail_test'])) {
        $to = trim((string)($_POST['mail_test_to'] ?? '')) ?: (string)(current_user()['email'] ?? '');
        if ($to === '') { flash('Completeaza adresa pentru emailul de test.', 'error'); redirect('admin/settings'); }
        if (!mail_enabled()) { flash('Setari salvate, dar trimiterea de emailuri nu este activata (bifeaza optiunea).', 'error'); redirect('admin/settings'); }
        $ok = send_mail($to, 'Test email — ' . setting('brand_name', 'Bon de ordine'),
            mail_template('Functioneaza!', '<p>Acesta este un email de test trimis din setarile aplicatiei <strong>Bon de ordine</strong>.</p><p>Daca il citesti, configurarea emailului este corecta.</p>'));
        flash($ok ? ('Setari salvate. Email de test trimis catre ' . $to . '.')
                  : 'Setari salvate, dar emailul de test NU a putut fi trimis. Verifica host/port/user/parola SMTP (sau lasa hostul gol pentru mail() de pe server).', $ok ? 'info' : 'error');
        redirect('admin/settings');
    }
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
/** Export filiale (CSV: nume,oras,adresa). */
function admin_branches_export(): void {
    $tmpl = isset($_GET['template']);
    $rows = $tmpl ? [] : all('SELECT name, city, address FROM branches ORDER BY name');
    if (!$tmpl) audit('export', 'branches');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($tmpl ? 'sablon_filiale.csv' : 'filiale_' . date('Ymd_His') . '.csv') . '"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv_safe($out, ['nume', 'oras', 'adresa']);
    foreach ($rows as $r) fputcsv_safe($out, [$r['name'], $r['city'], $r['address']]);
    fclose($out); exit;
}
/** Import filiale din CSV (nume,oras,adresa); sare peste numele deja existente. */
function admin_branches_import(): void {
    csrf_check();
    $csv = (string)($_POST['csv'] ?? '');
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
        $csv = (string) file_get_contents($_FILES['file']['tmp_name']);
    $rows = parse_branches_csv($csv);
    $existing = array_flip(array_map('mb_strtolower', array_column(all('SELECT name FROM branches'), 'name')));
    $n = 0; $skipped = 0;
    $lim = tenant_limit('branches'); $cur = (int) val('SELECT COUNT(*) FROM branches'); $capped = false;
    foreach ($rows as $r) {
        if (isset($existing[mb_strtolower($r['name'])])) { $skipped++; continue; }
        if ($lim > 0 && $cur >= $lim) { $capped = true; break; }   // respecta limita de plan si la import
        q("INSERT INTO branches (name, city, country, address, timezone, active) VALUES (?,?,'Romania',?,'Europe/Bucharest',1)",
            [$r['name'], $r['city'], $r['address']]);
        $existing[mb_strtolower($r['name'])] = 1; $n++; $cur++;
    }
    audit('import', 'branches', null, $n . ' filiale');
    $msg = $n > 0 ? "$n filiale importate." : 'Nicio filiala noua de importat.';
    if ($skipped) $msg .= " $skipped sarite (nume existent).";
    if ($capped) $msg .= ' Limita planului pentru filiale a fost atinsa.';
    flash($msg, $n > 0 ? 'info' : 'error');
    redirect('admin/branches');
}

/* ----------------------- ZILE INCHISE / SARBATORI ----------------------- */
function admin_closures_page(): void {
    $branches = all('SELECT id, name FROM branches ORDER BY name');
    $rows = all("SELECT cl.*, b.name AS branch_name FROM branch_closures cl
                 LEFT JOIN branches b ON b.id=cl.branch_id
                 WHERE cl.closed_date >= CURDATE() - INTERVAL 7 DAY
                 ORDER BY cl.closed_date ASC, b.name");
    view('admin/closures', compact('branches', 'rows'));
}
function admin_closure_save(): void {
    csrf_check();
    $date   = trim((string)($_POST['closed_date'] ?? ''));
    $bid    = (int)($_POST['branch_id'] ?? 0);            // 0 = toate filialele (global)
    $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 120);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { flash('Alege o data valida.', 'error'); redirect('admin/closures'); }
    // evita dubluri (UNIQUE nu prinde branch_id NULL in MySQL) — sterge apoi insereaza
    if ($bid) q("DELETE FROM branch_closures WHERE branch_id=? AND closed_date=?", [$bid, $date]);
    else      q("DELETE FROM branch_closures WHERE branch_id IS NULL AND closed_date=?", [$date]);
    q("INSERT INTO branch_closures (branch_id, closed_date, reason) VALUES (?,?,?)",
      [$bid ?: null, $date, $reason !== '' ? $reason : null]);
    audit('create', 'closure', $bid ?: 'all', $date);
    flash('Zi inchisa adaugata.'); redirect('admin/closures');
}
function admin_closure_delete(int $id): void {
    csrf_check();
    q("DELETE FROM branch_closures WHERE id=?", [$id]);
    audit('delete', 'closure', $id);
    flash('Zi inchisa stearsa.'); redirect('admin/closures');
}
/** Export zile inchise (CSV: data,motiv). ?branch=N pentru o filiala, implicit cele globale. ?template=1 = doar antet. */
function admin_closures_export(): void {
    $tmpl = isset($_GET['template']);
    $branch = (int)($_GET['branch'] ?? 0);
    if ($tmpl) $rows = [];
    elseif ($branch) $rows = all("SELECT closed_date, reason FROM branch_closures WHERE branch_id=? ORDER BY closed_date", [$branch]);
    else $rows = all("SELECT closed_date, reason FROM branch_closures WHERE branch_id IS NULL ORDER BY closed_date");
    if (!$tmpl) audit('export', 'closures');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($tmpl ? 'sablon_zile_inchise.csv' : 'zile_inchise_' . date('Ymd_His') . '.csv') . '"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv_safe($out, ['data', 'motiv']);
    foreach ($rows as $r) fputcsv_safe($out, [$r['closed_date'], $r['reason']]);
    fclose($out); exit;
}
/** Import zile inchise din CSV (data,motiv) pentru o filiala (0 = toate); sare peste datele deja existente in acel domeniu. */
function admin_closures_import(): void {
    csrf_check();
    $bid = (int)($_POST['branch_id'] ?? 0);  // 0 = global (toate filialele)
    $csv = (string)($_POST['csv'] ?? '');
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
        $csv = (string) file_get_contents($_FILES['file']['tmp_name']);
    $rows = parse_closures_csv($csv);
    $ex = $bid ? all('SELECT closed_date FROM branch_closures WHERE branch_id=?', [$bid])
               : all('SELECT closed_date FROM branch_closures WHERE branch_id IS NULL');
    $existing = array_flip(array_column($ex, 'closed_date'));
    $n = 0; $skipped = 0;
    foreach ($rows as $r) {
        if (isset($existing[$r['date']])) { $skipped++; continue; }
        q("INSERT INTO branch_closures (branch_id, closed_date, reason) VALUES (?,?,?)",
            [$bid ?: null, $r['date'], $r['reason'] !== '' ? $r['reason'] : null]);
        $existing[$r['date']] = 1; $n++;
    }
    audit('import', 'closures', $bid ?: 'all', $n . ' zile');
    $msg = $n > 0 ? "$n zile inchise importate." : 'Nicio zi noua de importat.';
    if ($skipped) $msg .= " $skipped sarite (data existenta).";
    flash($msg, $n > 0 ? 'info' : 'error');
    redirect('admin/closures');
}
function admin_branch_save(): void {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    // orar de functionare al filialei (open_hours JSON) — acelasi format ca la servicii
    $oh = '';
    if (isset($_POST['bsched_enabled'])) {
        $days = [];
        foreach ([1,2,3,4,5,6,0] as $d) {
            if (isset($_POST["bday_{$d}_open"])) {
                $fr = trim((string)($_POST["bday_{$d}_from"] ?? '')); $tt = trim((string)($_POST["bday_{$d}_to"] ?? ''));
                if ($fr && $tt) $days[(string)$d] = [$fr, $tt];
            }
        }
        $oh = json_encode(['enabled'=>true,'days'=>$days], JSON_UNESCAPED_UNICODE);
    }
    $f = ['name'=>trim($_POST['name'] ?? ''), 'city'=>trim($_POST['city'] ?? ''),
          'country'=>trim($_POST['country'] ?? 'Romania'), 'address'=>trim($_POST['address'] ?? ''),
          'timezone'=>trim($_POST['timezone'] ?? 'Europe/Bucharest'), 'open_hours'=>$oh,
          'active'=>isset($_POST['active'])?1:0];
    if ($f['name'] === '') { flash('Numele filialei este obligatoriu.', 'error'); redirect('admin/branches'); }
    if ($id) {
        $set = implode(', ', array_map(fn($k)=>"$k=?", array_keys($f)));
        q("UPDATE branches SET $set WHERE id=?", array_merge(array_values($f), [$id]));
    } else {
        if (tenant_limit_reached('branches')) { flash('Ai atins limita planului pentru filiale ('.tenant_limit('branches').'). Contactează furnizorul pentru un pachet mai mare.', 'error'); redirect('admin/branches'); }
        $cols = implode(',', array_keys($f)); $ph = implode(',', array_fill(0, count($f), '?'));
        q("INSERT INTO branches ($cols) VALUES ($ph)", array_values($f));
    }
    audit($id?'update':'create','branch',$id ?: insert_id());
    flash('Filiala salvata.'); redirect('admin/branches');
}
/** Duplica o filiala impreuna cu serviciile, ghiseele si dispozitivele ei (chei noi). */
function admin_branch_duplicate(int $id): void {
    csrf_check();
    $src = one('SELECT * FROM branches WHERE id=?', [$id]);
    if (!$src) { flash('Filiala inexistenta.', 'error'); redirect('admin/branches'); }
    if (tenant_limit_reached('branches')) { flash('Ai atins limita planului pentru filiale ('.tenant_limit('branches').'). Contactează furnizorul pentru un pachet mai mare.', 'error'); redirect('admin/branches'); }
    try {
        db()->beginTransaction();

        // 1) filiala noua (numarul de copii este urcat in nume)
        $base = preg_replace('/\s*\(copie( \d+)?\)$/u', '', $src['name']);
        $n = 1; do { $newName = $base.' (copie'.($n>1?' '.$n:'').')'; $n++; }
        while ($n <= 50 && (int)val('SELECT COUNT(*) FROM branches WHERE name=?', [$newName]) > 0);
        q('INSERT INTO branches (name,city,country,address,timezone,open_hours,active) VALUES (?,?,?,?,?,?,?)',
          [$newName, $src['city'], $src['country'], $src['address'], $src['timezone'], $src['open_hours'] ?? null, $src['active']]);
        $newBranch = insert_id();

        // 1b) grupuri de servicii (sunt per-filiala) — pastreaza maparea vechi->nou
        $grpMap = [];
        foreach (all('SELECT * FROM service_groups WHERE branch_id=? ORDER BY id', [$id]) as $g) {
            q('INSERT INTO service_groups (branch_id,name,color,sort_order) VALUES (?,?,?,?)',
              [$newBranch, $g['name'], $g['color'], $g['sort_order']]);
            $grpMap[(int)$g['id']] = insert_id();
        }

        // 2) servicii (pastreaza maparea vechi->nou pt ghisee/dispozitive)
        $svcMap = [];
        foreach (all('SELECT * FROM services WHERE branch_id=? ORDER BY id', [$id]) as $s) {
            $gid = $s['group_id'] !== null ? ($grpMap[(int)$s['group_id']] ?? null) : null;
            q('INSERT INTO services (branch_id,prefix,name,description,color,status,num_from,num_to,include_zeros,pad_length,allow_priority,terminate_on_call,kpi_wait_sec,kpi_service_sec,max_queued,max_per_day,sort_order,active_hours,form_id,group_id,i18n,paused,pause_note,appt_enabled,appt_slot_min,appt_capacity)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [$newBranch,$s['prefix'],$s['name'],$s['description'],$s['color'],$s['status'],$s['num_from'],$s['num_to'],$s['include_zeros'],$s['pad_length'],$s['allow_priority'],$s['terminate_on_call'],$s['kpi_wait_sec'],$s['kpi_service_sec'],$s['max_queued'],$s['max_per_day'],$s['sort_order'],$s['active_hours'],$s['form_id'],$gid,$s['i18n'],$s['paused'],$s['pause_note'],$s['appt_enabled'],$s['appt_slot_min'],$s['appt_capacity']]);
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
    audit('duplicate','branch',$newBranch);
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
    // Se accepta ORICE tip de fisier. Sunt blocate doar extensiile executabile pe server
    // (protectie anti-RCE), iar SVG-ul este curatat de scripturi inainte de stocare.
    // Folderul assets/uploads are oricum .htaccess care dezactiveaza executia (aparare in adancime).
    $blockExt = ['php','php2','php3','php4','php5','php6','php7','php8','phtml','pht','phps','phar','phpt',
                 'cgi','pl','py','asp','aspx','jsp','jspx','sh','bash','exe','bat','cmd','com','htaccess','htpasswd'];
    $dir = APP_ROOT . '/assets/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    // plafon efectiv: cel mai mic dintre plafonul aplicatiei si limitele PHP (upload_max_filesize/post_max_size)
    $appMax  = 512 * 1024 * 1024;                                             // plafon generos (512MB)
    $iniMax  = min(bdo_ini_bytes('upload_max_filesize'), bdo_ini_bytes('post_max_size'));
    $maxBytes = $iniMax > 0 ? min($appMax, $iniMax) : $appMax;
    $okCount = 0; $files = $_FILES['file'];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            if (in_array($files['error'][$i], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true))
                flash('Fisier prea mare pentru limita serverului (max '.bdo_human_size($maxBytes).').', 'error');
            continue;
        }
        $tmp = $files['tmp_name'][$i];
        if ($files['size'][$i] > $maxBytes) { flash('Fisier prea mare (max '.bdo_human_size($maxBytes).').', 'error'); continue; }
        $orig = (string) $files['name'][$i];
        $ext = strtolower(preg_replace('/[^a-z0-9]+/i', '', pathinfo($orig, PATHINFO_EXTENSION))) ?: 'bin';
        if (in_array($ext, $blockExt, true)) { flash('Tip de fisier interzis din motive de securitate: .'.e($ext), 'error'); continue; }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp) ?: 'application/octet-stream';
        // SVG: curata scripturile inainte de stocare, ca sa poata fi randat in siguranta ca imagine
        if ($ext === 'svg' || $ext === 'svgz' || $mime === 'image/svg+xml') {
            $clean = bdo_sanitize_svg((string) @file_get_contents($tmp));
            @file_put_contents($tmp, $clean);
            $ext = 'svg'; $mime = 'image/svg+xml';
        }
        $base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($orig, PATHINFO_FILENAME));
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
/** Sterge mai multe fisiere media odata (selectie cu ctrl/shift/ctrl+A pe pagina Multimedia). */
function admin_media_bulk_delete(): void {
    csrf_check();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    if (!$ids) { flash('Niciun fisier selectat.', 'error'); redirect('admin/media'); }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $n = 0;
    foreach (all("SELECT * FROM media WHERE id IN ($in)", $ids) as $m) {
        $path = APP_ROOT . '/' . $m['path'];
        if (is_file($path)) @unlink($path);
        $n++;
    }
    q("DELETE FROM media WHERE id IN ($in)", $ids);
    flash("$n fisier(e) sterse.");
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
    // pastreaza layoutul canvas (editat separat) cand se salveaza editorul clasic
    $dev = one('SELECT config FROM devices WHERE id=?', [$id]);
    $cur = ($dev && $dev['config']) ? json_decode($dev['config'], true) : [];
    if (is_array($cur) && isset($cur['canvas'])) $data['canvas'] = $cur['canvas'];
    q('UPDATE devices SET config=? WHERE id=?', [json_encode($data, JSON_UNESCAPED_UNICODE), $id]);
    json_out(['ok' => true]);
}
/* Editor canvas pentru dispenser (acelasi motor ca afisorul TV) — layoutul sta in config->canvas */
function admin_dispenser_canvas_builder(int $id): void {
    $dev = one('SELECT * FROM devices WHERE id=?', [$id]);
    if (!$dev) { http_response_code(404); echo 'Dispozitiv inexistent.'; return; }
    view('admin/dispenser_canvas_builder', ['dev' => $dev]);
}
function admin_dispenser_canvas_save(int $id): void {
    csrf_check();
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['screens'])) json_out(['ok' => false, 'error' => 'Layout invalid'], 422);
    // pastreaza config-ul editorului clasic (logic/appearance/texts/popup); doar cheia „canvas" e actualizata
    $dev = one('SELECT config FROM devices WHERE id=?', [$id]);
    $cfg = ($dev && $dev['config']) ? json_decode($dev['config'], true) : [];
    if (!is_array($cfg)) $cfg = [];
    $cfg['canvas'] = $data;
    q('UPDATE devices SET config=? WHERE id=?', [json_encode($cfg, JSON_UNESCAPED_UNICODE), $id]);
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
        fputcsv_safe($out, ['Bon','Serviciu','Status','Prioritar','Canal','Emis','Apelat','Finalizat','Ghiseu']);
        foreach ($rows as $r) fputcsv_safe($out, [$r['label'],$r['service'],$r['status'],$r['priority']?'Da':'Nu',
            $r['channel'],$r['issued_at'],$r['called_at'],$r['finished_at'],$r['counter']]);
        fclose($out); exit;
    }

    $kpi = one("SELECT COUNT(*) total,
                SUM(status='served') served, SUM(status='no_show') no_show, SUM(status='cancelled') cancelled,
                AVG(TIMESTAMPDIFF(SECOND,issued_at,called_at)) avg_wait,
                AVG(CASE WHEN status='served' THEN TIMESTAMPDIFF(SECOND,called_at,finished_at) END) avg_service
                FROM tickets t WHERE $where", $args) ?: [];

    // perioada precedenta, de aceeasi lungime (pentru comparatie)
    $lenDays  = (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1;
    $prevTo   = date('Y-m-d', strtotime($from . ' -1 day'));
    $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($lenDays - 1) . ' days'));
    $prevArgs = [$prevFrom, $prevTo];
    if ($branch) $prevArgs[] = $branch;
    $kpiPrev = one("SELECT COUNT(*) total,
                SUM(status='served') served, SUM(status='no_show') no_show, SUM(status='cancelled') cancelled,
                AVG(TIMESTAMPDIFF(SECOND,issued_at,called_at)) avg_wait,
                AVG(CASE WHEN status='served' THEN TIMESTAMPDIFF(SECOND,called_at,finished_at) END) avg_service
                FROM tickets t WHERE $where", $prevArgs) ?: [];

    $per_day = all("SELECT DATE(t.issued_at) d, COUNT(*) c,
                    AVG(TIMESTAMPDIFF(SECOND,t.issued_at,t.called_at)) w
                    FROM tickets t WHERE $where GROUP BY d ORDER BY d", $args);
    $per_service = all("SELECT s.name, s.color, s.kpi_wait_sec, COUNT(t.id) c,
                        SUM(t.status='served') served,
                        AVG(TIMESTAMPDIFF(SECOND,t.issued_at,t.called_at)) w,
                        AVG(CASE WHEN t.status='served' THEN TIMESTAMPDIFF(SECOND,t.called_at,t.finished_at) END) sv,
                        SUM(t.called_at IS NOT NULL) called_cnt,
                        SUM(t.called_at IS NOT NULL AND TIMESTAMPDIFF(SECOND,t.issued_at,t.called_at) <= s.kpi_wait_sec) kpi_ok
                        FROM services s LEFT JOIN tickets t ON t.service_id=s.id AND ($where)
                        GROUP BY s.id ORDER BY c DESC", $args);
    $per_hour = all("SELECT HOUR(t.issued_at) h, COUNT(*) c FROM tickets t WHERE $where GROUP BY h", $args);
    // heatmap aglomeratie: zi a saptamanii x ora (DAYOFWEEK: 1=Duminica ... 7=Sambata)
    $heat = all("SELECT DAYOFWEEK(t.issued_at) d, HOUR(t.issued_at) h, COUNT(*) c FROM tickets t WHERE $where GROUP BY d, h", $args);
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
    // CSAT pe serviciu (doar feedback legat de un bon servit, deci de un serviciu)
    $fb_service = all("SELECT s.name service_name, s.color, COUNT(*) n, AVG(f.rating) avg
                       FROM feedback f JOIN tickets t ON t.id=f.ticket_id JOIN services s ON s.id=t.service_id
                       WHERE $fbWhere GROUP BY s.id, s.name, s.color ORDER BY n DESC, avg DESC", $fbArgs);
    // CSAT pe operator (feedback legat de un bon care a fost servit de cineva)
    $fb_operator = all("SELECT u.name, COUNT(*) n, AVG(f.rating) avg
                        FROM feedback f JOIN tickets t ON t.id=f.ticket_id JOIN users u ON u.id=t.agent_id
                        WHERE $fbWhere GROUP BY t.agent_id, u.name ORDER BY n DESC, avg DESC", $fbArgs);

    // activitate operatori: timp pe status in interval (clamped la interval)
    $fromDt = $from.' 00:00:00'; $toDt = $to.' 23:59:59';
    $op_activity = all("SELECT u.name, l.status,
        SUM(TIMESTAMPDIFF(SECOND, GREATEST(l.started_at, ?), LEAST(COALESCE(l.ended_at, NOW()), ?))) secs
      FROM user_status_log l JOIN users u ON u.id=l.user_id
      WHERE l.started_at < ? AND COALESCE(l.ended_at, NOW()) > ?
      GROUP BY u.id, l.status", [$fromDt, $toDt, $toDt, $fromDt]);

    // export CSV per set de date (fiecare grafic are buton propriu de download)
    if (($_GET['export'] ?? '') === 'csv' && in_array($_GET['dataset'] ?? '', ['day','service','counter','hour','user','op_activity','csat','csat_op'], true)) {
        $ds = $_GET['dataset'];
        // activitate operatori pivotata: o linie/operator cu minute pe fiecare status
        $opPivot = [];
        foreach ($op_activity as $r) {
            $n = $r['name']; $opPivot[$n] ??= ['available'=>0,'busy'=>0,'paused'=>0,'offline'=>0];
            if (isset($opPivot[$n][$r['status']])) $opPivot[$n][$r['status']] += (int)$r['secs'];
        }
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
          'op_activity' => ['activitate_operatori', ['Operator','Disponibil (min)','Ocupat (min)','Pauza (min)','Indisponibil (min)'],
                        array_map(fn($n)=>[$n, round($opPivot[$n]['available']/60), round($opPivot[$n]['busy']/60), round($opPivot[$n]['paused']/60), round($opPivot[$n]['offline']/60)], array_keys($opPivot))],
          'csat'    => ['csat_pe_serviciu', ['Serviciu','Raspunsuri','Nota medie'],
                        array_map(fn($r)=>[$r['service_name'],(int)$r['n'],round((float)$r['avg'],2)], $fb_service)],
          'csat_op' => ['csat_pe_operator', ['Operator','Raspunsuri','Nota medie'],
                        array_map(fn($r)=>[$r['name'],(int)$r['n'],round((float)$r['avg'],2)], $fb_operator)],
        ];
        [$fname,$head,$data] = $sets[$ds];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$fname.'_'.$from.'_'.$to.'.csv"');
        $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
        fputcsv_safe($out,$head); foreach($data as $row) fputcsv_safe($out,$row);
        fclose($out); exit;
    }

    // statistici programari pe interval (dupa data slotului) — pentru ecran si export
    $apWhere = 'DATE(slot_start) BETWEEN ? AND ?'; $apArgs = [$from, $to];
    if ($branch) { $apWhere .= ' AND branch_id = ?'; $apArgs[] = $branch; }
    $appt = one("SELECT COUNT(*) total, SUM(status='booked') booked, SUM(status='checked_in') checked_in,
                 SUM(status='no_show') no_show, SUM(status='cancelled') cancelled
                 FROM appointments WHERE $apWhere", $apArgs) ?: [];

    // export Excel (.xlsx) cu grafice native
    if (($_GET['export'] ?? '') === 'xlsx') {
        $brand = (string) setting('brand_name', 'Bon de ordine');
        $branchLabel = $branch
            ? ('Filiala: ' . ((string)(val('SELECT name FROM branches WHERE id=?', [$branch]) ?? $branch)))
            : 'Toate filialele';
        $accent = (string) setting('accent_color', '#2563eb');
        // combina bilete pe utilizator + timp pe status pentru foaia "Operatori"
        $opMap = [];
        foreach ($per_user as $r) $opMap[$r['name']] = ['name'=>$r['name'],'total'=>(int)$r['c'],'served'=>(int)$r['served'],
            'w'=>round((float)$r['w']),'sv'=>round((float)$r['sv']),'available'=>0,'busy'=>0,'paused'=>0];
        foreach (($op_activity ?? []) as $r) {
            if (!isset($opMap[$r['name']])) $opMap[$r['name']] = ['name'=>$r['name'],'total'=>0,'served'=>0,'w'=>0,'sv'=>0,'available'=>0,'busy'=>0,'paused'=>0];
            if (in_array($r['status'], ['available','busy','paused'], true)) $opMap[$r['name']][$r['status']] = (int)$r['secs'];
        }
        $op_rows = array_values($opMap);
        $xl = build_stats_xlsx($brand, $branchLabel, $from, $to, $kpi, $per_day, $per_service, $per_hour, $per_counter, $accent, $op_rows, $appt, $fb_service, $fb_operator);
        $xl->download('statistici_' . $from . '_' . $to . '.xlsx');
    }

    view('admin/statistics', compact('from','to','branch','branches','kpi','per_day','per_service','per_hour','per_counter','per_user','feedback','fb_dist','fb_recent','fb_service','fb_operator','op_activity','heat','kpiPrev','prevFrom','prevTo','appt'));
}

/**
 * Construieste workbook-ul de statistici (.xlsx) cu grafice native Excel:
 * placinta (status), linie (bilete/zi), bare (serviciu/ghiseu), coloane (aflux orar).
 */
function build_stats_xlsx(string $brand, string $branchLabel, string $from, string $to,
                          array $kpi, array $per_day, array $per_service, array $per_hour,
                          array $per_counter, string $accent, array $op_rows = [], array $appt = [],
                          array $fb_service = [], array $fb_operator = []): Xlsx {
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
    if (!empty($appt) && (int)($appt['total'] ?? 0) > 0) {
        $s->blank();
        $s->row(['Programari online', 'Valoare'], Xlsx::S_HEAD);
        $s->row(['Total programari', (int)$appt['total']]);
        $s->row(['Check-in', (int)($appt['checked_in'] ?? 0)]);
        $s->row(['Neprezentate', (int)($appt['no_show'] ?? 0)]);
        $s->row(['Anulate', (int)($appt['cancelled'] ?? 0)]);
        $s->row(['In asteptare', (int)($appt['booked'] ?? 0)]);
    }
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

    /* ---- Foaia: CSAT pe serviciu / operator (bare cu nota medie) ---- */
    if ($fb_service || $fb_operator) {
        $s = $xl->add_sheet('CSAT');
        $s->set_col_widths([1 => 26, 2 => 12, 3 => 12]);
        if ($fb_service) {
            $s->row([['v' => 'Nota medie pe serviciu', 's' => Xlsx::S_TITLE]]);
            $hdr = $s->row(['Serviciu', 'Raspunsuri', 'Nota medie'], Xlsx::S_HEAD);
            $cats = []; $vals = []; $cols = []; $first = $hdr + 1;
            foreach ($fb_service as $r) {
                $s->row([$r['service_name'], (int)$r['n'], ['v' => round((float)$r['avg'], 2), 's' => Xlsx::S_NUM1]]);
                $cats[] = $r['service_name']; $vals[] = round((float)$r['avg'], 2); $cols[] = $r['color'] ?: $accent;
            }
            $last = $s->row_count();
            $s->chart([
                'type' => 'bar', 'title' => 'Nota medie pe serviciu (1–5)',
                'anchor' => ['col' => 5, 'row' => 1, 'col2' => 14, 'row2' => 18],
                'cat' => ['ref' => '$A$' . $first . ':$A$' . $last, 'vals' => $cats],
                'series' => [['name' => 'Nota medie', 'name_ref' => '$C$' . $hdr, 'ref' => '$C$' . $first . ':$C$' . $last, 'vals' => $vals, 'colors' => $cols]],
            ]);
        }
        if ($fb_operator) {
            $s->blank();
            $s->row([['v' => 'Nota medie pe operator', 's' => Xlsx::S_TITLE]]);
            $s->row(['Operator', 'Raspunsuri', 'Nota medie'], Xlsx::S_HEAD);
            foreach ($fb_operator as $r) {
                $s->row([$r['name'], (int)$r['n'], ['v' => round((float)$r['avg'], 2), 's' => Xlsx::S_NUM1]]);
            }
        }
    }

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

    /* ---- Foaia 6: Operatori (bilete + timp pe status) ---- */
    if ($op_rows) {
        $s = $xl->add_sheet('Operatori');
        $s->set_col_widths([1 => 22, 2 => 9, 3 => 9, 4 => 20, 5 => 18, 6 => 17, 7 => 14, 8 => 13]);
        $s->row([['v' => 'Activitate operatori', 's' => Xlsx::S_TITLE]]);
        $s->row([['v' => 'Perioada: ' . $from . ' – ' . $to, 's' => Xlsx::S_MUTED]]);
        $hdr = $s->row(['Operator', 'Total', 'Servite', 'Timp asteptare (min)', 'Timp servire (min)', 'Disponibil (min)', 'Ocupat (min)', 'Pauza (min)'], Xlsx::S_HEAD);
        $cats = []; $vals = []; $first = $hdr + 1;
        foreach ($op_rows as $r) {
            $s->row([$r['name'] ?? '', (int)($r['total'] ?? 0), (int)($r['served'] ?? 0),
                ['v' => $mins($r['w'] ?? 0), 's' => Xlsx::S_NUM1], ['v' => $mins($r['sv'] ?? 0), 's' => Xlsx::S_NUM1],
                ['v' => $mins($r['available'] ?? 0), 's' => Xlsx::S_NUM1], ['v' => $mins($r['busy'] ?? 0), 's' => Xlsx::S_NUM1], ['v' => $mins($r['paused'] ?? 0), 's' => Xlsx::S_NUM1]]);
            $cats[] = $r['name'] ?? ''; $vals[] = (int)($r['served'] ?? 0);
        }
        $last = $s->row_count();
        if ($vals) $s->chart([
            'type' => 'bar', 'title' => 'Bilete servite pe operator',
            'anchor' => ['col' => 9, 'row' => 1, 'col2' => 18, 'row2' => 21],
            'cat' => ['ref' => '$A$' . $first . ':$A$' . $last, 'vals' => $cats],
            'series' => [['name' => 'Servite', 'name_ref' => '$C$' . $hdr, 'ref' => '$C$' . $first . ':$C$' . $last, 'vals' => $vals, 'color' => $accent]],
        ]);
    }

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
/** Construieste filtrul (WHERE + args + meta) pentru lista de programari din parametrii GET. */
function admin_appointments_filter(): array {
    $date   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
    $branch = (int)($_GET['branch'] ?? 0);
    $viewMode = (($_GET['view'] ?? 'day') === 'week') ? 'week' : 'day';
    if ($viewMode === 'week') {
        $weekStart = date('Y-m-d', strtotime($date . ' -' . ((((int)date('N', strtotime($date))) - 1)) . ' days'));
        $weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        $w = 'DATE(a.slot_start) BETWEEN ? AND ?'; $args = [$weekStart, $weekEnd];
    } else {
        $weekStart = $weekEnd = null;
        $w = 'DATE(a.slot_start)=?'; $args = [$date];
    }
    if ($branch) { $w .= ' AND a.branch_id=?'; $args[] = $branch; }
    return [$w, $args, compact('date','branch','viewMode','weekStart','weekEnd')];
}
function admin_appointments_list(): void {
    [$w, $args, $f] = admin_appointments_filter();
    $rows = all("SELECT a.*, s.name service_name, s.color, s.prefix, b.name branch_name,
                    t.label ticket_label, t.public_token ticket_token
                 FROM appointments a JOIN services s ON s.id=a.service_id JOIN branches b ON b.id=a.branch_id
                 LEFT JOIN tickets t ON t.id=a.ticket_id WHERE $w ORDER BY a.slot_start", $args);
    $branches = all('SELECT id,name FROM branches ORDER BY name');
    $services = all('SELECT id,prefix,name,color,branch_id FROM services WHERE appt_enabled=1 AND status="active" ORDER BY name');
    $date=$f['date']; $branch=$f['branch']; $viewMode=$f['viewMode']; $weekStart=$f['weekStart']; $weekEnd=$f['weekEnd'];
    // lista de asteptare: intrari viitoare neanuntate
    $waitlist = all("SELECT w.*, s.name service_name, s.prefix, s.color, b.name branch_name
                     FROM appointment_waitlist w JOIN services s ON s.id=w.service_id LEFT JOIN branches b ON b.id=w.branch_id
                     WHERE w.notified_at IS NULL AND w.slot_start >= NOW()" . ($branch ? ' AND w.branch_id='.(int)$branch : '') . "
                     ORDER BY w.slot_start, w.id LIMIT 100");
    view('admin/appointments', compact('rows','date','branch','branches','services','viewMode','weekStart','weekEnd','waitlist'));
}
/** Export CSV al programarilor filtrate (BOM UTF-8, separator ;). */
function admin_appointments_export(): void {
    [$w, $args, $f] = admin_appointments_filter();
    audit('export', 'appointments', $f['date']);
    $rows = all("SELECT a.slot_start, a.status, s.name service_name, b.name branch_name,
                        a.customer_name, a.customer_phone, a.customer_email, a.note, t.label ticket_label
                 FROM appointments a JOIN services s ON s.id=a.service_id JOIN branches b ON b.id=a.branch_id
                 LEFT JOIN tickets t ON t.id=a.ticket_id WHERE $w ORDER BY a.slot_start", $args);
    $statusRo = ['booked'=>'Confirmata','checked_in'=>'Check-in','cancelled'=>'Anulata','no_show'=>'Neprezentat'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="programari_' . $f['date'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv_safe($out, ['Data/ora','Status','Serviciu','Filiala','Client','Telefon','Email','Nota','Bon'], ';');
    foreach ($rows as $r) {
        fputcsv_safe($out, [$r['slot_start'], $statusRo[$r['status']] ?? $r['status'], $r['service_name'], $r['branch_name'] ?? '',
                       $r['customer_name'] ?? '', $r['customer_phone'] ?? '', $r['customer_email'] ?? '',
                       $r['note'] ?? '', $r['ticket_label'] ?? ''], ';');
    }
    fclose($out);
    exit;
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
    elseif ($act === 'cancel') {
        appt_cancel($id);   // status -> cancelled + webhook
        // anunta clientul pe email (best-effort)
        if (!empty($a['customer_email']) && mail_enabled()) {
            $svcName = (string) (val('SELECT name FROM services WHERE id=?', [$a['service_id']]) ?? '');
            $when = date('d.m.Y H:i', strtotime($a['slot_start']));
            send_mail($a['customer_email'], 'Programare anulata — ' . $svcName,
                mail_template('Programare anulata',
                    '<p>Programarea ta la <strong>' . e($svcName) . '</strong> din <strong>' . e($when) . '</strong> a fost anulata.</p>'
                  . '<p>Te rugam sa faci o noua programare daca mai ai nevoie.</p>',
                    'Programeaza-te din nou', url('book')));
        }
        flash('Programare anulata.');
    }
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
