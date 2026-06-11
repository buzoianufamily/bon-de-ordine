<?php
/** TOTP (RFC 6238) in PHP pur — pentru autentificare in doi pasi cu Google Authenticator / Authy etc. */

function base32_encode(string $data): string {
    if ($data === '') return '';
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    return $out;
}
function base32_decode(string $b32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    if ($b32 === '') return '';
    $bits = '';
    foreach (str_split($b32) as $c) $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

/** Secret nou (base32, ~32 caractere). */
function totp_secret(int $bytes = 20): string { return base32_encode(random_bytes($bytes)); }

/** Codul TOTP de 6 cifre pentru un anumit slot de 30s. */
function totp_at(string $secret, int $slice): string {
    $key = base32_decode($secret);
    if ($key === '') return '------';
    $bin  = pack('N*', 0) . pack('N*', $slice);                 // contor 8 octeti big-endian
    $hash = hash_hmac('sha1', $bin, $key, true);
    $off  = ord($hash[19]) & 0xf;
    $code = ((ord($hash[$off]) & 0x7f) << 24) | ((ord($hash[$off + 1]) & 0xff) << 16)
          | ((ord($hash[$off + 2]) & 0xff) << 8) | (ord($hash[$off + 3]) & 0xff);
    return str_pad((string)($code % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Verifica un cod cu toleranta +/- $window sloturi (drift de ceas). */
function totp_verify(string $secret, string $code, int $window = 1): bool {
    $code = preg_replace('/\D/', '', $code);
    if ($secret === '' || strlen($code) !== 6) return false;
    $slice = (int) floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_at($secret, $slice + $i), $code)) return true;
    }
    return false;
}

/** URI otpauth:// pentru codul QR de configurare. */
function totp_uri(string $secret, string $label, string $issuer): string {
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
         . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
}

/** Genereaza coduri de recuperare unice (format XXXX-XXXX). Returneaza [coduri_clar, hashuri]. */
function totp_backup_generate(int $n = 8): array {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // fara caractere ambigue (0/O, 1/I/L)
    $plain = [];
    for ($i = 0; $i < $n; $i++) {
        $c = '';
        for ($j = 0; $j < 8; $j++) $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $plain[] = substr($c, 0, 4) . '-' . substr($c, 4);
    }
    $hashes = array_map(fn($c) => hash('sha256', str_replace('-', '', $c)), $plain);
    return [$plain, $hashes];
}

/**
 * Verifica si CONSUMA un cod de recuperare din lista JSON de hash-uri.
 * Returneaza noul JSON (fara codul folosit) sau null daca nu se potriveste.
 */
function totp_backup_consume(?string $json, string $code): ?string {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    if (strlen($code) !== 8 || !$json) return null;
    $hashes = json_decode($json, true);
    if (!is_array($hashes)) return null;
    $h = hash('sha256', $code);
    foreach ($hashes as $i => $stored) {
        if (is_string($stored) && hash_equals($stored, $h)) {
            unset($hashes[$i]);
            return json_encode(array_values($hashes));
        }
    }
    return null;
}
