<?php
/** Autentificare utilizatori (operatori/admini). */

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $u = one('SELECT id, name, email, role, active, must_change_pw FROM users WHERE id = ?', [$_SESSION['uid']]);
        if (!$u || (int)$u['active'] !== 1) { $u = null; logout(); }
    }
    return $u;
}

/** Verifica doar parola (fara a deschide sesiunea). Returneaza randul userului sau null. */
function verify_credentials(string $email, string $password): ?array {
    $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [trim($email)]);
    if (!$u || !password_verify($password, $u['password_hash'])) return null;
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    return $u;
}

/** Finalizeaza autentificarea (dupa parola + eventual 2FA). */
function complete_login(int $uid): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
}

function attempt_login(string $email, string $password): bool {
    $u = verify_credentials($email, $password);
    if (!$u) return false;
    complete_login((int)$u['id']);
    return true;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        if (want_json()) json_out(['ok' => false, 'error' => 'Neautentificat'], 401);
        redirect('login');
    }
    return $u;
}

/**
 * Trebuie userul sa-si schimbe parola implicita acum? (onboarding sigur)
 * Dezactivat in mediul 'dev' (testele se autentifica cu parola seed), activ in productie.
 */
function must_change_pw_now(?array $u): bool {
    if (!$u || (int)($u['must_change_pw'] ?? 0) !== 1) return false;
    $env = ($GLOBALS['__config']['app']['env'] ?? 'production');
    return $env !== 'dev';
}

function require_role(array $roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        if (want_json()) json_out(['ok' => false, 'error' => 'Acces interzis'], 403);
        http_response_code(403);
        die('Acces interzis.');
    }
    return $u;
}

/**
 * Schimbare rapida a operatorului la terminal printr-un PIN (dispozitive de incredere).
 * Doar conturi de tip 'agent' active — nu permite escaladare la manager/admin.
 */
function pin_switch(string $pin): ?array {
    $pin = preg_replace('/\D/', '', $pin);
    if ($pin === '') return null;
    $u = one("SELECT id, name, role FROM users WHERE pin = ? AND active = 1 AND role = 'agent' LIMIT 1", [$pin]);
    if (!$u) return null;
    complete_login((int)$u['id']);
    return $u;
}

/* ----------------------- Parola: schimbare / resetare ----------------------- */

/** Cerinte minime pentru o parola noua. Returneaza mesaj de eroare sau '' daca e ok. */
function password_policy_error(string $pw): string {
    if (strlen($pw) < 6) return 'Parola trebuie sa aiba cel putin 6 caractere.';
    return '';
}

/** Schimba parola propriului cont dupa verificarea parolei curente. ['ok'=>bool,'error'=>string] */
function change_own_password(int $uid, string $current, string $new, string $confirm): array {
    $row = one('SELECT password_hash FROM users WHERE id=?', [$uid]);
    if (!$row || !password_verify($current, $row['password_hash'])) {
        return ['ok' => false, 'error' => 'Parola curenta este incorecta.'];
    }
    if ($new !== $confirm) return ['ok' => false, 'error' => 'Confirmarea nu coincide cu parola noua.'];
    if (($e = password_policy_error($new)) !== '') return ['ok' => false, 'error' => $e];
    if (password_verify($new, $row['password_hash'])) return ['ok' => false, 'error' => 'Parola noua trebuie sa difere de cea curenta.'];
    q('UPDATE users SET password_hash=?, must_change_pw=0 WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
    return ['ok' => true, 'error' => ''];
}

/** Actualizeaza datele proprii de profil (nume, email, telefon). ['ok'=>bool,'error'=>string] */
function change_own_profile(int $uid, string $name, string $email, string $phone): array {
    $name  = trim($name);
    $email = trim($email);
    $phone = trim($phone);
    if ($name === '') return ['ok' => false, 'error' => 'Numele nu poate fi gol.'];
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Adresa de email nu este valida.'];
    // emailul e identificator de autentificare -> trebuie sa ramana unic
    $taken = one('SELECT id FROM users WHERE email=? AND id<>?', [$email, $uid]);
    if ($taken) return ['ok' => false, 'error' => 'Aceasta adresa de email este deja folosita de alt cont.'];
    if (mb_strlen($phone) > 32) $phone = mb_substr($phone, 0, 32);
    q('UPDATE users SET name=?, email=?, phone=? WHERE id=?', [$name, $email, ($phone === '' ? null : $phone), $uid]);
    return ['ok' => true, 'error' => ''];
}

/**
 * Creeaza un token de resetare pentru emailul dat si trimite linkul pe email.
 * Best-effort, fara a divulga daca emailul exista. Returneaza true daca un email a fost trimis.
 */
function password_reset_request(string $email): bool {
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $u = one('SELECT id, name, email FROM users WHERE email=? AND active=1', [$email]);
    if (!$u) return false;
    // invalideaza tokenele vechi inca active ale userului
    q("UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL", [$u['id']]);
    $token = bin2hex(random_bytes(24));
    q('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))',
      [$u['id'], hash('sha256', $token)]);
    $link = url('login/reset') . '?token=' . $token;
    $brand = setting('brand_name', 'Bon de ordine');
    $body  = '<p>Salut, ' . e($u['name']) . '.</p>'
           . '<p>Am primit o cerere de resetare a parolei pentru contul tau din <strong>' . e($brand) . '</strong>. '
           . 'Apasa butonul de mai jos pentru a seta o parola noua. Linkul expira in <strong>60 de minute</strong>.</p>'
           . '<p style="color:#6b7280;font-size:13px">Daca nu tu ai cerut resetarea, ignora acest email — parola ramane neschimbata.</p>';
    $html = mail_template('Resetare parola', $body, 'Seteaza parola noua', $link);
    return send_mail($u['email'], 'Resetare parola · ' . $brand, $html);
}

/** Valideaza un token de resetare (nefolosit, neexpirat). Returneaza randul din password_resets + email, sau null. */
function password_reset_lookup(string $token): ?array {
    if ($token === '' || !ctype_xdigit($token)) return null;
    return one("SELECT pr.id, pr.user_id, u.email, u.name
                FROM password_resets pr JOIN users u ON u.id=pr.user_id AND u.active=1
                WHERE pr.token_hash=? AND pr.used_at IS NULL AND pr.expires_at > NOW()
                LIMIT 1", [hash('sha256', $token)]);
}

/** Aplica parola noua pe baza unui token valid si marcheaza tokenul folosit. ['ok'=>bool,'error'=>string,'uid'=>int] */
function password_reset_apply(string $token, string $new, string $confirm): array {
    $row = password_reset_lookup($token);
    if (!$row) return ['ok' => false, 'error' => 'Link invalid sau expirat. Cere o resetare noua.'];
    if ($new !== $confirm) return ['ok' => false, 'error' => 'Confirmarea nu coincide cu parola noua.'];
    if (($e = password_policy_error($new)) !== '') return ['ok' => false, 'error' => $e];
    q('UPDATE users SET password_hash=?, must_change_pw=0 WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $row['user_id']]);
    q('UPDATE password_resets SET used_at=NOW() WHERE id=?', [$row['id']]);
    return ['ok' => true, 'error' => '', 'uid' => (int)$row['user_id']];
}

/* ----------------------- Permisiuni (roluri) ----------------------- */
/** Ariile de administrare ce pot fi permise/blocate per rol. */
function perm_areas(): array {
    return [
        'statistics'=>'Statistici', 'branches'=>'Filiale', 'services'=>'Servicii', 'groups'=>'Grupuri', 'counters'=>'Ghisee',
        'devices'=>'Dispozitive', 'media'=>'Multimedia', 'forms'=>'Formulare', 'tickets'=>'Bilete',
        'appointments'=>'Programari', 'feedback'=>'Feedback', 'users'=>'Utilizatori', 'settings'=>'Setari',
    ];
}
/** Configuratia salvata a permisiunilor (per rol). */
function role_perms(): array {
    static $c = null;
    if ($c === null) { $raw = setting('role_perms', ''); $c = $raw ? (json_decode($raw, true) ?: []) : []; }
    return $c;
}
/** Are utilizatorul curent acces la aria data? */
function can(string $area): bool {
    $u = current_user();
    if (!$u) return false;
    if ($u['role'] === 'admin') return true;          // adminul are tot
    if ($area === 'roles') return false;              // doar admin configureaza rolurile
    if ($u['role'] === 'manager') {
        $cfg = role_perms();
        if (isset($cfg['manager'][$area])) return (bool)$cfg['manager'][$area];
        return !in_array($area, ['users','settings'], true); // implicit pt manager
    }
    return false;                                     // agent (nu intra in admin)
}
