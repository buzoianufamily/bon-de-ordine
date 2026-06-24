<?php
/**
 * Sarcini programate — rulate de un cron job extern (cPanel → Cron Jobs)
 * care apeleaza /cron?key=CRON_TOKEN, ex. la fiecare 15 minute.
 *  - remindere programari (cu ~24h inainte)
 *  - raport zilnic pe email (o data pe zi)
 */
function run_cron_jobs(): array {
    $out = ['ok' => true, 'ran_at' => now(), 'reminders' => 0, 'daily_report' => false, 'cleaned' => 0];
    try { set_setting('cron_last_run', (string) time()); } catch (Throwable $e) {}   // pentru diagnoza „cron activ?"

    /* 0) Curatare automata: sterge biletele mai vechi decat perioada de retentie (nu necesita email). */
    $months = (int) setting('retention_months', '0');
    if ($months > 0) {
        try {
            // sterge in loturi pana se goleste restanta (altfel >1000 bilete vechi raman pe loc -> PII nepurjat)
            $out['cleaned'] = 0;
            do { $d = q("DELETE FROM tickets WHERE issued_at < NOW() - INTERVAL $months MONTH LIMIT 1000")->rowCount(); $out['cleaned'] += $d; } while ($d >= 1000);
            q("DELETE FROM ticket_sequences WHERE seq_date < CURDATE() - INTERVAL $months MONTH");
            q("DELETE FROM appointments WHERE slot_start < NOW() - INTERVAL $months MONTH AND status <> 'booked'");
            // lista de asteptare (contine email/telefon) — aceeasi perioada de retentie
            q("DELETE FROM appointment_waitlist WHERE slot_start < NOW() - INTERVAL $months MONTH");
            // jurnale operationale (cresc nelimitat altfel) — aceeasi perioada de retentie
            q("DELETE FROM audit_log WHERE created_at < NOW() - INTERVAL $months MONTH");
            q("DELETE FROM user_status_log WHERE started_at < NOW() - INTERVAL $months MONTH");
        } catch (Throwable $e) {}
    }

    /* 0b) Inchidere automata a biletelor uitate (nu necesita email). */
    $acMin = (int) setting('auto_close_min', '0');
    if ($acMin > 0) {
        try {
            // in servire de prea mult timp -> finalizat (operatorul a uitat sa apese „finalizat")
            $r1 = q("UPDATE tickets SET status='served', finished_at=NOW()
                     WHERE status='serving' AND served_at < NOW() - INTERVAL $acMin MINUTE")->rowCount();
            // chemat dar neprezentat de prea mult timp -> neprezentat
            $r2 = q("UPDATE tickets SET status='no_show', finished_at=NOW()
                     WHERE status='called' AND called_at < NOW() - INTERVAL $acMin MINUTE")->rowCount();
            $out['auto_closed'] = $r1 + $r2;
        } catch (Throwable $e) {}
    }

    /* 0c) Alerta SLA — bilete care asteapta peste tinta: webhook (mereu) + email (daca e activ). */
    $out['sla_alert'] = false;
    if (setting('sla_alert_enabled', '0') === '1') {
        $cooldown = max(5, (int) setting('sla_alert_cooldown_min', '30')) * 60;   // implicit 30 min
        if (time() - (int) setting('sla_alert_last', '0') >= $cooldown) {
            $minBreaches = max(1, (int) setting('sla_alert_min', '1'));
            $rows = all("SELECT b.name branch_name, s.name, s.prefix, s.kpi_wait_sec,
                                COUNT(t.id) breaching, MAX(TIMESTAMPDIFF(SECOND,t.issued_at,NOW())) worst
                         FROM tickets t JOIN services s ON s.id=t.service_id JOIN branches b ON b.id=t.branch_id
                         WHERE t.status='waiting' AND s.kpi_wait_sec>0
                           AND TIMESTAMPDIFF(SECOND,t.issued_at,NOW()) > s.kpi_wait_sec
                         GROUP BY s.id ORDER BY worst DESC LIMIT 50");
            $totalBreaches = array_sum(array_map(fn($r) => (int)$r['breaching'], $rows));
            if ($totalBreaches >= $minBreaches) {
                // webhook semnat (nu necesita email) — eveniment 'sla.breach'
                fire_webhook('sla.breach', ['total' => $totalBreaches, 'services' => array_map(fn($r) => [
                    'branch' => $r['branch_name'], 'service' => $r['name'], 'prefix' => $r['prefix'],
                    'breaching' => (int)$r['breaching'], 'worst_wait_sec' => (int)$r['worst'],
                    'target_sec' => (int)$r['kpi_wait_sec'],
                ], $rows)]);
                $whConfigured = trim((string) setting('webhook_url', '')) !== '';
                $sent = false;
                if (function_exists('mail_enabled') && mail_enabled()) {
                    $to = trim((string) setting('sla_alert_to', '')) ?: trim((string) setting('daily_report_to', ''));
                    if ($to === '') $to = implode(',', array_column(all("SELECT email FROM users WHERE role='admin' AND active=1"), 'email'));
                    $recipients = array_filter(array_map('trim', explode(',', $to)));
                    if ($recipients) {
                        $mmss = fn($s) => sprintf('%d:%02d', intdiv((int)$s, 60), (int)$s % 60);
                        $rowsHtml = '';
                        foreach ($rows as $r)
                            $rowsHtml .= '<tr><td style="padding:4px 8px 4px 0">' . e($r['branch_name']) . ' · <strong>' . e($r['name']) . '</strong></td>'
                                       . '<td style="padding:4px 8px;text-align:right">' . (int)$r['breaching'] . ' bilete</td>'
                                       . '<td style="padding:4px 0;text-align:right">cel mai vechi <strong>' . e($mmss($r['worst'])) . '</strong> (tinta ' . e($mmss($r['kpi_wait_sec'])) . ')</td></tr>';
                        $body = '<p>În acest moment <strong>' . (int)$totalBreaches . '</strong> bilete așteaptă peste ținta serviciului:</p>'
                              . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $rowsHtml . '</table>'
                              . '<p style="color:#6b7280;font-size:13px">Verifică încărcarea ghișeelor sau deschide ghișee suplimentare.</p>';
                        foreach ($recipients as $addr)
                            $sent = send_mail($addr, '⚠ Alertă SLA — cozi peste țintă · ' . setting('brand_name', 'Bon de ordine'),
                                mail_template('Alertă SLA — timp de așteptare', $body, 'Deschide dashboard', url('admin'))) || $sent;
                    }
                }
                // cooldown-ul porneste daca AM trimis ceva (email sau webhook configurat)
                if ($sent || $whConfigured) { set_setting('sla_alert_last', (string) time()); $out['sla_alert'] = $totalBreaches; }
            }
        }
    }

    /* 0d) Operatori inactivi (browser inchis fara logout) -> offline. Nu necesita email. */
    $offMin = max(2, (int) setting('auto_offline_min', '10'));
    try {
        $stale = all("SELECT id FROM users WHERE work_status<>'offline'
                      AND (last_seen IS NULL OR last_seen < NOW() - INTERVAL $offMin MINUTE)");
        foreach ($stale as $su) {
            log_user_status((int)$su['id'], 'offline');   // inchide intervalul de status (statistici corecte)
            q("UPDATE users SET work_status='offline' WHERE id=?", [(int)$su['id']]);
        }
        $out['auto_offline'] = count($stale);
    } catch (Throwable $e) {}

    /* 0e) Programari neonorate: 'booked' cu slot trecut de mult -> 'no_show'. Nu necesita email. */
    $apNs = (int) setting('appt_noshow_min', '0');
    if ($apNs > 0) {
        try {
            // notifica integratorii (webhook) pentru fiecare programare neonorata — doar daca e configurat URL
            if (trim((string) setting('webhook_url', '')) !== '') {
                foreach (all("SELECT * FROM appointments WHERE status='booked' AND slot_start < NOW() - INTERVAL $apNs MINUTE LIMIT 100") as $a) {
                    $a['status'] = 'no_show';
                    fire_webhook('appointment.no_show', webhook_appointment($a));
                }
            }
            $out['appt_no_show'] = q("UPDATE appointments SET status='no_show'
                WHERE status='booked' AND slot_start < NOW() - INTERVAL $apNs MINUTE")->rowCount();
        } catch (Throwable $e) {}
    }

    /* 0f) Backup automat zilnic pe server (optional). Nu necesita email. */
    $out['backup'] = false;
    if (setting('backup_auto_enabled', '0') === '1') {
        $today = date('Y-m-d');
        if (setting('backup_last', '') !== $today) {
            try {
                $name = backup_to_file();
                if ($name !== '') {
                    set_setting('backup_last', $today);
                    backup_prune((int) setting('backup_keep', '14'));
                    $out['backup'] = $name;
                }
            } catch (Throwable $e) {}
        }
    }

    if (!function_exists('mail_enabled') || !mail_enabled()) { $out['note'] = 'Email dezactivat'; return $out; }

    /* 1) Remindere pentru programarile din urmatoarele 24h, inca neremindate. */
    if (setting('reminder_enabled', '0') === '1') {
        $rows = all("SELECT a.*, s.name service_name, b.name branch_name, b.address, b.city
                     FROM appointments a JOIN services s ON s.id=a.service_id JOIN branches b ON b.id=a.branch_id
                     WHERE a.status='booked' AND a.reminded_at IS NULL
                       AND a.customer_email IS NOT NULL AND a.customer_email<>''
                       AND a.slot_start BETWEEN NOW() AND (NOW() + INTERVAL 24 HOUR)
                     LIMIT 200");
        foreach ($rows as $a) {
            $ts   = strtotime($a['slot_start']);
            $when = date('d.m.Y', $ts) . ' la ora ' . date('H:i', $ts);
            $loc  = trim(($a['branch_name'] ?? '') . ($a['address'] ? ', ' . $a['address'] : '') . ($a['city'] ? ', ' . $a['city'] : ''));
            $body = '<p>Buna' . ($a['customer_name'] ? ' <strong>' . e($a['customer_name']) . '</strong>' : '') . ',</p>'
                  . '<p>Iti reamintim de programarea ta:</p>'
                  . '<ul><li>Serviciu: <strong>' . e($a['service_name']) . '</strong></li>'
                  . '<li>Data: <strong>' . e($when) . '</strong></li>'
                  . ($loc ? '<li>Locatie: ' . e($loc) . '</li>' : '') . '</ul>'
                  . '<p>Cand ajungi, deschide linkul de mai jos si apasa <strong>Check-in</strong> — primesti automat bonul de ordine.</p>'
                  . '<p><a href="' . e(url('a/' . $a['public_token'] . '/ics')) . '">📅 Adauga in calendar</a></p>';
            if (send_mail($a['customer_email'], 'Reminder programare — ' . $a['service_name'],
                    mail_template('Reminder programare', $body, 'Vezi programarea', url('a/' . $a['public_token'])))) {
                q("UPDATE appointments SET reminded_at=NOW() WHERE id=?", [$a['id']]);
                $out['reminders']++;
            }
        }
    }

    /* 2) Raport zilnic — o singura data pe zi, despre ziua precedenta. */
    if (setting('daily_report_enabled', '0') === '1') {
        $today = date('Y-m-d');
        if (setting('last_daily_report', '') !== $today) {
            $to = trim((string) setting('daily_report_to', ''));
            if ($to === '') {
                $to = implode(',', array_column(all("SELECT email FROM users WHERE role='admin' AND active=1"), 'email'));
            }
            $recipients = array_filter(array_map('trim', explode(',', $to)));
            if ($recipients) {
                $day = date('Y-m-d', strtotime('-1 day'));
                $k = one("SELECT COUNT(*) total, SUM(status='served') served, SUM(status='no_show') no_show,
                          SUM(status='cancelled') cancelled, AVG(TIMESTAMPDIFF(SECOND,issued_at,called_at)) avg_wait,
                          AVG(CASE WHEN status='served' THEN TIMESTAMPDIFF(SECOND,called_at,finished_at) END) avg_service
                          FROM tickets WHERE DATE(issued_at)=?", [$day]) ?: [];
                $svc = all("SELECT s.name, COUNT(t.id) c FROM services s
                            LEFT JOIN tickets t ON t.service_id=s.id AND DATE(t.issued_at)=?
                            GROUP BY s.id HAVING c>0 ORDER BY c DESC LIMIT 8", [$day]);
                $mmss = fn($s) => sprintf('%d:%02d', intdiv((int)$s, 60), (int)$s % 60);
                $rowsHtml = '';
                foreach ([['Total bilete', (int)($k['total'] ?? 0)], ['Servite', (int)($k['served'] ?? 0)],
                          ['Neprezentate', (int)($k['no_show'] ?? 0)], ['Anulate', (int)($k['cancelled'] ?? 0)],
                          ['Timp mediu asteptare', $mmss($k['avg_wait'] ?? 0)], ['Timp mediu servire', $mmss($k['avg_service'] ?? 0)]] as $r)
                    $rowsHtml .= '<tr><td style="padding:4px 0">' . e($r[0]) . '</td><td style="padding:4px 0;text-align:right"><strong>' . e((string)$r[1]) . '</strong></td></tr>';
                $svcHtml = '';
                foreach ($svc as $s) $svcHtml .= '<tr><td style="padding:2px 0">' . e($s['name']) . '</td><td style="padding:2px 0;text-align:right">' . (int)$s['c'] . '</td></tr>';
                $body = '<p>Rezumat pentru <strong>' . e(date('d.m.Y', strtotime($day))) . '</strong>:</p>'
                      . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $rowsHtml . '</table>'
                      . ($svcHtml ? '<h3 style="margin:18px 0 6px;font-size:15px">Pe serviciu</h3><table style="width:100%;border-collapse:collapse;font-size:14px">' . $svcHtml . '</table>' : '');
                $sent = false;
                foreach ($recipients as $addr)
                    $sent = send_mail($addr, 'Raport zilnic ' . date('d.m.Y', strtotime($day)) . ' — ' . setting('brand_name', 'Bon de ordine'),
                        mail_template('Raport zilnic', $body, 'Deschide statistici', url('admin/statistics'))) || $sent;
                if ($sent) { set_setting('last_daily_report', $today); $out['daily_report'] = true; }
            }
        }
    }

    return $out;
}
