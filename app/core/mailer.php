<?php
/**
 * Trimitere email — fara dependente externe.
 * SMTP simplu (SSL/STARTTLS + AUTH LOGIN) cand e configurat un host;
 * altfel fallback pe mail() din PHP (functioneaza nativ pe cPanel).
 * Se activeaza din Administrare → Setari → Email.
 */

function mail_enabled(): bool { return setting('mail_enabled', '0') === '1'; }

/** Trimite un email HTML. Best-effort: intoarce true/false, nu arunca exceptii. */
function send_mail(string $to, string $subject, string $html): bool {
    try {
        if (!mail_enabled()) return false;
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
        $fromEmail = trim((string) setting('mail_from', '')) ?: ('no-reply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromName  = trim((string) setting('mail_from_name', '')) ?: (string) setting('brand_name', 'Bon de ordine');
        $host = trim((string) setting('smtp_host', ''));
        if ($host !== '') {
            return smtp_send($host, (int) setting('smtp_port', '587'), (string) setting('smtp_secure', 'tls'),
                (string) setting('smtp_user', ''), (string) setting('smtp_pass', ''),
                $fromEmail, $fromName, $to, $subject, $html);
        }
        $headers = "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n"
                 . "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
    } catch (Throwable $e) { return false; }
}

/** Client SMTP minimal: EHLO → (STARTTLS) → AUTH LOGIN → MAIL/RCPT/DATA → QUIT. */
function smtp_send(string $host, int $port, string $secure, string $user, string $pass,
                   string $fromEmail, string $fromName, string $to, string $subject, string $html): bool {
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @stream_socket_client($remote . ':' . ($port ?: ($secure === 'ssl' ? 465 : 587)),
        $errno, $errstr, 8, STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]));
    if (!$fp) return false;
    stream_set_timeout($fp, 8);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) { $data .= $line; if (strlen($line) < 4 || $line[3] !== '-') break; }
        return $data;
    };
    $cmd = function (string $c, array $okCodes) use ($fp, $read): bool {
        fwrite($fp, $c . "\r\n");
        $resp = $read();
        return in_array((int) substr($resp, 0, 3), $okCodes, true);
    };

    try {
        if ((int) substr($read(), 0, 3) !== 220) return false;
        $ehloHost = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
        if (!$cmd('EHLO ' . $ehloHost, [250])) return false;
        if ($secure === 'tls') {
            if (!$cmd('STARTTLS', [220])) return false;
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return false;
            if (!$cmd('EHLO ' . $ehloHost, [250])) return false;
        }
        if ($user !== '') {
            if (!$cmd('AUTH LOGIN', [334])) return false;
            if (!$cmd(base64_encode($user), [334])) return false;
            if (!$cmd(base64_encode($pass), [235])) return false;
        }
        if (!$cmd('MAIL FROM:<' . $fromEmail . '>', [250])) return false;
        if (!$cmd('RCPT TO:<' . $to . '>', [250, 251])) return false;
        if (!$cmd('DATA', [354])) return false;

        $headers = 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <{$fromEmail}>\r\n"
                 . "To: <{$to}>\r\n"
                 . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
                 . 'Date: ' . date('r') . "\r\n"
                 . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . ">\r\n"
                 . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: base64\r\n";
        $body = chunk_split(base64_encode($html));
        if (!$cmd($headers . "\r\n" . $body . "\r\n.", [250])) return false;
        $cmd('QUIT', [221]);
        return true;
    } finally {
        @fclose($fp);
    }
}

/** Sablon HTML simplu, pe brandul instantei (accent + nume). */
function mail_template(string $title, string $bodyHtml, string $btnText = '', string $btnUrl = ''): string {
    $brand  = e(setting('brand_name', 'Bon de ordine'));
    $accent = e(setting('accent_color', '#2563eb'));
    $btn = '';
    if ($btnText !== '' && $btnUrl !== '') {
        $btn = '<p style="margin:22px 0"><a href="' . e($btnUrl) . '" style="background:' . $accent . ';color:#fff;'
             . 'padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block">'
             . e($btnText) . '</a></p>';
    }
    return '<!doctype html><html><body style="margin:0;background:#f1f4f8;font-family:Arial,Helvetica,sans-serif;color:#1a1d23">'
         . '<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e6e8ec">'
         . '<div style="background:' . $accent . ';color:#fff;padding:16px 24px;font-size:18px;font-weight:bold">' . $brand . '</div>'
         . '<div style="padding:24px"><h2 style="margin:0 0 12px;font-size:20px">' . e($title) . '</h2>'
         . '<div style="font-size:15px;line-height:1.6">' . $bodyHtml . '</div>' . $btn . '</div>'
         . '<div style="padding:14px 24px;border-top:1px solid #eef1f5;color:#6b7280;font-size:12px">'
         . 'Mesaj trimis automat de ' . $brand . '. Nu raspunde la acest email.</div>'
         . '</div></body></html>';
}
