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
    } catch (PDOException $e) {
        http_response_code(500);
        if (($GLOBALS['__config']['app']['env'] ?? '') === 'dev') {
            die('Eroare conexiune DB: ' . $e->getMessage());
        }
        die('Eroare conexiune baza de date. Verifica config/config.php');
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
