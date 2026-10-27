<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Support\CampaignTokens;
use Tests\TestCase;

class CampaignTokensTest extends TestCase
{
    public function test_empty_template_uses_default_title(): void
    {
        $campaign = new Campaign(['nombre' => 'Navidad', 'mensaje_seleccion' => null]);

        $this->assertSame(
            'Hola, <i>Ana</i> seleccione su hijo(a)',
            CampaignTokens::tituloSeleccion($campaign, 'Ana', 'Empresa X')
        );
    }

    public function test_blank_template_uses_default_title(): void
    {
        $campaign = new Campaign(['mensaje_seleccion' => '   ']);

        $this->assertSame(
            'Hola, <i>Ana</i> seleccione su hijo(a)',
            CampaignTokens::tituloSeleccion($campaign, 'Ana')
        );
    }

    public function test_tokens_are_replaced(): void
    {
        $campaign = new Campaign([
            'nombre'            => 'Navidad 2026',
            'mensaje_seleccion' => '[EMPRESA] te invita a escoger para [NOMBRE CAMPAÑA] hasta el [FECHAFIN]',
            'fechafin'          => '2026-10-31 23:59:59',
        ]);

        $this->assertSame(
            'Bayer te invita a escoger para Navidad 2026 hasta el 31/10/2026',
            CampaignTokens::tituloSeleccion($campaign, 'Ana', 'Bayer')
        );
    }

    public function test_tokens_are_tolerant_to_case_and_spaces(): void
    {
        $campaign = new Campaign(['mensaje_seleccion' => 'Hola [ colaborador ] de [empresa]']);

        $this->assertSame(
            'Hola <i>Ana</i> de Bayer',
            CampaignTokens::tituloSeleccion($campaign, 'Ana', 'Bayer')
        );
    }

    public function test_user_text_and_values_are_escaped(): void
    {
        $campaign = new Campaign(['mensaje_seleccion' => '<script>x</script> [COLABORADOR] & [EMPRESA]']);

        $this->assertSame(
            '&lt;script&gt;x&lt;/script&gt; <i>Ana &lt;b&gt;</i> &amp; A&amp;B',
            CampaignTokens::tituloSeleccion($campaign, 'Ana <b>', 'A&B')
        );
    }

    public function test_unknown_tokens_are_kept(): void
    {
        $campaign = new Campaign(['mensaje_seleccion' => 'Hola [COLABORADOR] [DESCONOCIDO]']);

        $this->assertSame(
            'Hola <i>Ana</i> [DESCONOCIDO]',
            CampaignTokens::tituloSeleccion($campaign, 'Ana')
        );
    }
}
