<?php
/**
 * Sarcini programate — rulate de un cron job extern (cPanel → Cron Jobs)
 * care apeleaza /cron?key=CRON_TOKEN, ex. la fiecare 15 minute.
 *  - remindere programari (cu ~24h inainte)
 *  - raport zilnic pe email (o data pe zi)
 */
function run_cron_jobs(): array {
    $out = ['ok' => true, 'ran_at' => now(), 'reminders' => 0, 'daily_report' => false];
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
                  . '<p>Cand ajungi, deschide linkul de mai jos si apasa <strong>Check-in</strong> — primesti automat bonul de ordine.</p>';
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
