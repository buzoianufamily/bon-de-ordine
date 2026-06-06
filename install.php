<?php
/**
 * INSTALATOR — Sistem de Bon de Ordine
 * Ruleaza acest fisier O SINGURA DATA dupa ce ai editat config/config.php.
 * Pasi: testeaza DB -> importa schema + date demo -> creeaza primul admin.
 * IMPORTANT: dupa instalare STERGE acest fisier (install.php).
 */
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors', '1');
$config = require __DIR__ . '/config/config.php';
$token  = $config['setup_token'] ?? '';
$msgs = []; $err = []; $done = false;

function pdo_connect(array $c): PDO {
    $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
    return new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function run_sql(PDO $pdo, string $file): int {
    $sql = file_get_contents($file);
    // 1) elimina liniile de comentariu (-- ...) ca sa nu "inghita" instructiunile de sub ele
    $lines = preg_split('/\r?\n/', $sql);
    $kept = [];
    foreach ($lines as $ln) { if (preg_match('/^\s*--/', $ln)) continue; $kept[] = $ln; }
    $sql = implode("\n", $kept);
    // 2) imparte pe ';' (schema/seed nu au ';' in interiorul instructiunilor) si executa
    $count = 0;
    foreach (array_map('trim', explode(';', $sql)) as $stmt) {
        if ($stmt === '') continue;
        $pdo->exec($stmt);
        $count++;
    }
    return $count;
}

$step = $_GET['step'] ?? '1';
$sentToken = $_POST['token'] ?? ($_GET['token'] ?? '');
$tokenOk = hash_equals((string)$token, (string)$sentToken);

// conexiune DB pentru orice pas
$pdo = null; $dbOk = false; $dbErr = '';
try { $pdo = pdo_connect($config['db']); $dbOk = true; }
catch (Throwable $e) { $dbErr = $e->getMessage(); }

// ---- Actiune: importa schema ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='import' && $tokenOk && $dbOk) {
    try {
        $n = run_sql($pdo, __DIR__.'/database/schema.sql');
        $tables = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        $msgs[] = "Schema importata: $n instructiuni rulate, $tables tabele in baza de date.";
        $has = (int)$pdo->query('SELECT COUNT(*) FROM branches')->fetchColumn();
        if ($has === 0) { run_sql($pdo, __DIR__.'/database/seed.sql'); $msgs[]='Date demo importate.'; }
        else { $msgs[]='Datele exista deja (nu le-am dublat).'; }
    } catch (Throwable $e) { $err[] = 'Eroare import: '.$e->getMessage(); }
}

// ---- Actiune: creeaza admin ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='admin' && $tokenOk && $dbOk) {
    $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $pass=(string)($_POST['password']??'');
    if ($name===''||$email===''||strlen($pass)<6) { $err[]='Completeaza nume, email si parola (min 6 caractere).'; }
    else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st = $pdo->prepare('INSERT INTO users (name,email,role,active,password_hash) VALUES (?,?,?,1,?)
                                 ON DUPLICATE KEY UPDATE name=VALUES(name), password_hash=VALUES(password_hash), role="admin"');
            $st->execute([$name,$email,'admin',$hash]);
            $done = true; $msgs[]='Administrator creat: '.$email;
        } catch (Throwable $e) { $err[]='Eroare creare admin: '.$e->getMessage(); }
    }
}
$tablesExist = false;
if ($dbOk) { try { $pdo->query('SELECT 1 FROM settings LIMIT 1'); $tablesExist=true; } catch (Throwable $e) {} }
?>
<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalare · Sistem Bon de Ordine</title>
<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem;color:#1a1d23;line-height:1.55}
.card{border:1px solid #e6e8ec;border-radius:14px;padding:1.4rem;margin:1rem 0;box-shadow:0 8px 24px rgba(16,24,40,.05)}
input{width:100%;padding:.6rem;border:1px solid #d8dbe0;border-radius:8px;margin:.3rem 0 .8rem;font-size:1rem}
.btn{background:#2563eb;color:#fff;border:0;padding:.7rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:1rem}
.ok{background:#dcfce7;color:#166534;padding:.6rem;border-radius:8px;margin:.3rem 0}
.err{background:#fee2e2;color:#b91c1c;padding:.6rem;border-radius:8px;margin:.3rem 0}
code{background:#0f1115;color:#7CFFB2;padding:.15rem .4rem;border-radius:5px}
h1{font-size:1.5rem}</style></head><body>
<h1>🎫 Instalare — Sistem de Bon de Ordine</h1>
<?php foreach($msgs as $m): ?><div class="ok">✔ <?=htmlspecialchars($m)?></div><?php endforeach; ?>
<?php foreach($err as $m): ?><div class="err">✖ <?=htmlspecialchars($m)?></div><?php endforeach; ?>

<div class="card">
  <strong>1. Conexiune baza de date</strong>
  <?php if($dbOk): ?><div class="ok">✔ Conectat la <code><?=htmlspecialchars($config['db']['name'])?></code></div>
  <?php else: ?><div class="err">✖ Esuat: <?=htmlspecialchars($dbErr)?><br>Editeaza <code>config/config.php</code> (host, nume BD, user, parola).</div><?php endif; ?>
</div>

<?php if(!$tokenOk): ?>
  <div class="card">
    <strong>Token de instalare</strong>
    <p>Introdu valoarea <code>setup_token</code> din <code>config/config.php</code>:</p>
    <form method="get"><input name="token" placeholder="setup_token" value=""><button class="btn">Continua</button></form>
  </div>
<?php else: ?>
  <div class="card">
    <strong>2. Structura bazei de date</strong>
    <?php if($tablesExist): ?><div class="ok">✔ Tabelele exista deja.</div><?php endif; ?>
    <form method="post"><input type="hidden" name="token" value="<?=htmlspecialchars($token)?>"><input type="hidden" name="action" value="import">
      <button class="btn">Importa schema + date demo</button></form>
  </div>

  <div class="card">
    <strong>3. Creeaza primul administrator</strong>
    <?php if($done): ?>
      <div class="ok">✔ Gata! Te poti autentifica la <a href="login">/login</a>.</div>
      <div class="err">⚠ ACUM: sterge fisierul <code>install.php</code> de pe server si schimba <code>setup_token</code>.</div>
    <?php else: ?>
    <form method="post"><input type="hidden" name="token" value="<?=htmlspecialchars($token)?>"><input type="hidden" name="action" value="admin">
      <label>Nume</label><input name="name" required>
      <label>Email (user de login)</label><input type="email" name="email" required>
      <label>Parola (min 6 caractere)</label><input type="password" name="password" required>
      <button class="btn">Creeaza administrator</button>
    </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
</body></html>
