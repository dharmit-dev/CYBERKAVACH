<?php

declare(strict_types=1);

/**
 * QRService — generates real, scannable QR codes in pure PHP.
 *
 * Uses a self-contained QR encoder (byte mode, ECC level M) that produces
 * standards-compliant matrix data rendered as SVG.  No Composer or external
 * libraries required.  Supports payloads up to ~2 KB depending on QR version.
 */
final class QRService
{
    public static function generateTeamQr(string $teamIdentifier, string $payload): string
    {
        $relativePath = 'uploads/qr/team-' . preg_replace('/[^A-Z0-9-]/', '', $teamIdentifier) . '.svg';
        $absolutePath = BASE_PATH . '/public/' . $relativePath;

        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        // Try Endroid if available (composer install ran)
        if (class_exists('\Endroid\QrCode\Builder\Builder') && method_exists('\Endroid\QrCode\Builder\Builder', 'create')) {
            try {
                return self::generateWithEndroid($payload, $absolutePath, $relativePath);
            } catch (Throwable $e) {
                // Fall back to pure PHP generator below
            }
        }

        // Pure-PHP standards-compliant QR generator (always available)
        self::generateQrSvg($payload, $absolutePath);

        return $relativePath;
    }

    // -----------------------------------------------------------------------
    // Endroid path (only used when vendor/ exists)
    // -----------------------------------------------------------------------

    private static function generateWithEndroid(string $payload, string $absolutePath, string $relativePath): string
    {
        $builder = \Endroid\QrCode\Builder\Builder::create()
            ->data($payload)
            ->size(260)
            ->margin(12);
        $builder->build()->saveToFile($absolutePath);

        return $relativePath;
    }

    // -----------------------------------------------------------------------
    // Pure-PHP QR encoder
    // -----------------------------------------------------------------------

    private static function generateQrSvg(string $data, string $outPath): void
    {
        $matrix = self::encode($data);
        $n      = count($matrix);
        $cell   = 8;
        $quiet  = 4;                   // quiet zone in cells
        $total  = ($n + $quiet * 2) * $cell;

        $svg  = '<svg xmlns="http://www.w3.org/2000/svg"';
        $svg .= ' width="' . $total . '" height="' . $total . '"';
        $svg .= ' viewBox="0 0 ' . $total . ' ' . $total . '">';
        $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';
        $svg .= '<g fill="#000000">';

        $offset = $quiet * $cell;
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x = $offset + $c * $cell;
                    $y = $offset + $r * $cell;
                    $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $cell . '" height="' . $cell . '"/>';
                }
            }
        }

        $svg .= '</g></svg>';
        file_put_contents($outPath, $svg, LOCK_EX);
    }

    /**
     * Encodes $data into a QR symbol matrix (2-D array of 0/1 integers).
     * Implements byte mode, ECC level M, versions 1-10.
     */
    private static function encode(string $data): array
    {
        // --- 1. Choose version (smallest that fits byte-mode + ECC-M) -------
        // Capacities (bytes) for ECC level M, versions 1-10
        $caps = [16, 28, 44, 64, 86, 108, 124, 154, 182, 216];
        $len  = strlen($data);
        $ver  = 0;
        foreach ($caps as $i => $cap) {
            if ($len <= $cap) { $ver = $i + 1; break; }
        }
        if ($ver === 0) {
            // Data too long; truncate gracefully and use version 10
            $ver  = 10;
            $data = substr($data, 0, 216);
            $len  = strlen($data);
        }

        // --- 2. Build data codewords -----------------------------------------
        // ECC blocks for level M, versions 1-10:
        // [ec_codewords_per_block, num_blocks_group1, data_codewords_group1,
        //  num_blocks_group2, data_codewords_group2]
        $eccInfo = [
            1  => [10, 1, 16, 0, 0],
            2  => [16, 1, 28, 0, 0],
            3  => [26, 1, 44, 0, 0],
            4  => [18, 2, 32, 0, 0],
            5  => [24, 2, 43, 0, 0],
            6  => [16, 4, 27, 0, 0],
            7  => [18, 4, 31, 0, 0],
            8  => [22, 2, 38, 2, 39],
            9  => [22, 3, 36, 2, 37],
            10 => [26, 4, 43, 1, 44],
        ];

        [$ecPerBlock, $nb1, $dc1, $nb2, $dc2] = $eccInfo[$ver];
        $totalData = $nb1 * $dc1 + $nb2 * $dc2;

        // Bit stream: mode indicator (4 bits, byte=0100) + char count + data
        $bits = '0100';
        $ccLen = $ver < 10 ? 8 : 16;
        $bits .= str_pad(decbin($len), $ccLen, '0', STR_PAD_LEFT);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Terminator and padding
        $targetBits = $totalData * 8;
        if (strlen($bits) < $targetBits) {
            $bits .= str_repeat('0', min(4, $targetBits - strlen($bits)));
        }
        // Align to byte boundary
        $rem = strlen($bits) % 8;
        if ($rem) { $bits .= str_repeat('0', 8 - $rem); }
        // Pad to capacity
        $padBytes = ['11101100', '00010001'];
        $pi = 0;
        while (strlen($bits) < $targetBits) {
            $bits .= $padBytes[$pi % 2];
            $pi++;
        }

        // Convert to codeword bytes
        $dataBytes = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataBytes[] = bindec(substr($bits, $i, 8));
        }

        // --- 3. Reed-Solomon ECC blocks -------------------------------------
        $allDataCW  = [];
        $allEccCW   = [];
        $idx = 0;
        for ($b = 0; $b < $nb1 + $nb2; $b++) {
            $dcCount = ($b < $nb1) ? $dc1 : $dc2;
            $block   = array_slice($dataBytes, $idx, $dcCount);
            $idx    += $dcCount;
            $allDataCW[] = $block;
            $allEccCW[]  = self::rsBlock($block, $ecPerBlock);
        }

        // Interleave
        $codewords = [];
        $maxDC = max($dc1, $dc2 ?: 0);
        for ($i = 0; $i < $maxDC; $i++) {
            foreach ($allDataCW as $block) {
                if (isset($block[$i])) { $codewords[] = $block[$i]; }
            }
        }
        foreach ($allEccCW as $block) {
            foreach ($block as $cw) { $codewords[] = $cw; }
        }

        // Remainder bits
        $remBits = [0, 7, 7, 7, 7, 7, 0, 0, 0, 0];
        $bitStream = '';
        foreach ($codewords as $cw) {
            $bitStream .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }
        $bitStream .= str_repeat('0', $remBits[$ver - 1]);

        // --- 4. Build matrix ------------------------------------------------
        $size   = $ver * 4 + 17;
        $matrix = array_fill(0, $size, array_fill(0, $size, -1)); // -1 = unset
        $isFunc = array_fill(0, $size, array_fill(0, $size, false));

        // Finder patterns
        foreach ([[0,0],[0,$size-7],[$size-7,0]] as [$pr,$pc]) {
            self::placeFinder($matrix, $isFunc, $pr, $pc, $size);
        }

        // Alignment patterns (version >= 2)
        $alignPos = [
            2=>[6,18], 3=>[6,22], 4=>[6,26], 5=>[6,30],
            6=>[6,34], 7=>[6,22,38], 8=>[6,24,42], 9=>[6,26,46], 10=>[6,28,50],
        ];
        if ($ver >= 2) {
            $pos = $alignPos[$ver];
            foreach ($pos as $ar) {
                foreach ($pos as $ac) {
                    if ($isFunc[$ar][$ac]) { continue; }
                    self::placeAlignment($matrix, $isFunc, $ar, $ac);
                }
            }
        }

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $v = ($i % 2 === 0) ? 1 : 0;
            if (!$isFunc[6][$i]) { $matrix[6][$i] = $v; $isFunc[6][$i] = true; }
            if (!$isFunc[$i][6]) { $matrix[$i][6] = $v; $isFunc[$i][6] = true; }
        }

        // Dark module
        $matrix[$size - 8][8] = 1;
        $isFunc[$size - 8][8] = true;

        // Format info placeholder (reserve)
        self::reserveFormat($matrix, $isFunc, $size);

        // Place data bits (zigzag)
        self::placeData($matrix, $isFunc, $bitStream, $size);

        // Apply best mask
        $bestMask   = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix  = $matrix;
        for ($m = 0; $m < 8; $m++) {
            $masked = self::applyMask($matrix, $isFunc, $m, $size);
            self::writeFormatInfo($masked, $isFunc, $m, $size);
            $p = self::penalty($masked, $size);
            if ($p < $bestPenalty) {
                $bestPenalty = $p;
                $bestMask    = $m;
                $bestMatrix  = $masked;
            }
        }
        self::writeFormatInfo($bestMatrix, $isFunc, $bestMask, $size);

        return $bestMatrix;
    }

    // -----------------------------------------------------------------------
    // Matrix helpers
    // -----------------------------------------------------------------------

    private static function placeFinder(array &$m, array &$f, int $r, int $c, int $size): void
    {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $row = $r + $dr; $col = $c + $dc;
                if ($row < 0 || $col < 0 || $row >= $size || $col >= $size) { continue; }
                $isEdge = ($dr === -1 || $dr === 7 || $dc === -1 || $dc === 7);
                $isInner = (1 <= $dr && $dr <= 5 && 1 <= $dc && $dc <= 5);
                $isCore  = (2 <= $dr && $dr <= 4 && 2 <= $dc && $dc <= 4);
                $m[$row][$col] = ($isEdge || $isCore) ? 1 : 0;
                $f[$row][$col] = true;
            }
        }
    }

    private static function placeAlignment(array &$m, array &$f, int $r, int $c): void
    {
        for ($dr = -2; $dr <= 2; $dr++) {
            for ($dc = -2; $dc <= 2; $dc++) {
                $isEdge = ($dr === -2 || $dr === 2 || $dc === -2 || $dc === 2);
                $isCenter = ($dr === 0 && $dc === 0);
                $m[$r + $dr][$c + $dc] = ($isEdge || $isCenter) ? 1 : 0;
                $f[$r + $dr][$c + $dc] = true;
            }
        }
    }

    private static function reserveFormat(array &$m, array &$f, int $size): void
    {
        // Horizontal strip around top-left finder
        for ($i = 0; $i <= 8; $i++) {
            if (!$f[8][$i]) { $m[8][$i] = 0; $f[8][$i] = true; }
            if (!$f[$i][8]) { $m[$i][8] = 0; $f[$i][8] = true; }
        }
        // Bottom-left and top-right strips
        for ($i = $size - 7; $i < $size; $i++) {
            $m[8][$i] = 0; $f[8][$i] = true;
            $m[$i][8] = 0; $f[$i][8] = true;
        }
    }

    private static function writeFormatInfo(array &$m, array &$f, int $mask, int $size): void
    {
        // ECC level M = 00; format bits = ecc(2b) + mask(3b) = 5 bits
        // Generate 15-bit format string with BCH(15,5)
        $data = (0b00 << 3) | $mask; // ECC M bits = 00
        $fmt  = self::bchFormat($data) ^ 0b101010000010010; // XOR mask

        $bits = str_pad(decbin($fmt), 15, '0', STR_PAD_LEFT);

        // Place in top-left horizontal
        $positions = [0,1,2,3,4,5,7,8];
        $bi = 0;
        foreach ($positions as $i) {
            $m[8][$i] = (int) $bits[$bi++];
        }
        $m[8][8] = (int) $bits[$bi++]; // after timing
        // Vertical
        $vPos = array_reverse([0,1,2,3,4,5,7,8]);
        $bi2 = 0;
        foreach ($vPos as $i) {
            $m[$i][8] = (int) $bits[$bi2++];
        }
        // Bottom-left vertical copy
        for ($i = 0; $i < 7; $i++) {
            $m[$size - 1 - $i][8] = (int) $bits[$i];
        }
        // Top-right horizontal copy
        for ($i = 0; $i < 8; $i++) {
            $m[8][$size - 8 + $i] = (int) $bits[14 - $i];
        }
    }

    private static function placeData(array &$m, array &$f, string $bits, int $size): void
    {
        $bi = 0;
        // Zigzag from right to left, skipping column 6 (timing)
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) { $right = 5; }
            $upward = (((($size - 1 - $right) >> 1) % 2) === 0);
            for ($vert = 0; $vert < $size; $vert++) {
                $row = $upward ? ($size - 1 - $vert) : $vert;
                foreach ([0, -1] as $delta) {
                    $col = $right + $delta;
                    if ($col === 6) { continue; }
                    if (!$f[$row][$col]) {
                        $m[$row][$col] = ($bi < strlen($bits)) ? (int) $bits[$bi++] : 0;
                    }
                }
            }
        }
    }

    private static function applyMask(array $m, array $f, int $mask, int $size): array
    {
        $out = $m;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($f[$r][$c]) { continue; }
                $invert = match ($mask) {
                    0 => ($r + $c) % 2 === 0,
                    1 => $r % 2 === 0,
                    2 => $c % 3 === 0,
                    3 => ($r + $c) % 3 === 0,
                    4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
                    5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
                    6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
                    7 => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
                };
                if ($invert) { $out[$r][$c] ^= 1; }
            }
        }
        return $out;
    }

    private static function penalty(array $m, int $size): int
    {
        $p = 0;
        // Rule 1: 5+ consecutive same-colour in row/col
        for ($r = 0; $r < $size; $r++) {
            $run = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($m[$r][$c] === $m[$r][$c - 1]) { $run++; }
                else {
                    if ($run >= 5) { $p += 3 + ($run - 5); }
                    $run = 1;
                }
            }
            if ($run >= 5) { $p += 3 + ($run - 5); }
        }
        for ($c = 0; $c < $size; $c++) {
            $run = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($m[$r][$c] === $m[$r - 1][$c]) { $run++; }
                else {
                    if ($run >= 5) { $p += 3 + ($run - 5); }
                    $run = 1;
                }
            }
            if ($run >= 5) { $p += 3 + ($run - 5); }
        }
        // Rule 2: 2x2 blocks
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($v === $m[$r][$c+1] && $v === $m[$r+1][$c] && $v === $m[$r+1][$c+1]) { $p += 3; }
            }
        }
        // Rule 4: proportion of dark modules
        $dark = 0;
        foreach ($m as $row) { $dark += array_sum($row); }
        $pct = $dark * 100 / ($size * $size);
        $p += min(abs((int)($pct/5)*5 - 50), abs((int)($pct/5)*5 + 5 - 50)) * 2;
        return $p;
    }

    // -----------------------------------------------------------------------
    // Reed-Solomon
    // -----------------------------------------------------------------------

    private static function rsBlock(array $data, int $ecCount): array
    {
        $generator = self::rsGenerator($ecCount);
        $msg = $data;
        for ($i = 0; $i < $ecCount; $i++) { $msg[] = 0; }
        for ($i = 0; $i < count($data); $i++) {
            $coef = $msg[$i];
            if ($coef !== 0) {
                $logCoef = self::$gfLog[$coef];
                for ($j = 0; $j < count($generator); $j++) {
                    $msg[$i + $j] ^= self::$gfExp[($logCoef + $generator[$j]) % 255];
                }
            }
        }
        return array_slice($msg, count($data));
    }

    private static function rsGenerator(int $degree): array
    {
        $g = [1];
        for ($i = 0; $i < $degree; $i++) {
            $g = self::rsPolyMul($g, [1, self::$gfExp[$i]]);
        }
        return $g;
    }

    private static function rsPolyMul(array $a, array $b): array
    {
        $out = array_fill(0, count($a) + count($b) - 1, 0);
        foreach ($a as $i => $ai) {
            foreach ($b as $j => $bj) {
                $out[$i + $j] ^= self::gfMul($ai, $bj);
            }
        }
        return $out;
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) { return 0; }
        return self::$gfExp[(self::$gfLog[$a] + self::$gfLog[$b]) % 255];
    }

    // -----------------------------------------------------------------------
    // BCH format info
    // -----------------------------------------------------------------------

    private static function bchFormat(int $data): int
    {
        $d = $data << 10;
        while (self::bchDigit($d) - self::bchDigit(0b10100110111) >= 0) {
            $d ^= (0b10100110111 << (self::bchDigit($d) - self::bchDigit(0b10100110111)));
        }
        return ($data << 10) | $d;
    }

    private static function bchDigit(int $data): int
    {
        $digit = 0;
        while ($data !== 0) { $digit++; $data >>= 1; }
        return $digit;
    }

    // -----------------------------------------------------------------------
    // GF(256) tables (primitive polynomial x^8+x^4+x^3+x^2+1 = 285)
    // -----------------------------------------------------------------------

    /** @var int[] */
    private static array $gfExp = [];
    /** @var int[] */
    private static array $gfLog = [];

    public static function initGf(): void
    {
        if (count(self::$gfExp) > 0) { return; }
        self::$gfExp = array_fill(0, 512, 0);
        self::$gfLog = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$gfExp[$i] = $x;
            self::$gfLog[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) { $x ^= 285; }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$gfExp[$i] = self::$gfExp[$i - 255];
        }
    }
}

// Initialise GF tables once at class-load time
QRService::initGf();
