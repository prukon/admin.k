<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * QR Model 2, byte mode, ECC M, версии 1–10.
 * Для URL лендинга на HTML/PDF без composer-пакета (vendor не пишется от dev).
 */
final class UrlQrCode
{
    /** @var list<list<int>> count, total, data, … на версию (ECC M) */
    private const RS_M = [
        1 => [1, 26, 16],
        2 => [1, 44, 28],
        3 => [1, 70, 44],
        4 => [2, 50, 32],
        5 => [2, 67, 43],
        6 => [4, 43, 27],
        7 => [4, 49, 31],
        8 => [2, 60, 38, 2, 61, 39],
        9 => [3, 58, 36, 2, 59, 37],
        10 => [4, 69, 43, 1, 70, 44],
    ];

    /** @var array<int, list<int>> */
    private const ALIGN = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    public static function pngDataUri(string $url, int $modulePx = 4, int $marginModules = 2): string
    {
        $modules = self::modules($url);
        $n = count($modules);
        $dim = ($n + 2 * $marginModules) * $modulePx;
        $im = imagecreatetruecolor($dim, $dim);
        if ($im === false) {
            throw new RuntimeException('Не удалось создать изображение QR.');
        }
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        if ($white === false || $black === false) {
            imagedestroy($im);
            throw new RuntimeException('Не удалось выделить цвета QR.');
        }
        imagefill($im, 0, 0, $white);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if (!$modules[$y][$x]) {
                    continue;
                }
                $x0 = ($x + $marginModules) * $modulePx;
                $y0 = ($y + $marginModules) * $modulePx;
                imagefilledrectangle($im, $x0, $y0, $x0 + $modulePx - 1, $y0 + $modulePx - 1, $black);
            }
        }
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);
        if ($png === '') {
            throw new RuntimeException('Не удалось закодировать PNG QR.');
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * @return list<list<bool>>
     */
    public static function modules(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            throw new InvalidArgumentException('Пустой URL для QR.');
        }

        $bytes = array_values(unpack('C*', $url) ?: []);
        $type = self::pickVersion(count($bytes));
        $data = self::createData($type, $bytes);
        $size = $type * 4 + 17;

        $best = null;
        $bestLost = null;
        for ($mask = 0; $mask < 8; $mask++) {
            $grid = self::buildGrid($type, $size, $data, $mask, true);
            $lost = self::lostPoint($grid, $size);
            if ($bestLost === null || $lost < $bestLost) {
                $bestLost = $lost;
                $best = $mask;
            }
        }

        return self::buildGrid($type, $size, $data, (int) $best, false);
    }

    private static function pickVersion(int $byteLen): int
    {
        for ($type = 1; $type <= 10; $type++) {
            $capacity = self::dataCapacity($type);
            $bits = 4 + ($type <= 9 ? 8 : 16) + $byteLen * 8 + 4;
            if ($bits <= $capacity * 8) {
                return $type;
            }
        }

        throw new InvalidArgumentException('URL слишком длинный для QR.');
    }

    private static function dataCapacity(int $type): int
    {
        $table = self::RS_M[$type];
        $sum = 0;
        for ($i = 0; $i < count($table); $i += 3) {
            $sum += $table[$i] * $table[$i + 2];
        }

        return $sum;
    }

    /**
     * @param list<int> $bytes
     * @return list<int>
     */
    private static function createData(int $type, array $bytes): array
    {
        $blocks = self::rsBlocks($type);
        $totalData = 0;
        foreach ($blocks as $b) {
            $totalData += $b[1];
        }

        $buf = [];
        $lenBits = $type <= 9 ? 8 : 16;
        self::put($buf, 0b0100, 4);
        self::put($buf, count($bytes), $lenBits);
        foreach ($bytes as $b) {
            self::put($buf, $b, 8);
        }
        $maxBits = $totalData * 8;
        if (count($buf) + 4 <= $maxBits) {
            self::put($buf, 0, 4);
        }
        while (count($buf) % 8 !== 0) {
            $buf[] = 0;
        }
        $pad = [0xEC, 0x11];
        $pi = 0;
        while (count($buf) < $maxBits) {
            self::put($buf, $pad[$pi % 2], 8);
            $pi++;
        }
        $buf = array_slice($buf, 0, $maxBits);

        $raw = [];
        for ($i = 0; $i < $totalData; $i++) {
            $v = 0;
            for ($b = 0; $b < 8; $b++) {
                $v = ($v << 1) | $buf[$i * 8 + $b];
            }
            $raw[] = $v;
        }

        return self::interleave($raw, $blocks);
    }

    /**
     * @return list<array{0:int,1:int}> total, data
     */
    private static function rsBlocks(int $type): array
    {
        $t = self::RS_M[$type];
        $out = [];
        for ($i = 0; $i < count($t); $i += 3) {
            for ($j = 0; $j < $t[$i]; $j++) {
                $out[] = [$t[$i + 1], $t[$i + 2]];
            }
        }

        return $out;
    }

    /**
     * @param list<int> $raw
     * @param list<array{0:int,1:int}> $blocks
     * @return list<int>
     */
    private static function interleave(array $raw, array $blocks): array
    {
        $offset = 0;
        $dc = [];
        $ec = [];
        $maxDc = 0;
        $maxEc = 0;
        foreach ($blocks as $idx => $b) {
            [$total, $dataCount] = $b;
            $ecCount = $total - $dataCount;
            $maxDc = max($maxDc, $dataCount);
            $maxEc = max($maxEc, $ecCount);
            $dc[$idx] = array_slice($raw, $offset, $dataCount);
            $offset += $dataCount;
            $ec[$idx] = self::rsRemainder($dc[$idx], $ecCount);
        }

        $out = [];
        for ($i = 0; $i < $maxDc; $i++) {
            foreach ($dc as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $maxEc; $i++) {
            foreach ($ec as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }

        return $out;
    }

    /**
     * @param list<int> $data
     * @return list<int>
     */
    private static function rsRemainder(array $data, int $ecCount): array
    {
        [$exp, $log] = self::gf();
        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $gen = self::polyMul($gen, [1, $exp[$i]], $exp, $log);
        }
        $info = array_merge($data, array_fill(0, $ecCount, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $info[$i];
            if ($coef === 0) {
                continue;
            }
            $factor = $log[$coef];
            for ($j = 0; $j < count($gen); $j++) {
                $info[$i + $j] ^= $exp[($log[$gen[$j]] + $factor) % 255];
            }
        }

        return array_slice($info, count($data));
    }

    /**
     * @param list<int> $a
     * @param list<int> $b
     * @param list<int> $exp
     * @param array<int, int> $log
     * @return list<int>
     */
    private static function polyMul(array $a, array $b, array $exp, array $log): array
    {
        $n = array_fill(0, count($a) + count($b) - 1, 0);
        for ($i = 0; $i < count($a); $i++) {
            for ($j = 0; $j < count($b); $j++) {
                if ($a[$i] === 0 || $b[$j] === 0) {
                    continue;
                }
                $n[$i + $j] ^= $exp[($log[$a[$i]] + $log[$b[$j]]) % 255];
            }
        }

        return $n;
    }

    /**
     * @return array{0: list<int>, 1: array<int, int>}
     */
    private static function gf(): array
    {
        $exp = [];
        $log = [];
        $exp[0] = 1;
        for ($i = 1; $i < 256; $i++) {
            $v = $exp[$i - 1] << 1;
            if ($v & 0x100) {
                $v ^= 0x11d;
            }
            $exp[$i] = $v;
        }
        for ($i = 0; $i < 255; $i++) {
            $log[$exp[$i]] = $i;
        }

        return [$exp, $log];
    }

    /**
     * @param list<int> $bits
     */
    private static function put(array &$bits, int $value, int $len): void
    {
        for ($i = $len - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    /**
     * @param list<int> $data
     * @return list<list<bool>>
     */
    private static function buildGrid(int $type, int $size, array $data, int $mask, bool $test): array
    {
        $m = [];
        for ($r = 0; $r < $size; $r++) {
            $m[$r] = array_fill(0, $size, null);
        }
        self::probe($m, $size, 0, 0);
        self::probe($m, $size, $size - 7, 0);
        self::probe($m, $size, 0, $size - 7);
        self::align($m, $type);
        self::timing($m, $size);
        self::typeInfo($m, $size, $mask, $test);
        if ($type >= 7) {
            self::typeNumber($m, $size, $type, $test);
        }
        self::mapData($m, $size, $data, $mask);

        $out = [];
        for ($r = 0; $r < $size; $r++) {
            $out[$r] = [];
            for ($c = 0; $c < $size; $c++) {
                $out[$r][$c] = (bool) $m[$r][$c];
            }
        }

        return $out;
    }

    /**
     * @param array<int, array<int, bool|null>> $m
     */
    private static function probe(array &$m, int $size, int $row, int $col): void
    {
        for ($r = -1; $r <= 7; $r++) {
            if ($row + $r < 0 || $row + $r >= $size) {
                continue;
            }
            for ($c = -1; $c <= 7; $c++) {
                if ($col + $c < 0 || $col + $c >= $size) {
                    continue;
                }
                $dark = (0 <= $r && $r <= 6 && ($c === 0 || $c === 6))
                    || (0 <= $c && $c <= 6 && ($r === 0 || $r === 6))
                    || (2 <= $r && $r <= 4 && 2 <= $c && $c <= 4);
                $m[$row + $r][$col + $c] = $dark;
            }
        }
    }

    /**
     * @param array<int, array<int, bool|null>> $m
     */
    private static function align(array &$m, int $type): void
    {
        $pos = self::ALIGN[$type];
        foreach ($pos as $row) {
            foreach ($pos as $col) {
                if ($m[$row][$col] !== null) {
                    continue;
                }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $m[$row + $r][$col + $c] = ($r === -2 || $r === 2 || $c === -2 || $c === 2 || ($r === 0 && $c === 0));
                    }
                }
            }
        }
    }

    /**
     * @param array<int, array<int, bool|null>> $m
     */
    private static function timing(array &$m, int $size): void
    {
        for ($r = 8; $r < $size - 8; $r++) {
            if ($m[$r][6] === null) {
                $m[$r][6] = ($r % 2 === 0);
            }
        }
        for ($c = 8; $c < $size - 8; $c++) {
            if ($m[6][$c] === null) {
                $m[6][$c] = ($c % 2 === 0);
            }
        }
    }

    /**
     * @param array<int, array<int, bool|null>> $m
     */
    private static function typeInfo(array &$m, int $size, int $mask, bool $test): void
    {
        $data = (0 << 3) | $mask;
        $bits = self::bchTypeInfo($data);
        for ($i = 0; $i < 15; $i++) {
            $mod = !$test && ((($bits >> $i) & 1) === 1);
            if ($i < 6) {
                $m[$i][8] = $mod;
            } elseif ($i < 8) {
                $m[$i + 1][8] = $mod;
            } else {
                $m[$size - 15 + $i][8] = $mod;
            }
        }
        for ($i = 0; $i < 15; $i++) {
            $mod = !$test && ((($bits >> $i) & 1) === 1);
            if ($i < 8) {
                $m[8][$size - $i - 1] = $mod;
            } elseif ($i < 9) {
                $m[8][15 - $i] = $mod;
            } else {
                $m[8][15 - $i - 1] = $mod;
            }
        }
        $m[$size - 8][8] = !$test;
    }

    /**
     * @param array<int, array<int, bool|null>> $m
     */
    private static function typeNumber(array &$m, int $size, int $type, bool $test): void
    {
        $bits = self::bchTypeNumber($type);
        for ($i = 0; $i < 18; $i++) {
            $mod = !$test && ((($bits >> $i) & 1) === 1);
            $m[(int) floor($i / 3)][$i % 3 + $size - 8 - 3] = $mod;
            $m[$i % 3 + $size - 8 - 3][(int) floor($i / 3)] = $mod;
        }
    }

    private static function bchTypeInfo(int $data): int
    {
        $g15 = 0x537;
        $d = $data << 10;
        while (self::bitLen($d) - self::bitLen($g15) >= 0) {
            $d ^= $g15 << (self::bitLen($d) - self::bitLen($g15));
        }

        return (($data << 10) | $d) ^ 0x5412;
    }

    private static function bchTypeNumber(int $data): int
    {
        $g18 = 0x1f25;
        $d = $data << 12;
        while (self::bitLen($d) - self::bitLen($g18) >= 0) {
            $d ^= $g18 << (self::bitLen($d) - self::bitLen($g18));
        }

        return ($data << 12) | $d;
    }

    private static function bitLen(int $n): int
    {
        $d = 0;
        while ($n !== 0) {
            $d++;
            $n >>= 1;
        }

        return $d;
    }

    /**
     * @param array<int, array<int, bool|null>> $m
     * @param list<int> $data
     */
    private static function mapData(array &$m, int $size, array $data, int $mask): void
    {
        $inc = -1;
        $row = $size - 1;
        $bitIndex = 7;
        $byteIndex = 0;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            while (true) {
                for ($c = 0; $c < 2; $c++) {
                    if ($m[$row][$col - $c] === null) {
                        $dark = false;
                        if ($byteIndex < count($data)) {
                            $dark = ((($data[$byteIndex] >> $bitIndex) & 1) === 1);
                        }
                        if (self::maskFn($mask, $row, $col - $c)) {
                            $dark = !$dark;
                        }
                        $m[$row][$col - $c] = $dark;
                        $bitIndex--;
                        if ($bitIndex === -1) {
                            $byteIndex++;
                            $bitIndex = 7;
                        }
                    }
                }
                $row += $inc;
                if ($row < 0 || $row >= $size) {
                    $row -= $inc;
                    $inc = -$inc;
                    break;
                }
            }
        }
    }

    private static function maskFn(int $mask, int $i, int $j): bool
    {
        return match ($mask) {
            0 => ($i + $j) % 2 === 0,
            1 => $i % 2 === 0,
            2 => $j % 3 === 0,
            3 => ($i + $j) % 3 === 0,
            4 => ((int) floor($i / 2) + (int) floor($j / 3)) % 2 === 0,
            5 => ($i * $j) % 2 + ($i * $j) % 3 === 0,
            6 => (($i * $j) % 2 + ($i * $j) % 3) % 2 === 0,
            default => (($i * $j) % 3 + ($i + $j) % 2) % 2 === 0,
        };
    }

    /**
     * @param list<list<bool>> $m
     */
    private static function lostPoint(array $m, int $size): int
    {
        $lost = 0;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $same = 0;
                $dark = $m[$r][$c];
                for ($dr = -1; $dr <= 1; $dr++) {
                    if ($r + $dr < 0 || $r + $dr >= $size) {
                        continue;
                    }
                    for ($dc = -1; $dc <= 1; $dc++) {
                        if ($c + $dc < 0 || $c + $dc >= $size || ($dr === 0 && $dc === 0)) {
                            continue;
                        }
                        if ($dark === $m[$r + $dr][$c + $dc]) {
                            $same++;
                        }
                    }
                }
                if ($same > 5) {
                    $lost += 3 + $same - 5;
                }
            }
        }

        return $lost;
    }
}
