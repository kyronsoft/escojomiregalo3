<?php

namespace App\Support;

class ColorContrast
{
    /**
     * Returns '#000000' or '#ffffff' for maximum readability against $hex.
     * Uses the WCAG relative luminance formula (threshold ~0.179).
     */
    public static function textColor(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '#000000';
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $toLinear = static fn(float $c): float => $c <= 0.04045
            ? $c / 12.92
            : (($c + 0.055) / 1.055) ** 2.4;

        $L = 0.2126 * $toLinear($r)
           + 0.7152 * $toLinear($g)
           + 0.0722 * $toLinear($b);

        return $L > 0.179 ? '#000000' : '#ffffff';
    }

    /**
     * Returns a subtle border color when $hex is a light color, transparent otherwise.
     */
    public static function subtleBorder(string $hex): string
    {
        return self::textColor($hex) === '#000000' ? 'rgba(0,0,0,.18)' : 'transparent';
    }
}
