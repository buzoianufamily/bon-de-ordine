<?php
/**
 * Printare bon termic prin ESC/POS.
 * Suporta imprimante de retea (ex: Bixolon Ethernet/WiFi) pe portul RAW 9100.
 * Genereaza si secventa de octeti (base64) pe care o poate folosi aplicatia
 * Android (prin bridge JS) pentru imprimante USB/Bluetooth.
 */
class Escpos
{
    private string $buf = '';

    const ESC = "\x1B";
    const GS  = "\x1D";

    public function init(): self    { $this->buf .= self::ESC . '@'; return $this; }
    public function text(string $s): self { $this->buf .= self::translit($s); return $this; }
    public function line(string $s = ''): self { $this->buf .= self::translit($s) . "\n"; return $this; }
    public function feed(int $n = 1): self { $this->buf .= str_repeat("\n", $n); return $this; }

    /** align: 0=stanga 1=centru 2=dreapta */
    public function align(int $n): self { $this->buf .= self::ESC . 'a' . chr($n); return $this; }
    public function bold(bool $on): self { $this->buf .= self::ESC . 'E' . chr($on ? 1 : 0); return $this; }
    /** w,h: 1..8 multiplicator */
    public function size(int $w = 1, int $h = 1): self {
        $w = max(1, min(8, $w)); $h = max(1, min(8, $h));
        $this->buf .= self::GS . '!' . chr((($w - 1) << 4) | ($h - 1));
        return $this;
    }
    public function cut(): self { $this->buf .= self::GS . 'V' . "\x42" . "\x00"; return $this; }

    /** Cod QR (model 2). */
    public function qr(string $data, int $module = 6, int $ec = 49): self {
        $this->buf .= self::GS . "(k\x04\x00\x31\x41\x32\x00";          // model 2
        $this->buf .= self::GS . "(k\x03\x00\x31\x43" . chr($module);   // marime modul
        $this->buf .= self::GS . "(k\x03\x00\x31\x45" . chr($ec);       // corectie erori
        $len = strlen($data) + 3;
        $this->buf .= self::GS . "(k" . chr($len & 0xff) . chr(($len >> 8) & 0xff) . "\x31\x50\x30" . $data; // store
        $this->buf .= self::GS . "(k\x03\x00\x31\x51\x30";              // print
        return $this;
    }

    public function raw(): string { return $this->buf; }
    public function base64(): string { return base64_encode($this->buf); }

    /** Transliterare diacritice -> ASCII (sigur pe orice imprimanta). */
    public static function translit(string $s): string {
        $map = ['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t',
                'Ă'=>'A','Â'=>'A','Î'=>'I','Ș'=>'S','Ş'=>'S','Ț'=>'T','Ţ'=>'T'];
        return strtr($s, $map);
    }
}

/** Construieste bonul pentru un bilet (octeti ESC/POS). */
function build_ticket_escpos(array $ticket, array $service, array $branch): string
{
    $brand   = setting('brand_name', $branch['name']);
    $header  = setting('ticket_header', '');
    $footer  = setting('ticket_footer', 'Va multumim!');
    $token   = $ticket['public_token'] ?? '';
    $qrUrl   = url('t/' . $token);
    $waiting = ticket_position($ticket);
    // optiuni configurabile pentru continutul bonului
    $numSize  = max(2, min(6, (int) setting('ticket_num_size', '4')));
    $showPos  = setting('ticket_show_position', '1') === '1';
    $showDt   = setting('ticket_show_datetime', '1') === '1';
    $showQr   = setting('ticket_show_qr', '1') === '1';

    $p = new Escpos();
    $p->init()->align(1);
    $p->bold(true)->size(1,1)->line($brand)->bold(false);
    $p->size(1,1)->line($branch['name'] ?? '');
    if ($header !== '') $p->line($header);
    $p->feed(1)->line('--------------------------------');
    $p->line($service['name']);
    if (!empty($ticket['priority'])) $p->bold(true)->line('* PRIORITAR *')->bold(false);
    $p->feed(1)->size($numSize,$numSize)->bold(true)->line($ticket['label'])->bold(false)->size(1,1);
    $p->feed(1);
    if ($showPos) $p->line('Inainte: ' . $waiting . ' persoane');
    if ($showDt)  $p->line(date('d.m.Y H:i', strtotime($ticket['issued_at'])));
    if (setting('virtual_enabled', '1') === '1' && $showQr && $token) {
        $p->feed(1)->line('Urmariti pe telefon:')->qr($qrUrl, 5)->feed(1);
    }
    $p->line('--------------------------------');
    $p->size(1,1)->line($footer);
    $p->feed(3)->cut();
    return $p->raw();
}

/** Trimite octeti ESC/POS la o imprimanta de retea (RAW 9100). */
function print_network(string $ip, int $port, string $bytes, int $timeout = 4): array
{
    $errno = 0; $errstr = '';
    $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if (!$fp) return ['ok' => false, 'error' => "Conectare imprimanta esuata ($errno): $errstr"];
    stream_set_timeout($fp, $timeout);
    $written = fwrite($fp, $bytes);
    fflush($fp);
    fclose($fp);
    return ['ok' => $written !== false, 'bytes' => (int)$written];
}
