<?php

if (! function_exists('normalizeHexColor')) {
    /**
     * Normaliza un color a formato #rrggbb en minúsculas.
     *
     * Acepta "#rgb", "#rrggbb", con o sin "#" y con espacios sobrantes.
     * Si el valor no es un hex válido devuelve el fallback, evitando que un
     * color mal configurado desde la empresa (p. ej. "#FFF", "white", vacío)
     * rompa el cálculo de contraste o genere CSS inválido.
     */
    function normalizeHexColor(?string $hex, string $fallback = '#000000'): string
    {
        $hex = ltrim(strtolower(trim((string) $hex)), '#');

        if (preg_match('/^[0-9a-f]{3}$/', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return preg_match('/^[0-9a-f]{6}$/', $hex) ? '#' . $hex : $fallback;
    }
}

if (! function_exists('contrastColor')) {
    /**
     * Devuelve el color de texto (oscuro o claro) que garantiza legibilidad
     * sobre el color de fondo dado, usando la luminancia percibida (YIQ).
     *
     * Funciona para cualquier color configurado por la empresa; los casos
     * problemáticos (blanco puro, negro puro y grises) quedan cubiertos.
     */
    function contrastColor($hex, string $dark = '#000000', string $light = '#ffffff'): string
    {
        $hex = ltrim(normalizeHexColor($hex), '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $yiq = ($r * 299 + $g * 587 + $b * 114) / 1000;

        return $yiq >= 128 ? $dark : $light;
    }
}
