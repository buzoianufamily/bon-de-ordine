<?php
/** Programari: sloturi disponibile, rezervare, check-in (genereaza bilet). */

/** Fereastra de program [open,close] pentru o data, din active_hours (sau implicit 09-17),
 *  intersectata cu orarul filialei daca acesta e configurat. null = inchis in ziua aceea. */
function appt_open_window(array $svc, string $date): ?array {
    $raw = trim((string)($svc['active_hours'] ?? ''));
    $dow = (int)date('w', strtotime($date));
    $win = ['09:00', '17:00'];
    if ($raw !== '') {
        $cfg = json_decode($raw, true);
        if (is_array($cfg) && !empty($cfg['enabled'])) {
            $day = $cfg['days'][$dow] ?? ($cfg['days'][(string)$dow] ?? null);
            if (!is_array($day) || count($day) < 2 || !$day[0] || !$day[1]) return null; // inchis in ziua aceea
            $win = [$day[0], $day[1]];
        }
    }
    // intersecteaza cu orarul filialei (daca e configurat la nivel de filiala)
    if (isset($svc['branch_id']) && function_exists('branch_hours_window')) {
        $bw = branch_hours_window((int)$svc['branch_id'], strtotime($date . ' 12:00:00'));
        if ($bw === null) return null;                              // filiala inchisa in ziua aceea
        if (is_array($bw)) {
            $open = max($win[0], $bw[0]); $close = min($win[1], $bw[1]);
            if ($open >= $close) return null;                       // fara suprapunere -> niciun slot
            $win = [$open, $close];
        }
    }
    return $win;
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

/** Prima zi (dupa $fromDate) cu cel putin un slot liber, in urmatoarele $maxDays zile. null daca nu. */
function appt_next_open_day(array $svc, string $fromDate, int $maxDays = 21): ?string {
    $start = strtotime($fromDate);
    if (!$start) return null;
    for ($i = 1; $i <= $maxDays; $i++) {
        $d = date('Y-m-d', strtotime("+$i day", $start));
        foreach (appt_slots($svc, $d) as $sl) {
            if (empty($sl['past']) && empty($sl['full'])) return $d;
        }
    }
    return null;
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
    $len = max(5, (int)$svc['appt_slot_min']);
    $token = gen_token(16);
    // verificare capacitate + inserare ATOMICA (tranzactie + lock pe slot via idx_appt_slot)
    // — fara asta, doua rezervari simultane pot trece amandoua de verificare si suprarezerva slotul
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $used = (int) val("SELECT COUNT(*) FROM appointments WHERE service_id=? AND slot_start=? AND status IN ('booked','checked_in') FOR UPDATE", [$service_id, $key]);
        if ($used >= $cap) throw new RuntimeException('Intervalul tocmai s-a ocupat. Alege altul.');
        q("INSERT INTO appointments (branch_id,service_id,customer_name,customer_phone,customer_email,slot_start,slot_end,status,public_token,note)
           VALUES (?,?,?,?,?,?,?, 'booked', ?, ?)",
          [$svc['branch_id'], $service_id, $name, $phone, $email, $key, date('Y-m-d H:i:00', $ts+$len*60), $token, $note]);
        $newId = insert_id();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $appt = one('SELECT * FROM appointments WHERE id=?', [$newId]);
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
              . '<p>In ziua programarii, deschide linkul de mai jos si apasa <strong>Check-in</strong> cand ajungi — primesti automat bonul de ordine.</p>'
              . '<p><a href="' . e(url('a/' . $token . '/ics')) . '">📅 Adauga in calendar</a></p>';
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
    appt_waitlist_notify((int)$a['service_id'], (string)$a['slot_start']);  // s-a eliberat un loc
    return $a;
}

/** Numar de locuri ocupate intr-un slot (booked + checked_in). */
function appt_slot_used(int $service_id, string $slot_key): int {
    return (int) val("SELECT COUNT(*) FROM appointments WHERE service_id=? AND slot_start=? AND status IN ('booked','checked_in')", [$service_id, $slot_key]);
}

/** Inscrie un client pe lista de asteptare a unui slot plin. Arunca exceptie la date invalide. */
function appt_waitlist_add(int $service_id, string $slot_start, ?string $name, string $email, ?string $phone = null): bool {
    $svc = one('SELECT * FROM services WHERE id=? AND status="active" AND appt_enabled=1', [$service_id]);
    if (!$svc) throw new RuntimeException('Serviciu indisponibil pentru programari.');
    $ts = strtotime($slot_start);
    if ($ts === false || $ts < time()) throw new RuntimeException('Interval invalid.');
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Adresa de email este invalida.');
    $key = date('Y-m-d H:i:00', $ts);
    if (appt_slot_used($service_id, $key) < max(1, (int)$svc['appt_capacity']))
        throw new RuntimeException('Mai sunt locuri libere — poti rezerva direct.');
    $dup = (int) val("SELECT COUNT(*) FROM appointment_waitlist WHERE service_id=? AND slot_start=? AND customer_email=? AND notified_at IS NULL", [$service_id, $key, $email]);
    if (!$dup) {
        q("INSERT INTO appointment_waitlist (service_id, branch_id, slot_start, customer_name, customer_email, customer_phone) VALUES (?,?,?,?,?,?)",
          [$service_id, (int)$svc['branch_id'], $key, $name ? mb_substr($name, 0, 120) : null, $email, $phone ?: null]);
    }
    return true;
}

/** Daca un slot are loc liber si exista cineva pe lista, il anunta pe email. Returneaza intrarea anuntata sau null. */
function appt_waitlist_notify(int $service_id, string $slot_start): ?array {
    $svc = one('SELECT * FROM services WHERE id=?', [$service_id]);
    if (!$svc) return null;
    $key = date('Y-m-d H:i:00', strtotime($slot_start));
    if (appt_slot_used($service_id, $key) >= max(1, (int)$svc['appt_capacity'])) return null; // tot plin
    $w = one("SELECT * FROM appointment_waitlist WHERE service_id=? AND slot_start=? AND notified_at IS NULL ORDER BY id LIMIT 1", [$service_id, $key]);
    if (!$w) return null;
    q("UPDATE appointment_waitlist SET notified_at=NOW() WHERE id=?", [(int)$w['id']]);
    if (function_exists('send_mail') && mail_enabled()) {
        $when = date('d.m.Y H:i', strtotime($key));
        $body = '<p>Buna' . ($w['customer_name'] ? ' <strong>' . e($w['customer_name']) . '</strong>' : '') . ',</p>'
              . '<p>S-a eliberat un loc pentru <strong>' . e($svc['name']) . '</strong> la <strong>' . e($when) . '</strong>.</p>'
              . '<p>Rezerva acum — primul venit, primul servit:</p>';
        send_mail($w['customer_email'], 'S-a eliberat un loc — ' . $svc['name'],
            mail_template('Loc disponibil', $body, 'Rezerva acum', url('book/' . $service_id . '?date=' . substr($key, 0, 10))));
    }
    return $w;
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
    $len = max(5, (int)$svc['appt_slot_min']);
    // verificare + mutare ATOMICA (tranzactie + lock pe slot) — anti-suprarezervare la reprogramari simultane
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $used = (int) val("SELECT COUNT(*) FROM appointments WHERE service_id=? AND slot_start=? AND status IN ('booked','checked_in') AND id<>? FOR UPDATE", [$a['service_id'], $key, $id]);
        if ($used >= $cap) throw new RuntimeException('Intervalul este ocupat. Alege altul.');
        q("UPDATE appointments SET slot_start=?, slot_end=? WHERE id=?", [$key, date('Y-m-d H:i:00', $ts + $len*60), $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $a['slot_start'] = $key;
    if (function_exists('fire_webhook')) fire_webhook('appointment.rescheduled', webhook_appointment($a));
    return one('SELECT * FROM appointments WHERE id=?', [$id]);
}

/** Check-in: genereaza biletul si leaga programarea. Idempotent + sigur la dublu check-in. */
function appt_checkin(array $appt): array {
    $id = (int)$appt['id'];
    // cale rapida: deja are bilet
    if ($appt['status'] === 'checked_in' && !empty($appt['ticket_id'])) {
        $t = one('SELECT * FROM tickets WHERE id=?', [$appt['ticket_id']]);
        if ($t) return $t;
    }
    if ($appt['status'] === 'cancelled') throw new RuntimeException('Programare anulata');
    // revendicare ATOMICA: o singura cerere trece 'booked' -> 'checked_in' (anti dublu check-in / dublu bilet).
    // Nu putem folosi o tranzactie cu lock peste issue_ticket (acesta isi deschide propria tranzactie).
    $claimed = q("UPDATE appointments SET status='checked_in' WHERE id=? AND status='booked'", [$id])->rowCount();
    if ($claimed === 0) {
        // alta cerere a revendicat deja (sau programarea e anulata) — intoarce biletul existent
        $a = one('SELECT status, ticket_id FROM appointments WHERE id=?', [$id]);
        if (!$a) throw new RuntimeException('Programare inexistenta');
        if ($a['status'] === 'cancelled') throw new RuntimeException('Programare anulata');
        for ($i = 0; $i < 20 && empty($a['ticket_id']); $i++) { usleep(50000); $a = one('SELECT ticket_id FROM appointments WHERE id=?', [$id]); }
        if (!empty($a['ticket_id']) && ($t = one('SELECT * FROM tickets WHERE id=?', [$a['ticket_id']]))) return $t;
        throw new RuntimeException('Check-in deja in curs. Reincarca pagina.');
    }
    // am revendicat: emite biletul si leaga-l (cu revenire la 'booked' daca emiterea esueaza)
    try {
        $t = issue_ticket((int)$appt['service_id'], false, 'appointment', $appt['customer_phone']);
    } catch (Throwable $e) {
        q("UPDATE appointments SET status='booked' WHERE id=? AND status='checked_in' AND ticket_id IS NULL", [$id]);
        throw $e;
    }
    q("UPDATE appointments SET ticket_id=? WHERE id=?", [$t['id'], $id]);
    if (function_exists('fire_webhook')) {
        $appt['status'] = 'checked_in'; $appt['ticket_id'] = $t['id'];
        fire_webhook('appointment.checked_in', webhook_appointment($appt) + ['ticket_label' => $t['label']]);
    }
    return $t;
}
