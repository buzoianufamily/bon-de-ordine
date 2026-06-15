<?php
/** Programari: sloturi disponibile, rezervare, check-in (genereaza bilet). */

/** Fereastra de program [open,close] pentru o data, din active_hours sau implicit 09-17. */
function appt_open_window(array $svc, string $date): ?array {
    $raw = trim((string)($svc['active_hours'] ?? ''));
    $dow = (int)date('w', strtotime($date));
    if ($raw !== '') {
        $cfg = json_decode($raw, true);
        if (is_array($cfg) && !empty($cfg['enabled'])) {
            $day = $cfg['days'][$dow] ?? ($cfg['days'][(string)$dow] ?? null);
            if (!is_array($day) || count($day) < 2 || !$day[0] || !$day[1]) return null; // inchis in ziua aceea
            return [$day[0], $day[1]];
        }
    }
    return ['09:00', '17:00'];
}

/** Sloturi pentru un serviciu intr-o zi. */
function appt_slots(array $svc, string $date): array {
    // zi inchisa (sarbatoare) -> niciun slot
    if (isset($svc['branch_id']) && function_exists('branch_closure_reason')
        && branch_closure_reason((int)$svc['branch_id'], strtotime($date . ' 12:00:00')) !== null) return [];
    $win = appt_open_window($svc, $date);
    if (!$win) return [];
    $len = max(5, (int)($svc['appt_slot_min'] ?? 15));
    $cap = max(1, (int)($svc['appt_capacity'] ?? 1));
    $start = strtotime($date . ' ' . $win[0]);
    $end   = strtotime($date . ' ' . $win[1]);
    $taken = [];
    foreach (all("SELECT slot_start, COUNT(*) c FROM appointments
                  WHERE service_id=? AND DATE(slot_start)=? AND status IN ('booked','checked_in')
                  GROUP BY slot_start", [$svc['id'], $date]) as $r) {
        $taken[date('Y-m-d H:i:00', strtotime($r['slot_start']))] = (int)$r['c'];
    }
    $now = time(); $slots = [];
    for ($t = $start; $t + $len*60 <= $end; $t += $len*60) {
        $key = date('Y-m-d H:i:00', $t); $used = $taken[$key] ?? 0;
        $slots[] = ['time'=>date('H:i',$t),'start'=>$key,'capacity'=>$cap,'used'=>$used,
                    'available'=>max(0,$cap-$used),'full'=>$used>=$cap,'past'=>$t < $now];
    }
    return $slots;
}

/** Creeaza o programare. */
function appt_book(int $service_id, string $slot_start, ?string $name, ?string $phone, ?string $email = null, ?string $note = null): array {
    $svc = one('SELECT * FROM services WHERE id=? AND status="active"', [$service_id]);
    if (!$svc || empty($svc['appt_enabled'])) throw new RuntimeException('Programarile nu sunt disponibile pentru acest serviciu');
    $ts = strtotime($slot_start);
    if (!$ts) throw new RuntimeException('Interval invalid');
    if ($ts < time() - 60) throw new RuntimeException('Nu se poate programa in trecut');
    if (function_exists('branch_closure_reason') && ($cl = branch_closure_reason((int)$svc['branch_id'], $ts)) !== null)
        throw new RuntimeException('Ziua aleasa este inchisa' . ($cl !== '' ? ' (' . $cl . ')' : '') . '. Alege alta zi.');
    $key = date('Y-m-d H:i:00', $ts);
    $cap = max(1, (int)$svc['appt_capacity']);
    $used = (int) val("SELECT COUNT(*) FROM appointments WHERE service_id=? AND slot_start=? AND status IN ('booked','checked_in')", [$service_id, $key]);
    if ($used >= $cap) throw new RuntimeException('Intervalul tocmai s-a ocupat. Alege altul.');
    $len = max(5, (int)$svc['appt_slot_min']);
    $token = gen_token(16);
    q("INSERT INTO appointments (branch_id,service_id,customer_name,customer_phone,customer_email,slot_start,slot_end,status,public_token,note)
       VALUES (?,?,?,?,?,?,?, 'booked', ?, ?)",
      [$svc['branch_id'], $service_id, $name, $phone, $email, $key, date('Y-m-d H:i:00', $ts+$len*60), $token, $note]);
    $appt = one('SELECT * FROM appointments WHERE id=?', [insert_id()]);
    if (function_exists('fire_webhook')) fire_webhook('appointment.created', webhook_appointment($appt));

    // email de confirmare (best-effort, doar daca emailul e completat si modulul e activ)
    if ($email && function_exists('send_mail') && mail_enabled()) {
        $branch = one('SELECT name, address, city FROM branches WHERE id=?', [$svc['branch_id']]);
        $when = date('d.m.Y', $ts) . ' la ora ' . date('H:i', $ts);
        $loc  = trim(($branch['name'] ?? '') . ($branch['address'] ? ', ' . $branch['address'] : '') . ($branch['city'] ? ', ' . $branch['city'] : ''));
        $body = '<p>Buna' . ($name ? ' <strong>' . e($name) . '</strong>' : '') . ',</p>'
              . '<p>Programarea ta a fost <strong>confirmata</strong>:</p>'
              . '<ul><li>Serviciu: <strong>' . e($svc['name']) . '</strong></li>'
              . '<li>Data: <strong>' . e($when) . '</strong></li>'
              . ($loc ? '<li>Locatie: ' . e($loc) . '</li>' : '') . '</ul>'
              . '<p>In ziua programarii, deschide linkul de mai jos si apasa <strong>Check-in</strong> cand ajungi — primesti automat bonul de ordine.</p>';
        send_mail($email, 'Confirmare programare — ' . $svc['name'],
                  mail_template('Programare confirmata', $body, 'Vezi programarea', url('a/' . $token)));
    }
    return $appt;
}

/** Anuleaza o programare (doar daca e 'booked'). Returneaza randul anulat sau null. */
function appt_cancel(int $id): ?array {
    $a = one('SELECT * FROM appointments WHERE id=?', [$id]);
    if (!$a || $a['status'] !== 'booked') return null;
    q("UPDATE appointments SET status='cancelled' WHERE id=?", [$id]);
    $a['status'] = 'cancelled';
    if (function_exists('fire_webhook')) fire_webhook('appointment.cancelled', webhook_appointment($a));
    return $a;
}

/** Reprogrameaza o programare 'booked' intr-un slot nou (acelasi serviciu). Returneaza randul nou sau null. */
function appt_reschedule(int $id, string $newSlot): ?array {
    $a = one('SELECT * FROM appointments WHERE id=?', [$id]);
    if (!$a || $a['status'] !== 'booked') return null;
    $svc = one('SELECT * FROM services WHERE id=? AND status="active"', [$a['service_id']]);
    if (!$svc || empty($svc['appt_enabled'])) throw new RuntimeException('Programarile nu sunt disponibile pentru acest serviciu');
    $ts = strtotime($newSlot);
    if (!$ts) throw new RuntimeException('Interval invalid');
    if ($ts < time() - 60) throw new RuntimeException('Nu se poate programa in trecut');
    if (function_exists('branch_closure_reason') && ($cl = branch_closure_reason((int)$a['branch_id'], $ts)) !== null)
        throw new RuntimeException('Ziua aleasa este inchisa' . ($cl !== '' ? ' (' . $cl . ')' : '') . '. Alege alta zi.');
    $key = date('Y-m-d H:i:00', $ts); $cap = max(1, (int)$svc['appt_capacity']);
    $used = (int) val("SELECT COUNT(*) FROM appointments WHERE service_id=? AND slot_start=? AND status IN ('booked','checked_in') AND id<>?", [$a['service_id'], $key, $id]);
    if ($used >= $cap) throw new RuntimeException('Intervalul este ocupat. Alege altul.');
    $len = max(5, (int)$svc['appt_slot_min']);
    q("UPDATE appointments SET slot_start=?, slot_end=? WHERE id=?", [$key, date('Y-m-d H:i:00', $ts + $len*60), $id]);
    $a['slot_start'] = $key;
    if (function_exists('fire_webhook')) fire_webhook('appointment.rescheduled', webhook_appointment($a));
    return one('SELECT * FROM appointments WHERE id=?', [$id]);
}

/** Check-in: genereaza biletul si leaga programarea. */
function appt_checkin(array $appt): array {
    if ($appt['status'] === 'checked_in' && $appt['ticket_id']) {
        $t = one('SELECT * FROM tickets WHERE id=?', [$appt['ticket_id']]);
        if ($t) return $t;
    }
    if ($appt['status'] === 'cancelled') throw new RuntimeException('Programare anulata');
    $t = issue_ticket((int)$appt['service_id'], false, 'appointment', $appt['customer_phone']);
    q("UPDATE appointments SET status='checked_in', ticket_id=? WHERE id=?", [$t['id'], $appt['id']]);
    if (function_exists('fire_webhook')) {
        $appt['status'] = 'checked_in'; $appt['ticket_id'] = $t['id'];
        fire_webhook('appointment.checked_in', webhook_appointment($appt) + ['ticket_label' => $t['label']]);
    }
    return $t;
}
