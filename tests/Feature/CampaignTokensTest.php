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

    public function test_confirmation_without_campaign_uses_default_text(): void
    {
        $this->assertSame(
            e(CampaignTokens::CONFIRMACION_DEFAULT),
            CampaignTokens::textoConfirmacion(null)
        );
    }

    public function test_blank_confirmation_uses_default_text(): void
    {
        $campaign = new Campaign(['mensaje_confirmacion' => '  ']);

        $this->assertSame(
            e(CampaignTokens::CONFIRMACION_DEFAULT),
            CampaignTokens::textoConfirmacion($campaign)
        );
    }

    public function test_confirmation_replaces_tokens_and_keeps_line_breaks(): void
    {
        $campaign = new Campaign([
            'nombre'              => 'Navidad 2026',
            'mensaje_confirmacion' => "Gracias por participar en [NOMBRE CAMPAÑA].\nMás info con [EMPRESA] hasta el [FECHAFIN].",
            'fechafin'            => '2026-10-31 23:59:59',
        ]);
        $campaign->setRelation('empresa', new \App\Models\Empresa(['nombre' => 'Bayer']));

        $this->assertSame(
            "Gracias por participar en Navidad 2026.<br>\nMás info con Bayer hasta el 31/10/2026.",
            CampaignTokens::textoConfirmacion($campaign)
        );
    }

    public function test_confirmation_escapes_user_text(): void
    {
        $campaign = new Campaign(['mensaje_confirmacion' => '<b>[EMPRESA]</b>']);
        $campaign->setRelation('empresa', new \App\Models\Empresa(['nombre' => 'A&B']));

        $this->assertSame(
            '&lt;b&gt;A&amp;B&lt;/b&gt;',
            CampaignTokens::textoConfirmacion($campaign)
        );
    }
}
