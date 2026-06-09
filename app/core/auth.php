<?php
/** Autentificare utilizatori (operatori/admini). */

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $u = one('SELECT id, name, email, role, active FROM users WHERE id = ?', [$_SESSION['uid']]);
        if (!$u || (int)$u['active'] !== 1) { $u = null; logout(); }
    }
    return $u;
}

function attempt_login(string $email, string $password): bool {
    $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [trim($email)]);
    if (!$u) return false;
    if (!password_verify($password, $u['password_hash'])) return false;
    // re-hash daca s-a schimbat algoritmul implicit
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?',
          [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
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

function require_role(array $roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        if (want_json()) json_out(['ok' => false, 'error' => 'Acces interzis'], 403);
        http_response_code(403);
        die('Acces interzis.');
    }
    return $u;
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
