<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\ColorContrast;

class ColorContrastTest extends TestCase
{
    /** Fondo negro → texto blanco */
    public function test_negro_produce_texto_blanco(): void
    {
        $this->assertSame('#ffffff', ColorContrast::textColor('#000000'));
    }

    /** Fondo blanco → texto negro */
    public function test_blanco_produce_texto_negro(): void
    {
        $this->assertSame('#000000', ColorContrast::textColor('#ffffff'));
    }

    /** Color primario oscuro por defecto (#1B4C43) → texto blanco */
    public function test_verde_oscuro_produce_texto_blanco(): void
    {
        $this->assertSame('#ffffff', ColorContrast::textColor('#1B4C43'));
    }

    /** Color dorado claro (#BA895D) → texto negro (luminancia media-alta) */
    public function test_dorado_produce_texto_negro(): void
    {
        // #BA895D tiene luminancia ~0.24, mayor que 0.179, por lo que corresponde texto negro
        $this->assertSame('#000000', ColorContrast::textColor('#BA895D'));
    }

    /** Notación corta #fff equivale a #ffffff */
    public function test_notacion_corta_blanco(): void
    {
        $this->assertSame('#000000', ColorContrast::textColor('#fff'));
    }

    /** Notación corta #000 equivale a #000000 */
    public function test_notacion_corta_negro(): void
    {
        $this->assertSame('#ffffff', ColorContrast::textColor('#000'));
    }

    /** Blanco sobre negro tiene contraste suficiente (>= 4.5) */
    public function test_blanco_sobre_negro_tiene_contraste_suficiente(): void
    {
        $this->assertTrue(ColorContrast::hasEnoughContrast('#ffffff', '#000000'));
    }

    /** Blanco sobre blanco NO tiene contraste suficiente */
    public function test_blanco_sobre_blanco_sin_contraste(): void
    {
        $this->assertFalse(ColorContrast::hasEnoughContrast('#ffffff', '#ffffff'));
    }

    /** Luminancia del blanco es 1.0 */
    public function test_luminancia_blanco(): void
    {
        $this->assertEqualsWithDelta(1.0, ColorContrast::relativeLuminance('#ffffff'), 0.001);
    }

    /** Luminancia del negro es 0.0 */
    public function test_luminancia_negro(): void
    {
        $this->assertEqualsWithDelta(0.0, ColorContrast::relativeLuminance('#000000'), 0.001);
    }
}
