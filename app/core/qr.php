<?php
/**
 * Generator QR cod, pur PHP, fara dependinte externe (nici imagine, nici servicii web).
 * Mod byte (UTF-8), nivel de corectie L, versiuni 1..10 (pana la 271 octeti) — suficient
 * pentru otpauth:// (2FA) si URL-uri de dispozitive. Iesire SVG (vectorial, fara GD).
 *
 * Inlocuieste fostul apel catre api.qrserver.com (care expunea secretul 2FA si cheile
 * de dispozitiv unui serviciu extern si cerea internet pe chioscuri/TV-uri).
 */

final class QR
{
    /** @var int[] */ private static array $expT = [];
    /** @var int[] */ private static array $logT = [];

    // Nivel L: [ec_codewords_per_block, [data_codewords_per_block...]] pentru versiunile 1..10
    private const EC_L = [
        1  => [7,  [19]],
        2  => [10, [34]],
        3  => [15, [55]],
        4  => [20, [80]],
        5  => [26, [108]],
        6  => [18, [68, 68]],
        7  => [20, [78, 78]],
        8  => [24, [97, 97]],
        9  => [30, [116, 116]],
        10 => [18, [68, 68, 69, 69]],
    ];
    // Pozitiile centrelor pentru tiparele de aliniere, per versiune
    private const ALIGN = [
        1 => [], 2 => [6,18], 3 => [6,22], 4 => [6,26], 5 => [6,30],
        6 => [6,34], 7 => [6,22,38], 8 => [6,24,42], 9 => [6,26,46], 10 => [6,28,50],
    ];

    /** Returneaza SVG-ul (string) pentru datele date, sau '' daca nu incap in v1..10-L. */
    public static function svg(string $data, int $size = 200, int $margin = 4): string
    {
        $m = self::matrix($data);
        if ($m === null) return '';
        $n = count($m);
        $total = $n + 2 * $margin;
        $px = $size / $total;
        $rects = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($m[$r][$c]) {
                    $x = round(($c + $margin) * $px, 2);
                    $y = round(($r + $margin) * $px, 2);
                    $w = round($px, 2);
                    $rects .= "<rect x=\"$x\" y=\"$y\" width=\"$w\" height=\"$w\"/>";
                }
            }
        }
        return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"$size\" height=\"$size\" "
             . "viewBox=\"0 0 $size $size\" shape-rendering=\"crispEdges\" role=\"img\">"
             . "<rect width=\"$size\" height=\"$size\" fill=\"#fff\"/><g fill=\"#000\">$rects</g></svg>";
    }

    /** Returneaza matricea de module (0/1) sau null daca datele nu incap. */
    public static function matrix(string $data): ?array
    {
        self::gfInit();
        $bytes = array_values(unpack('C*', $data) ?: []);
        $len = count($bytes);

        // alege cea mai mica versiune in care incap datele (nivel L)
        $version = 0;
        for ($v = 1; $v <= 10; $v++) {
            $dataCw = array_sum(self::EC_L[$v][1]);
            $countBits = $v < 10 ? 8 : 16;
            $needBits = 4 + $countBits + 8 * $len;
            if ((int) ceil($needBits / 8) <= $dataCw) { $version = $v; break; }
        }
        if ($version === 0) return null;

        $bits = self::encodeBits($bytes, $version);
        $allCw = self::interleave($bits, $version);
        return self::buildBest($allCw, $version);
    }

    /* ---------------- GF(256) ---------------- */
    private static function gfInit(): void
    {
        if (self::$expT) return;
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$expT[$i] = $x;
            self::$logT[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11d;
        }
        for ($i = 255; $i < 512; $i++) self::$expT[$i] = self::$expT[$i - 255];
    }
    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) return 0;
        return self::$expT[self::$logT[$a] + self::$logT[$b]];
    }
    /** Polinomul generator RS de grad $deg. */
    private static function rsGen(int $deg): array
    {
        $poly = [1];
        for ($i = 0; $i < $deg; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $coef) {
                $next[$j]     ^= self::gfMul($coef, self::$expT[$i]);
                $next[$j + 1] ^= $coef;
            }
            $poly = $next;
        }
        return array_reverse($poly); // grad descrescator (coeficient principal = 1) pentru rsEnc
    }
    /** Codewords de corectie pentru un bloc de date. */
    private static function rsEnc(array $data, int $ecLen): array
    {
        $gen = self::rsGen($ecLen);
        $res = array_merge($data, array_fill(0, $ecLen, 0));
        for ($i = 0; $i < count($data); $i++) {
            $factor = $res[$i];
            if ($factor === 0) continue;
            for ($j = 0; $j < count($gen); $j++) {
                $res[$i + $j] ^= self::gfMul($gen[$j], $factor);
            }
        }
        return array_slice($res, count($data));
    }

    /* ---------------- bitstream date ---------------- */
    private static function encodeBits(array $bytes, int $version): array
    {
        $len = count($bytes);
        $countBits = $version < 10 ? 8 : 16;
        $bits = [];
        $push = function (int $val, int $n) use (&$bits) {
            for ($i = $n - 1; $i >= 0; $i--) $bits[] = ($val >> $i) & 1;
        };
        $push(0b0100, 4);            // mod byte
        $push($len, $countBits);     // numar de octeti
        foreach ($bytes as $b) $push($b, 8);

        $dataCw = array_sum(self::EC_L[$version][1]);
        $capacity = $dataCw * 8;
        // terminator (max 4 biti)
        for ($i = 0; $i < 4 && count($bits) < $capacity; $i++) $bits[] = 0;
        // aliniere la octet
        while (count($bits) % 8 !== 0) $bits[] = 0;
        // padding 0xEC 0x11
        $pad = [0xEC, 0x11]; $pi = 0;
        while (count($bits) < $capacity) { $push($pad[$pi % 2], 8); $pi++; }
        return $bits;
    }

    /** Bitstream -> codewords de date pe blocuri -> intercalare date+EC (cu bitii ramasi). */
    private static function interleave(array $bits, int $version): array
    {
        [$ecLen, $blockSizes] = self::EC_L[$version];
        // bits -> octeti (codewords de date in flux)
        $cw = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $b = 0;
            for ($j = 0; $j < 8; $j++) $b = ($b << 1) | ($bits[$i + $j] ?? 0);
            $cw[] = $b;
        }
        // imparte in blocuri + calculeaza EC pe fiecare bloc
        $dataBlocks = []; $ecBlocks = []; $pos = 0;
        foreach ($blockSizes as $bs) {
            $blk = array_slice($cw, $pos, $bs); $pos += $bs;
            $dataBlocks[] = $blk;
            $ecBlocks[] = self::rsEnc($blk, $ecLen);
        }
        // intercalare codewords de date
        $out = [];
        $maxData = max($blockSizes);
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($dataBlocks as $blk) if (isset($blk[$i])) $out[] = $blk[$i];
        }
        // intercalare codewords EC
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $blk) $out[] = $blk[$i];
        }
        return $out; // sir de codewords (octeti), citit MSB-first la plasare
    }

    /* ---------------- matrice ---------------- */
    private static function buildBest(array $dataBits, int $version): array
    {
        $size = 17 + 4 * $version;
        $bestScore = PHP_INT_MAX; $bestMatrix = null;
        for ($mask = 0; $mask < 8; $mask++) {
            [$m, $fn] = self::baseMatrix($version, $size);
            self::placeData($m, $fn, $dataBits, $mask, $size);
            self::placeFormat($m, $mask, $size);
            $score = self::penalty($m, $size);
            if ($score < $bestScore) { $bestScore = $score; $bestMatrix = $m; }
        }
        return $bestMatrix;
    }

    /** Construieste matricea cu tiparele functionale; intoarce [matrice, harta-functii]. */
    private static function baseMatrix(int $version, int $size): array
    {
        $m  = array_fill(0, $size, array_fill(0, $size, 0));
        $fn = array_fill(0, $size, array_fill(0, $size, false)); // true = modul functional (rezervat)

        $finder = function (int $r, int $c) use (&$m, &$fn, $size) {
            for ($dr = -1; $dr <= 7; $dr++) {
                for ($dc = -1; $dc <= 7; $dc++) {
                    $rr = $r + $dr; $cc = $c + $dc;
                    if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) continue;
                    $fn[$rr][$cc] = true;
                    $inRing = ($dr >= 0 && $dr <= 6 && ($dc === 0 || $dc === 6))
                           || ($dc >= 0 && $dc <= 6 && ($dr === 0 || $dr === 6));
                    $inCore = ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4);
                    $m[$rr][$cc] = ($inRing || $inCore) ? 1 : 0;
                }
            }
        };
        $finder(0, 0); $finder(0, $size - 7); $finder($size - 7, 0);

        // timing
        for ($i = 8; $i < $size - 8; $i++) {
            $v = ($i % 2 === 0) ? 1 : 0;
            if (!$fn[6][$i]) { $m[6][$i] = $v; $fn[6][$i] = true; }
            if (!$fn[$i][6]) { $m[$i][6] = $v; $fn[$i][6] = true; }
        }

        // aliniere
        $centers = self::ALIGN[$version];
        foreach ($centers as $r) {
            foreach ($centers as $c) {
                // sare peste cele care se suprapun cu finder-ele
                if (($r <= 8 && $c <= 8) || ($r <= 8 && $c >= $size - 9) || ($r >= $size - 9 && $c <= 8)) continue;
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $rr = $r + $dr; $cc = $c + $dc;
                        $fn[$rr][$cc] = true;
                        $ring = (abs($dr) === 2 || abs($dc) === 2);
                        $m[$rr][$cc] = ($ring || ($dr === 0 && $dc === 0)) ? 1 : 0;
                    }
                }
            }
        }

        // modul intunecat fix
        $m[$size - 8][8] = 1; $fn[$size - 8][8] = true;

        // rezerva zonele pentru format info
        for ($i = 0; $i <= 8; $i++) {
            if (!$fn[8][$i]) $fn[8][$i] = true;
            if (!$fn[$i][8]) $fn[$i][8] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            $fn[8][$size - 1 - $i] = true;
            $fn[$size - 1 - $i][8] = true;
        }

        // informatii de versiune (v >= 7)
        if ($version >= 7) {
            $vbits = self::versionBits($version);
            for ($i = 0; $i < 18; $i++) {
                $bit = ($vbits >> $i) & 1;
                $r = intdiv($i, 3); $c = $i % 3;
                $m[$size - 11 + $c][$r] = $bit; $fn[$size - 11 + $c][$r] = true;
                $m[$r][$size - 11 + $c] = $bit; $fn[$r][$size - 11 + $c] = true;
            }
        }

        return [$m, $fn];
    }

    /** Plaseaza codewords-urile in zig-zag, aplicand masca (port 1:1 dupa qrcode.map_data). */
    private static function placeData(array &$m, array $fn, array $data, int $mask, int $size): void
    {
        $inc = -1; $row = $size - 1; $bitIndex = 7; $byteIndex = 0; $len = count($data);
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col <= 6) $col -= 1;            // sare coloana de timing (6)
            $colRange = [$col, $col - 1];
            while (true) {
                foreach ($colRange as $c) {
                    if (!$fn[$row][$c]) {
                        $bit = ($byteIndex < $len) ? (($data[$byteIndex] >> $bitIndex) & 1) : 0;
                        if (self::maskOn($mask, $row, $c)) $bit ^= 1;
                        $m[$row][$c] = $bit;
                        $bitIndex--;
                        if ($bitIndex === -1) { $byteIndex++; $bitIndex = 7; }
                    }
                }
                $row += $inc;
                if ($row < 0 || $row >= $size) { $row -= $inc; $inc = -$inc; break; }
            }
        }
    }

    private static function maskOn(int $mask, int $r, int $c): bool
    {
        switch ($mask) {
            case 0: return ($r + $c) % 2 === 0;
            case 1: return $r % 2 === 0;
            case 2: return $c % 3 === 0;
            case 3: return ($r + $c) % 3 === 0;
            case 4: return (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0;
            case 5: return (($r * $c) % 2) + (($r * $c) % 3) === 0;
            case 6: return ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0;
            case 7: return ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0;
        }
        return false;
    }

    /** Plaseaza informatiile de format (port 1:1 dupa qrcode.setup_type_info). */
    private static function placeFormat(array &$m, int $mask, int $size): void
    {
        $bits = self::formatBits($mask);
        // vertical (coloana 8)
        for ($i = 0; $i < 15; $i++) {
            $mod = ($bits >> $i) & 1;
            if ($i < 6)      $m[$i][8] = $mod;
            elseif ($i < 8)  $m[$i + 1][8] = $mod;
            else             $m[$size - 15 + $i][8] = $mod;
        }
        // orizontal (randul 8)
        for ($i = 0; $i < 15; $i++) {
            $mod = ($bits >> $i) & 1;
            if ($i < 8)      $m[8][$size - $i - 1] = $mod;
            elseif ($i < 9)  $m[8][15 - $i - 1 + 1] = $mod;
            else             $m[8][15 - $i - 1] = $mod;
        }
        $m[$size - 8][8] = 1; // modul intunecat fix
    }

    private static function formatBits(int $mask): int
    {
        $ecBits = 0b01;                       // nivel L
        $data = ($ecBits << 3) | $mask;       // 5 biti
        $rem = $data << 10;
        $g = 0b10100110111;
        for ($i = 14; $i >= 10; $i--) {
            if (($rem >> $i) & 1) $rem ^= $g << ($i - 10);
        }
        return (($data << 10) | $rem) ^ 0b101010000010010;
    }

    private static function versionBits(int $version): int
    {
        $rem = $version << 12;
        $g = 0b1111100100101;
        for ($i = 17; $i >= 12; $i--) {
            if (($rem >> $i) & 1) $rem ^= $g << ($i - 12);
        }
        return ($version << 12) | $rem;
    }

    /* ---------------- penalizare masca (4 reguli ISO) ---------------- */
    private static function penalty(array $m, int $size): int
    {
        $score = 0;
        // R1: serii de 5+ module identice pe rand/coloana
        for ($r = 0; $r < $size; $r++) {
            for ($axis = 0; $axis < 2; $axis++) {
                $run = 1; $prev = -1;
                for ($c = 0; $c < $size; $c++) {
                    $v = $axis === 0 ? $m[$r][$c] : $m[$c][$r];
                    if ($v === $prev) { $run++; if ($run === 5) $score += 3; elseif ($run > 5) $score++; }
                    else { $run = 1; $prev = $v; }
                }
            }
        }
        // R2: blocuri 2x2 de aceeasi culoare
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($v === $m[$r][$c+1] && $v === $m[$r+1][$c] && $v === $m[$r+1][$c+1]) $score += 3;
            }
        }
        // R3: tipar 1011101 0000 sau 0000 1011101
        $pat1 = [1,0,1,1,1,0,1,0,0,0,0];
        $pat2 = [0,0,0,0,1,0,1,1,1,0,1];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c <= $size - 11; $c++) {
                $okH1 = true; $okH2 = true; $okV1 = true; $okV2 = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($m[$r][$c+$k] !== $pat1[$k]) $okH1 = false;
                    if ($m[$r][$c+$k] !== $pat2[$k]) $okH2 = false;
                    if ($m[$c+$k][$r] !== $pat1[$k]) $okV1 = false;
                    if ($m[$c+$k][$r] !== $pat2[$k]) $okV2 = false;
                }
                if ($okH1) $score += 40; if ($okH2) $score += 40;
                if ($okV1) $score += 40; if ($okV2) $score += 40;
            }
        }
        // R4: proportia de module intunecate
        $dark = 0;
        for ($r = 0; $r < $size; $r++) $dark += array_sum($m[$r]);
        $pct = $dark * 100 / ($size * $size);
        $k = (int) (floor(abs($pct - 50) / 5));
        $score += $k * 10;
        return $score;
    }
}
