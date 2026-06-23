<?php

namespace App\Support;

class ColorContrast
{
    /**
     * Returns '#ffffff' or '#000000' whichever has better contrast against $hexBg.
     * Uses WCAG relative luminance formula.
     */
    public static function textColor(string $hexBg): string
    {
        $luminance = self::relativeLuminance($hexBg);
        // WCAG contrast ratio: white on bg = (1 + 0.05) / (L + 0.05)
        // Choose white when bg is dark (luminance < 0.179 threshold ~= 3:1 ratio midpoint)
        return $luminance < 0.179 ? '#ffffff' : '#000000';
    }

    /**
     * Returns true when two hex colors have a WCAG contrast ratio >= $minRatio.
     */
    public static function hasEnoughContrast(string $hexFg, string $hexBg, float $minRatio = 4.5): bool
    {
        $L1 = self::relativeLuminance($hexFg);
        $L2 = self::relativeLuminance($hexBg);
        [$lighter, $darker] = $L1 > $L2 ? [$L1, $L2] : [$L2, $L1];
        $ratio = ($lighter + 0.05) / ($darker + 0.05);
        return $ratio >= $minRatio;
    }

    /**
     * Returns the WCAG relative luminance of a hex color (0–1).
     */
    public static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = self::hexToRgbLinear($hex);
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Parses a hex color string (#rgb, #rrggbb) and returns linearized [r, g, b] components (0–1).
     */
    private static function hexToRgbLinear(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        return [self::linearize($r), self::linearize($g), self::linearize($b)];
    }

    private static function linearize(float $c): float
    {
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }
}
