<?php
/** Strat minimal de acces la baza de date (PDO). */

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $c = $GLOBALS['__config']['db'];
    $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // aliniaza fusul orar al sesiunii MySQL cu cel al PHP, ca NOW()/CURDATE() sa coincida
        // cu date() (altfel numerele de bilet / statisticile / reminderele sar peste miezul noptii
        // pe un host de DB cu alt fus). Folosim offsetul curent => nu necesita tabelele tz din MySQL.
        try {
            $tz  = $GLOBALS['__config']['app']['timezone'] ?? 'Europe/Bucharest';
            $off = (new DateTime('now', new DateTimeZone($tz)))->format('P');
            $pdo->exec("SET time_zone = '" . $off . "'");
        } catch (Throwable $e) { /* fus orar implicit al serverului */ }
    } catch (PDOException $e) {
        @error_log('[bon-de-ordine] DB connect: ' . $e->getMessage());
        // pagina prietenoasa, fara a expune detalii de configurare utilizatorului final
        fail_page(503, 'Serviciu indisponibil',
            'Serviciul este temporar indisponibil. Te rugăm reîncearcă în câteva minute.', $e->getMessage());
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function one(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r ?: null; }
function val(string $sql, array $params = []) { $r = q($sql, $params)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }
function insert_id(): int { return (int) db()->lastInsertId(); }
