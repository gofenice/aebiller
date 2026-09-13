<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Draws EAN-13 barcodes as inline SVG, so shelf labels print without any
 * external request or image file.
 *
 * EAN-13 carries twelve digits plus a check digit. The first digit is not
 * drawn as bars at all — it is encoded in the pattern of odd and even
 * parities chosen for the six digits of the left half.
 */
class BarcodeGenerator
{
    /**
     * Bar patterns for the three encodings. 1 is a bar, 0 is a space.
     *
     * @var array<string, array<int, string>>
     */
    private const ENCODINGS = [
        'L' => ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'],
        'G' => ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'],
        'R' => ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'],
    ];

    /**
     * Which parity pattern the left half uses, chosen by the first digit.
     *
     * @var array<int, string>
     */
    private const PARITY = [
        'LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG',
        'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL',
    ];

    /**
     * The check digit for the first twelve digits of an EAN-13.
     */
    public function checkDigit(string $digits): int
    {
        $sum = 0;

        foreach (str_split(substr($digits, 0, 12)) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }

    /**
     * A scanner rejects a code whose check digit does not add up, so an
     * invalid barcode is worth catching before it reaches a label.
     */
    public function isValidEan13(?string $code): bool
    {
        return $code !== null
            && preg_match('/^\d{13}$/', $code) === 1
            && $this->checkDigit($code) === (int) $code[12];
    }

    /**
     * @param  int  $height  bar height in SVG units; the human-readable digits sit below
     */
    public function ean13(string $code, int $height = 60): string
    {
        if (! $this->isValidEan13($code)) {
            throw new InvalidArgumentException("[{$code}] is not a valid EAN-13 barcode.");
        }

        $parity = self::PARITY[(int) $code[0]];
        $modules = '101';

        for ($i = 1; $i <= 6; $i++) {
            $modules .= self::ENCODINGS[$parity[$i - 1]][(int) $code[$i]];
        }

        $modules .= '01010';

        for ($i = 7; $i <= 12; $i++) {
            $modules .= self::ENCODINGS['R'][(int) $code[$i]];
        }

        $modules .= '101';

        return $this->render($code, $modules, $height);
    }

    /**
     * The guard bars at the edges and in the middle run longer than the rest,
     * and the first digit is printed in the quiet zone to their left.
     */
    protected function render(string $code, string $modules, int $height): string
    {
        $quietZone = 11;
        $width = strlen($modules) + ($quietZone * 2);
        $guardDrop = 5;
        $textBaseline = $height + $guardDrop + 6;
        $totalHeight = $textBaseline + 2;

        $bars = '';

        foreach (str_split($modules) as $index => $module) {
            if ($module !== '1') {
                continue;
            }

            $isGuard = $index < 3
                || ($index >= 45 && $index < 50)
                || $index >= 92;

            $bars .= sprintf(
                '<rect x="%d" y="0" width="1" height="%d"/>',
                $index + $quietZone,
                $isGuard ? $height + $guardDrop : $height,
            );
        }

        $digits = sprintf(
            '<text x="%d" y="%d" font-size="7" text-anchor="middle">%s</text>'
            .'<text x="%d" y="%d" font-size="7" text-anchor="middle">%s</text>'
            .'<text x="%d" y="%d" font-size="7" text-anchor="end">%s</text>',
            $quietZone + 3 + 21, $textBaseline, substr($code, 1, 6),
            $quietZone + 50 + 21, $textBaseline, substr($code, 7, 6),
            $quietZone - 2, $textBaseline, $code[0],
        );

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="100%%" '
            .'shape-rendering="crispEdges" font-family="monospace" fill="#000">'
            .'<rect x="0" y="0" width="%d" height="%d" fill="#fff"/>%s%s</svg>',
            $width, $totalHeight, $width, $totalHeight, $bars, $digits,
        );
    }
}
