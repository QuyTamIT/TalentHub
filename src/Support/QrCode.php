<?php
declare(strict_types=1);

namespace TalentHub\Support;

final class QrCode
{
    private const ECC_CODEWORDS_PER_BLOCK = [
        'L' => [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'M' => [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        'Q' => [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'H' => [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    private const NUM_ERROR_CORRECTION_BLOCKS = [
        'L' => [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        'M' => [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        'Q' => [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        'H' => [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    private const FORMAT_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

    private int $version;
    private int $size;
    private string $ecl;
    private array $modules = [];
    private array $isFunction = [];

    private function __construct(string $text, string $ecl)
    {
        $this->ecl = $ecl;
        $bytes = array_values(unpack('C*', $text) ?: []);
        $len = count($bytes);

        $version = 0;
        for ($v = 1; $v <= 40; $v++) {
            $ccBits = $v <= 9 ? 8 : 16;
            if (4 + $ccBits + 8 * $len <= self::numDataCodewords($v, $ecl) * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new \InvalidArgumentException('Data too long for QR code');
        }
        $this->version = $version;
        $this->size = $version * 4 + 17;

        $bits = [];
        self::appendBits($bits, 0x4, 4);
        self::appendBits($bits, $len, $version <= 9 ? 8 : 16);
        foreach ($bytes as $b) {
            self::appendBits($bits, $b, 8);
        }
        $capacity = self::numDataCodewords($version, $ecl) * 8;
        self::appendBits($bits, 0, min(4, $capacity - count($bits)));
        self::appendBits($bits, 0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $capacity; $pad ^= 0xEC ^ 0x11) {
            self::appendBits($bits, $pad, 8);
        }

        $data = array_fill(0, intdiv(count($bits), 8), 0);
        foreach ($bits as $i => $bit) {
            $data[$i >> 3] |= $bit << (7 - ($i & 7));
        }

        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);

        $this->drawFunctionPatterns();
        $this->drawCodewords($this->addEccAndInterleave($data));

        $bestMask = 0;
        $minPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $penalty = $this->penaltyScore();
            if ($penalty < $minPenalty) {
                $bestMask = $mask;
                $minPenalty = $penalty;
            }
            $this->applyMask($mask);
        }
        $this->applyMask($bestMask);
        $this->drawFormatBits($bestMask);
    }

    public static function svg(string $text, string $ecl = 'M', int $border = 4, string $dark = '#000000', string $light = '#ffffff'): string
    {
        $ecl = strtoupper($ecl);
        if (!isset(self::FORMAT_BITS[$ecl])) {
            $ecl = 'M';
        }
        $qr = new self($text, $ecl);
        $dim = $qr->size + $border * 2;
        $path = '';
        for ($y = 0; $y < $qr->size; $y++) {
            for ($x = 0; $x < $qr->size; $x++) {
                if ($qr->modules[$y][$x]) {
                    $path .= 'M' . ($x + $border) . ',' . ($y + $border) . 'h1v1h-1z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="' . htmlspecialchars($light, ENT_QUOTES) . '"/>'
            . '<path d="' . $path . '" fill="' . htmlspecialchars($dark, ENT_QUOTES) . '"/></svg>';
    }

    public static function svgDataUri(string $text, string $ecl = 'M', int $border = 4): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($text, $ecl, $border));
    }

    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function numRawDataModules(int $ver): int
    {
        $result = (16 * $ver + 128) * $ver + 64;
        if ($ver >= 2) {
            $numAlign = intdiv($ver, 7) + 2;
            $result -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($ver >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    private static function numDataCodewords(int $ver, string $ecl): int
    {
        return intdiv(self::numRawDataModules($ver), 8)
            - self::ECC_CODEWORDS_PER_BLOCK[$ecl][$ver] * self::NUM_ERROR_CORRECTION_BLOCKS[$ecl][$ver];
    }

    private function setFunction(int $x, int $y, bool $isDark): void
    {
        $this->modules[$y][$x] = $isDark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }
        $this->drawFinder(3, 3);
        $this->drawFinder($this->size - 4, 3);
        $this->drawFinder(3, $this->size - 4);

        $pos = $this->alignmentPositions();
        $n = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                $this->drawAlignment($pos[$i], $pos[$j]);
            }
        }
        $this->drawFormatBits(0);
        $this->drawVersion();
    }

    private function drawFinder(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $this->setFunction($xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignment(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private function alignmentPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }
        $numAlign = intdiv($this->version, 7) + 2;
        $step = $this->version === 32 ? 26 : (int)ceil(($this->version * 4 + 4) / ($numAlign * 2 - 2)) * 2;
        $result = array_fill(0, $numAlign, 0);
        $result[0] = 6;
        for ($i = $numAlign - 1, $pos = $this->size - 7; $i >= 1; $i--, $pos -= $step) {
            $result[$i] = $pos;
        }
        return $result;
    }

    private function drawFormatBits(int $mask): void
    {
        $data = self::FORMAT_BITS[$this->ecl] << 3 | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = ($data << 10 | $rem) ^ 0x5412;
        $bit = static fn(int $i): bool => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, $bit($i));
        }
        $this->setFunction(8, 7, $bit(6));
        $this->setFunction(8, 8, $bit(7));
        $this->setFunction(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, $bit($i));
        }
        $this->setFunction(8, $this->size - 8, true);
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = $this->version << 12 | $rem;
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $dark);
            $this->setFunction($b, $a, $dark);
        }
    }

    private function addEccAndInterleave(array $data): array
    {
        $numBlocks = self::NUM_ERROR_CORRECTION_BLOCKS[$this->ecl][$this->version];
        $blockEccLen = self::ECC_CODEWORDS_PER_BLOCK[$this->ecl][$this->version];
        $rawCodewords = intdiv(self::numRawDataModules($this->version), 8);
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen = intdiv($rawCodewords, $numBlocks);

        $divisor = self::rsDivisor($blockEccLen);
        $blocks = [];
        for ($i = 0, $k = 0; $i < $numBlocks; $i++) {
            $datLen = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
            $dat = array_slice($data, $k, $datLen);
            $k += $datLen;
            $ecc = self::rsRemainder($dat, $divisor);
            if ($i < $numShortBlocks) {
                $dat[] = 0;
            }
            $blocks[] = array_merge($dat, $ecc);
        }

        $result = [];
        $blockLen = count($blocks[0]);
        for ($i = 0; $i < $blockLen; $i++) {
            foreach ($blocks as $j => $block) {
                if ($i !== $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) {
                    $result[] = $block[$i];
                }
            }
        }
        return $result;
    }

    private static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::rsMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::rsMultiply($root, 0x02);
        }
        return $result;
    }

    private static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::rsMultiply($coef, $factor);
            }
        }
        return $result;
    }

    private static function rsMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }

    private function drawCodewords(array $data): void
    {
        $total = count($data) * 8;
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function penaltyScore(): int
    {
        $size = $this->size;
        $m = $this->modules;
        $penalty = 0;
        $pattern = [true, false, true, true, true, false, true];

        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $size; $a++) {
                $line = [];
                for ($b = 0; $b < $size; $b++) {
                    $line[] = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                }
                $run = 1;
                for ($b = 1; $b < $size; $b++) {
                    if ($line[$b] === $line[$b - 1]) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $penalty += $run - 2;
                        }
                        $run = 1;
                    }
                }
                if ($run >= 5) {
                    $penalty += $run - 2;
                }
                for ($b = 0; $b + 7 <= $size; $b++) {
                    if (array_slice($line, $b, 7) !== $pattern) {
                        continue;
                    }
                    $before = true;
                    for ($k = $b - 4; $k < $b; $k++) {
                        if ($k >= 0 && $line[$k]) {
                            $before = false;
                            break;
                        }
                    }
                    $after = true;
                    for ($k = $b + 7; $k < $b + 11; $k++) {
                        if ($k < $size && $line[$k]) {
                            $after = false;
                            break;
                        }
                    }
                    if ($before || $after) {
                        $penalty += 40;
                    }
                }
            }
        }

        $dark = 0;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($m[$y][$x]) {
                    $dark++;
                }
                if ($x + 1 < $size && $y + 1 < $size) {
                    $c = $m[$y][$x];
                    if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                        $penalty += 3;
                    }
                }
            }
        }
        $total = $size * $size;
        $k = (int)ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        $penalty += max(0, $k) * 10;

        return $penalty;
    }
}
