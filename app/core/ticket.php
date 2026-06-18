<?php
/**
 * Logica de gestionare a cozii: emitere bilet, apel urmator, recall,
 * finalizare, no-show, transfer, starea curenta (pt afisaj si terminal).
 */

/**
 * Verifica daca serviciul e in program acum.
 * active_hours = JSON {"enabled":true,"days":{"1":["09:00","17:00"],...}} (0=Dum..6=Sam).
 * Gol / dezactivat => mereu deschis.
 */
/** True daca serviciul are plafon zilnic si l-a atins azi (pt a-l marca indisponibil pe dispenser). */
function service_cap_reached(array $svc): bool {
    $cap = (int)($svc['max_per_day'] ?? 0);
    if ($cap <= 0 || empty($svc['id'])) return false;
    return (int) val('SELECT COUNT(*) FROM tickets WHERE service_id = ? AND DATE(issued_at) = CURDATE()', [(int)$svc['id']]) >= $cap;
}

function service_is_open(array $svc, ?int $ts = null): bool {
    // pauza temporara pe serviciu -> inchis, indiferent de orar
    if (!empty($svc['paused'])) return false;
    $ts = $ts ?? time();
    if (isset($svc['branch_id'])) {
        // zi inchisa (sarbatoare / inchidere) pe filiala sau globala -> inchis, indiferent de orar
        if (branch_closure_reason((int)$svc['branch_id'], $ts) !== null) return false;
        // orar de functionare al filialei (plic): in afara lui, totul e inchis
        $bw = branch_hours_window((int)$svc['branch_id'], $ts);
        if ($bw === null) return false;                       // filiala inchisa in ziua aceea
        if (is_array($bw)) { $now = date('H:i', $ts); if ($now < $bw[0] || $now >= $bw[1]) return false; }
    }
    $raw = trim((string)($svc['active_hours'] ?? ''));
    if ($raw === '') return true;
    $cfg = json_decode($raw, true);
    if (!is_array($cfg) || empty($cfg['enabled'])) return true;
    $dow = (int)date('w', $ts);
    $day = $cfg['days'][$dow] ?? ($cfg['days'][(string)$dow] ?? null);
    if (!is_array($day) || count($day) < 2 || !$day[0] || !$day[1]) return false;
    $now = date('H:i', $ts);
    return $now >= $day[0] && $now < $day[1];
}

/**
 * Orarul de functionare al filialei pentru momentul $ts:
 *   false = fara orar configurat (mereu deschis), null = inchisa in ziua aceea, [open,close] = interval.
 * Formatul open_hours e identic cu active_hours: {"enabled":true,"days":{"1":["09:00","17:00"],...}}.
 */
function branch_hours_window(int $branchId, ?int $ts = null): array|null|false {
    static $cache = [];
    if (!array_key_exists($branchId, $cache)) {
        $cache[$branchId] = false;
        try {
            $raw = trim((string) val('SELECT open_hours FROM branches WHERE id=?', [$branchId]));
            if ($raw !== '') {
                $cfg = json_decode($raw, true);
                if (is_array($cfg) && !empty($cfg['enabled'])) $cache[$branchId] = $cfg;
            }
        } catch (Throwable $e) { $cache[$branchId] = false; }
    }
    $cfg = $cache[$branchId];
    if ($cfg === false) return false;
    $ts = $ts ?? time();
    $dow = (int)date('w', $ts);
    $day = $cfg['days'][$dow] ?? ($cfg['days'][(string)$dow] ?? null);
    if (!is_array($day) || count($day) < 2 || !$day[0] || !$day[1]) return null;
    return [$day[0], $day[1]];
}

/**
 * Motivul inchiderii (sarbatoare) pentru o filiala la o data, sau null daca e zi lucratoare.
 * O inchidere globala (branch_id NULL) acopera toate filialele. Sirul gol = inchis fara motiv.
 */
function branch_closure_reason(int $branchId, ?int $ts = null): ?string {
    $date = date('Y-m-d', $ts ?? time());
    static $cache = [];
    if (!array_key_exists($date, $cache)) {
        $cache[$date] = [];
        try {
            foreach (all("SELECT branch_id, reason FROM branch_closures WHERE closed_date=?", [$date]) as $r)
                $cache[$date][(int)$r['branch_id']] = (string)($r['reason'] ?? ''); // 0 = global (branch_id NULL)
        } catch (Throwable $e) { $cache[$date] = []; }
    }
    $map = $cache[$date];
    if (array_key_exists(0, $map)) return $map[0];              // inchidere globala
    if (array_key_exists($branchId, $map)) return $map[$branchId];
    return null;
}

/**
 * Urmatorul moment in care serviciul redevine deschis (timestamp), sau null daca nu se poate
 * determina in ~14 zile (sau e oprit temporar). Reutilizeaza service_is_open ca sa nu divergam
 * de logica de orar (serviciu ∩ filiala + inchideri). Cautare la pas de 5 min, cu oprire devreme.
 */
function service_next_open(array $svc, ?int $from = null): ?int {
    if (!empty($svc['paused'])) return null;        // pauza: nu stim cand revine
    $from = $from ?? time();
    $step = 300; $limit = $from + 14 * 86400;
    $t = $from - ($from % $step) + $step;           // urmatorul interval rotund de 5 min
    for (; $t <= $limit; $t += $step) {
        if (service_is_open($svc, $t)) return $t;
    }
    return null;
}

/** Formateaza eticheta biletului (ex: C001 / C1). */
function format_label(array $service, int $number): string {
    $n = $service['include_zeros']
        ? str_pad((string)$number, max(1, (int)$service['pad_length']), '0', STR_PAD_LEFT)
        : (string)$number;
    return $service['prefix'] . $n;
}

/** Urmatorul numar pentru un serviciu (atomic, reset zilnic). */
function next_number(array $service): int {
    $today = date('Y-m-d');
    db()->beginTransaction();
    try {
        q('INSERT INTO ticket_sequences (service_id, seq_date, last_number)
           VALUES (?, ?, 1)
           ON DUPLICATE KEY UPDATE last_number = last_number + 1',
          [$service['id'], $today]);
        $n = (int) val('SELECT last_number FROM ticket_sequences WHERE service_id = ? AND seq_date = ?',
                       [$service['id'], $today]);
        // wrap-around la num_to
        $from = (int)$service['num_from']; $to = (int)$service['num_to'];
        $range = max(1, $to - $from + 1);
        $number = $from + (($n - 1) % $range);
        db()->commit();
        return $number;
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
}

/** Emite un bilet nou. Returneaza randul complet din DB. */
function issue_ticket(int $service_id, bool $priority = false, string $channel = 'paper', ?string $phone = null, ?string $form_data = null): array {
    $svc = one('SELECT * FROM services WHERE id = ? AND status = "active"', [$service_id]);
    if (!$svc) throw new RuntimeException('Serviciu indisponibil');
    if (!empty($svc['paused'])) throw new RuntimeException('Serviciul este oprit temporar');
    $closure = branch_closure_reason((int)$svc['branch_id']);
    if ($closure !== null) throw new RuntimeException('Inchis astazi' . ($closure !== '' ? ' · ' . $closure : ''));
    if (!service_is_open($svc)) throw new RuntimeException('Serviciul este inchis in acest moment');

    if ((int)$svc['max_queued'] > 0) {
        $waiting = (int) val('SELECT COUNT(*) FROM tickets WHERE service_id = ? AND status = "waiting"', [$service_id]);
        if ($waiting >= (int)$svc['max_queued']) throw new RuntimeException('Coada este plina pentru acest serviciu');
    }
    if ((int)($svc['max_per_day'] ?? 0) > 0) {
        $today = (int) val('SELECT COUNT(*) FROM tickets WHERE service_id = ? AND DATE(issued_at) = CURDATE()', [$service_id]);
        if ($today >= (int)$svc['max_per_day']) throw new RuntimeException('Limita zilnica de bonuri a fost atinsa pentru acest serviciu');
    }
    if (!$svc['allow_priority']) $priority = false;

    $number = next_number($svc);
    $label  = format_label($svc, $number);
    $token  = gen_token(16);

    q('INSERT INTO tickets (branch_id, service_id, number, label, priority, status, channel, customer_phone, public_token, form_data)
       VALUES (?, ?, ?, ?, ?, "waiting", ?, ?, ?, ?)',
      [$svc['branch_id'], $service_id, $number, $label, $priority ? 1 : 0, $channel, $phone, $token, $form_data]);

    $id = insert_id();
    $t = one('SELECT * FROM tickets WHERE id = ?', [$id]);
    fire_webhook('ticket.created', webhook_ticket($t));
    return $t;
}

/** ID-urile serviciilor pe care le deserveste un ghiseu. */
function counter_service_ids(array $counter): array {
    if ((int)$counter['all_services'] === 1) {
        return array_map('intval', array_column(
            all('SELECT id FROM services WHERE branch_id = ? AND status = "active"', [$counter['branch_id']]), 'id'));
    }
    return array_map('intval', array_column(
        all('SELECT service_id AS id FROM counter_services WHERE counter_id = ?', [$counter['id']]), 'id'));
}

/** Pozitia in coada + cati sunt inainte (pt bilet digital). */
function ticket_position(array $t): int {
    return (int) val(
        'SELECT COUNT(*) FROM tickets
         WHERE service_id = ? AND status = "waiting"
           AND (priority > ? OR (priority = ? AND issued_at < ?) OR (priority = ? AND issued_at = ? AND id < ?))',
        [$t['service_id'], $t['priority'], $t['priority'], $t['issued_at'], $t['priority'], $t['issued_at'], $t['id']]
    );
}

/** Timp estimat de asteptare (secunde) = pozitie x timp mediu servire / nr. ghisee deschise. */
function est_wait_seconds(array $t): int {
    $pos = ticket_position($t);
    if ($pos <= 0) return 0;
    $avg = (int) (val("SELECT AVG(TIMESTAMPDIFF(SECOND, called_at, finished_at)) FROM tickets
                       WHERE service_id = ? AND status = 'served' AND called_at IS NOT NULL
                         AND finished_at >= NOW() - INTERVAL 7 DAY", [$t['service_id']]) ?? 0);
    if ($avg <= 0) { $svc = one('SELECT kpi_service_sec FROM services WHERE id=?', [$t['service_id']]); $avg = (int)($svc['kpi_service_sec'] ?? 0) ?: 300; }
    $open = max(1, (int) val("SELECT COUNT(*) FROM counters WHERE branch_id=? AND status='open'", [$t['branch_id']]));
    return (int) round($pos * $avg / $open);
}

/**
 * Apeleaza urmatorul bilet pentru un ghiseu.
 * Alege: prioritate DESC, apoi cel mai vechi. Tranzactie cu lock.
 */
function call_next(int $counter_id, int $user_id, int $service_id = 0): ?array {
    $counter = one('SELECT * FROM counters WHERE id = ?', [$counter_id]);
    if (!$counter) throw new RuntimeException('Ghiseu inexistent');
    $svcIds = counter_service_ids($counter);

    if ($service_id > 0) {
        // urmatorul dintr-un serviciu anume (din filiala ghiseului)
        $cond = "t.service_id = ? AND t.branch_id = ?"; $args = [$service_id, (int)$counter['branch_id']];
    } else {
        // bilete eligibile: directionate catre acest ghiseu (prioritar) + serviciile ghiseului
        $cond = "t.target_counter_id = ?"; $args = [$counter_id];
        if ($svcIds) {
            $in = implode(',', array_fill(0, count($svcIds), '?'));
            $cond .= " OR (t.target_counter_id IS NULL AND t.service_id IN ($in))";
            $args = array_merge($args, $svcIds);
        }
    }
    // anti-starvation: biletele care asteapta peste N minute capata prioritate efectiva (0 = oprit)
    $esc = (int) setting('priority_escalate_min', '0');
    $escExpr = $esc > 0 ? "IF(TIMESTAMPDIFF(MINUTE, t.issued_at, NOW()) >= $esc, 1, 0)" : "0";
    db()->beginTransaction();
    try {
        // urmatorul bilet: intai cele transferate la acest ghiseu, apoi dupa prioritate (efectiva)/vechime
        $row = one("SELECT t.* FROM tickets t
                    WHERE t.status = 'waiting' AND ($cond)
                    ORDER BY (t.target_counter_id = ?) DESC, GREATEST(t.priority, $escExpr) DESC, t.issued_at ASC, t.id ASC
                    LIMIT 1 FOR UPDATE", array_merge($args, [$counter_id]));
        if (!$row) { db()->commit(); return null; }

        // marcheaza precedentul bilet servit de acest ghiseu ca 'served' daca inca era 'serving'
        q("UPDATE tickets SET status = 'served', finished_at = NOW()
           WHERE counter_id = ? AND status IN ('called','serving')", [$counter_id]);

        $svc = one('SELECT * FROM services WHERE id = ?', [$row['service_id']]);
        $newStatus = ((int)$svc['terminate_on_call'] === 1) ? 'served' : 'called';
        q("UPDATE tickets SET status = ?, counter_id = ?, agent_id = ?, called_at = NOW(), target_counter_id = NULL" .
          ($newStatus === 'served' ? ", served_at = NOW(), finished_at = NOW()" : "") . " WHERE id = ?",
          [$newStatus, $counter_id, $user_id, $row['id']]);

        db()->commit();
        $t = one('SELECT * FROM tickets WHERE id = ?', [$row['id']]);
        fire_webhook('ticket.called', webhook_ticket($t));
        return $t;
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
}

/** Apeleaza un bilet ANUME (ales de operator) la un ghiseu, indiferent de ordine/prioritate. */
function call_specific(int $ticket_id, int $counter_id, int $user_id): ?array {
    $counter = one('SELECT * FROM counters WHERE id = ?', [$counter_id]);
    if (!$counter) throw new RuntimeException('Ghiseu inexistent');
    db()->beginTransaction();
    try {
        $row = one("SELECT * FROM tickets WHERE id = ? AND status = 'waiting' FOR UPDATE", [$ticket_id]);
        if (!$row) { db()->rollBack(); return null; } // deja chemat/anulat sau inexistent
        if ((int)$row['branch_id'] !== (int)$counter['branch_id']) { db()->rollBack(); throw new RuntimeException('Biletul este din alta filiala'); }

        // inchide biletul curent al ghiseului (daca era in lucru)
        q("UPDATE tickets SET status = 'served', finished_at = NOW()
           WHERE counter_id = ? AND status IN ('called','serving')", [$counter_id]);

        $svc = one('SELECT * FROM services WHERE id = ?', [$row['service_id']]);
        $newStatus = ((int)$svc['terminate_on_call'] === 1) ? 'served' : 'called';
        q("UPDATE tickets SET status = ?, counter_id = ?, agent_id = ?, called_at = NOW(), target_counter_id = NULL" .
          ($newStatus === 'served' ? ", served_at = NOW(), finished_at = NOW()" : "") . " WHERE id = ?",
          [$newStatus, $counter_id, $user_id, $ticket_id]);

        db()->commit();
        $t = one('SELECT * FROM tickets WHERE id = ?', [$ticket_id]);
        fire_webhook('ticket.called', webhook_ticket($t));
        return $t;
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        throw $ex;
    }
}

/** Eveniment webhook dupa o tranzitie de status. */
function ticket_event(int $ticket_id, string $event): void {
    fire_webhook($event, webhook_ticket(one('SELECT * FROM tickets WHERE id = ?', [$ticket_id])));
}

/** Transfera biletul catre alt GHISEU (birou): revine la rand, directionat catre acel ghiseu. */
function transfer_to_counter(int $ticket_id, int $target_counter_id): void {
    $c = one('SELECT * FROM counters WHERE id = ?', [$target_counter_id]);
    if (!$c) throw new RuntimeException('Ghiseu inexistent');
    q("UPDATE tickets SET status = 'waiting', counter_id = NULL, agent_id = NULL,
        called_at = NULL, served_at = NULL, finished_at = NULL, target_counter_id = ?
       WHERE id = ?", [$target_counter_id, $ticket_id]);
    ticket_event($ticket_id, 'ticket.transferred');
}

/** Salveaza o nota interna pe bilet (operator). Gol = sterge nota. */
function set_ticket_note(int $ticket_id, string $note): void {
    $note = trim($note);
    q("UPDATE tickets SET note=? WHERE id=?", [$note !== '' ? mb_substr($note, 0, 255) : null, $ticket_id]);
}

/** Recheamă un bilet. După 'max_recalls' rechemări (>0), îl marchează automat neprezentat. Returneaza statusul rezultat. */
function recall_ticket(int $ticket_id): string {
    $row = one("SELECT recall_count, status FROM tickets WHERE id=?", [$ticket_id]);
    if (!$row) return '';
    $new = (int)$row['recall_count'] + 1;
    $max = (int) setting('max_recalls', '0');
    if ($max > 0 && $new > $max && $row['status'] === 'called') {
        q("UPDATE tickets SET status='no_show', finished_at=NOW(), recall_count=? WHERE id=?", [$new, $ticket_id]);
        ticket_event($ticket_id, 'ticket.no_show');
        return 'no_show';
    }
    q("UPDATE tickets SET called_at = NOW(), recall_count = ?,
        status = CASE WHEN status IN ('served','no_show','cancelled') THEN 'called' ELSE status END
       WHERE id = ?", [$new, $ticket_id]);
    ticket_event($ticket_id, 'ticket.recalled');
    return 'called';
}
function start_serving(int $ticket_id): void {
    $st = q("UPDATE tickets SET status = 'serving', served_at = COALESCE(served_at, NOW()) WHERE id = ? AND status = 'called'", [$ticket_id]);
    if ($st->rowCount() > 0) ticket_event($ticket_id, 'ticket.serving');
}
function finish_ticket(int $ticket_id): void {
    $st = q("UPDATE tickets SET status = 'served', served_at = COALESCE(served_at, NOW()), finished_at = NOW()
       WHERE id = ? AND status IN ('called','serving')", [$ticket_id]);
    if ($st->rowCount() > 0) ticket_event($ticket_id, 'ticket.served');
}
function no_show_ticket(int $ticket_id): void {
    $st = q("UPDATE tickets SET status = 'no_show', finished_at = NOW() WHERE id = ? AND status IN ('called','serving')", [$ticket_id]);
    if ($st->rowCount() > 0) ticket_event($ticket_id, 'ticket.no_show');
}
function cancel_ticket(int $ticket_id): void {
    $st = q("UPDATE tickets SET status = 'cancelled', finished_at = NOW() WHERE id = ? AND status IN ('waiting','called','serving')", [$ticket_id]);
    if ($st->rowCount() > 0) ticket_event($ticket_id, 'ticket.cancelled');
}
/** Transfera biletul catre alt serviciu (revine in asteptare). */
function transfer_ticket(int $ticket_id, int $service_id): void {
    q("UPDATE tickets SET service_id = ?, status = 'waiting', counter_id = NULL, agent_id = NULL,
        called_at = NULL, served_at = NULL, finished_at = NULL, target_counter_id = NULL, recall_count = 0
       WHERE id = ?", [$service_id, $ticket_id]);
    ticket_event($ticket_id, 'ticket.transferred');
}

/** Elibereaza biletele directionate catre un ghiseu (in asteptare) inapoi in coada generala a serviciului. */
function release_targeted_tickets(int $counter_id): int {
    return q("UPDATE tickets SET target_counter_id = NULL WHERE target_counter_id = ? AND status = 'waiting'", [$counter_id])->rowCount();
}

/** Repune un bilet neprezentat inapoi la rand (clientul a ajuns tarziu). Merge la coada, cu ora curenta. */
function requeue_ticket(int $ticket_id): bool {    $st = q("UPDATE tickets SET status='waiting', counter_id=NULL, called_at=NULL, served_at=NULL, finished_at=NULL,
                    issued_at=NOW(), recall_count=0
             WHERE id=? AND status='no_show'", [$ticket_id]);
    if ($st->rowCount() > 0) { ticket_event($ticket_id, 'ticket.created'); return true; }
    return false;
}

/** Coada unei filiale pentru modul Concierge: bilete individuale la rand + ghisee. */
function branch_queue(int $branch_id): array {
    $waiting = all(
        "SELECT t.id, t.label, t.priority, t.issued_at, s.name AS service_name, s.color
         FROM tickets t JOIN services s ON s.id = t.service_id
         WHERE t.branch_id = ? AND t.status = 'waiting'
         ORDER BY t.priority DESC, t.issued_at ASC LIMIT 100", [$branch_id]);
    $counters = all(
        "SELECT c.id, c.code, c.name, c.status, c.pause_note,
                (SELECT t.label FROM tickets t WHERE t.counter_id = c.id AND t.status IN ('called','serving')
                 ORDER BY t.called_at DESC LIMIT 1) AS current_label
         FROM counters c WHERE c.branch_id = ? ORDER BY c.code", [$branch_id]);
    // neprezentati azi (pentru repunere la rand din receptie)
    $no_show = all(
        "SELECT t.id, t.label, s.name AS service_name, s.color, TIME_FORMAT(t.finished_at,'%H:%i') AS at
         FROM tickets t JOIN services s ON s.id = t.service_id
         WHERE t.branch_id = ? AND t.status='no_show' AND DATE(t.finished_at)=CURDATE()
         ORDER BY t.finished_at DESC LIMIT 12", [$branch_id]);
    return ['waiting' => $waiting, 'waiting_count' => count($waiting), 'counters' => $counters, 'no_show' => $no_show];
}

/**
 * Starea curenta a cozii pentru o filiala.
 * Folosita de afisaj (player) si terminal (polling/SSE).
 */
function queue_state(int $branch_id, bool $withEstimates = false): array {
    // ultimele bilete chemate (pt lista pe TV)
    $called = all(
        "SELECT t.id, t.label, t.status, t.priority, t.called_at, t.recall_count,
                s.name AS service_name, s.prefix, s.color,
                c.code AS counter_code, c.name AS counter_name
         FROM tickets t
         JOIN services s ON s.id = t.service_id
         LEFT JOIN counters c ON c.id = t.counter_id
         WHERE t.branch_id = ? AND t.status IN ('called','serving')
         ORDER BY t.called_at DESC
         LIMIT 8", [$branch_id]);

    // bilete in asteptare pe serviciu
    $waiting = all(
        "SELECT s.id, s.prefix, s.name, s.color, COUNT(t.id) AS cnt
         FROM services s
         LEFT JOIN tickets t ON t.service_id = s.id AND t.status = 'waiting'
         WHERE s.branch_id = ? AND s.status = 'active'
         GROUP BY s.id ORDER BY s.sort_order", [$branch_id]);

    // (optional) timp estimat de asteptare per serviciu — calculat doar la cerere (nu pe fiecare tick SSE)
    if ($withEstimates) {
        $open = max(1, (int) val("SELECT COUNT(*) FROM counters WHERE branch_id=? AND status='open'", [$branch_id]));
        $avgBySvc = [];
        foreach (all("SELECT service_id, AVG(TIMESTAMPDIFF(SECOND, called_at, finished_at)) a
                      FROM tickets WHERE branch_id=? AND status='served' AND called_at IS NOT NULL
                        AND finished_at >= NOW() - INTERVAL 7 DAY GROUP BY service_id", [$branch_id]) as $r)
            $avgBySvc[(int)$r['service_id']] = (float)$r['a'];
        foreach ($waiting as &$w) {
            $cnt = (int)$w['cnt'];
            $avg = $avgBySvc[(int)$w['id']] ?? 0; if ($avg <= 0) $avg = 300; // fallback 5 min
            $w['est'] = $cnt > 0 ? (int) round($cnt * $avg / $open) : 0;
        }
        unset($w);
    }

    // ghisee si biletul curent
    $counters = all(
        "SELECT c.id, c.code, c.name, c.status, c.pause_note,
                (SELECT t.label FROM tickets t WHERE t.counter_id = c.id AND t.status IN ('called','serving')
                 ORDER BY t.called_at DESC LIMIT 1) AS current_label
         FROM counters c WHERE c.branch_id = ? ORDER BY c.code", [$branch_id]);

    $last = $called[0] ?? null;
    return [
        'ts'       => time(),
        'last'     => $last,
        'called'   => $called,
        'waiting'  => $waiting,
        'counters' => $counters,
        'notice'   => active_notice(),   // anunt general (banner) — live pe toate afisajele
    ];
}

/** Lista pentru terminalul operatorului (coada + ultimele chemate). */
function counter_view(array $counter): array {
    $cid = (int)$counter['id'];
    $svcIds = counter_service_ids($counter);
    // bilete la rand: cele directionate exact catre acest ghiseu + cele ale serviciilor sale (nedirectionate altundeva)
    $cond = "t.target_counter_id = ?"; $args = [$cid];
    if ($svcIds) {
        $in = implode(',', array_fill(0, count($svcIds), '?'));
        $cond .= " OR (t.target_counter_id IS NULL AND t.service_id IN ($in))";
        $args = array_merge($args, $svcIds);
    }
    $waiting = all(
        "SELECT t.id, t.label, t.priority, t.issued_at, t.form_data, t.target_counter_id, t.note,
                s.name AS service_name, s.color, s.kpi_wait_sec,
                TIMESTAMPDIFF(SECOND, t.issued_at, NOW()) AS waited
         FROM tickets t JOIN services s ON s.id = t.service_id
         WHERE t.status = 'waiting' AND ($cond)
         ORDER BY (t.target_counter_id = ?) DESC, t.priority DESC, t.issued_at ASC LIMIT 50",
        array_merge($args, [$cid]));
    $current = one(
        "SELECT t.*, s.name AS service_name, s.color FROM tickets t
         JOIN services s ON s.id = t.service_id
         WHERE t.counter_id = ? AND t.status IN ('called','serving')
         ORDER BY t.called_at DESC LIMIT 1", [$counter['id']]);
    // neprezentati recent (azi) pe serviciile ghiseului — pot fi repusi la rand daca ajung tarziu
    $no_show = [];
    if ($svcIds) {
        $in = implode(',', array_fill(0, count($svcIds), '?'));
        $no_show = all(
            "SELECT t.id, t.label, s.name AS service_name, s.color,
                    TIME_FORMAT(t.finished_at, '%H:%i') AS at
             FROM tickets t JOIN services s ON s.id = t.service_id
             WHERE t.status='no_show' AND DATE(t.finished_at)=CURDATE() AND t.service_id IN ($in)
             ORDER BY t.finished_at DESC LIMIT 8", $svcIds);
    }
    return ['current' => $current, 'waiting' => $waiting, 'waiting_count' => count($waiting), 'no_show' => $no_show];
}
