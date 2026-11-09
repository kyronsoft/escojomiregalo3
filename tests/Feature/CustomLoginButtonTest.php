<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Empresa;
use Tests\TestCase;

class CustomLoginButtonTest extends TestCase
{
    private function render(array $overrides): string
    {
        return view('custom-login', array_merge([
            'empresa'    => new Empresa(['nombre' => 'Bayer']),
            'campaign'   => new Campaign(['nombre' => 'Navidad']),
            'ingresoUrl' => '/login',
            'bgUrl'      => 'data:image/svg+xml;charset=UTF-8,x',
            'token'      => 'token',
            'colorBoton' => '#2563EB',
            'textoBoton' => 'Ingresar',
            'errors'     => new \Illuminate\Support\ViewErrorBag(),
        ], $overrides))->render();
    }

    public function test_button_uses_configured_color_and_text(): void
    {
        $html = $this->render(['colorBoton' => '#FF0000', 'textoBoton' => 'Entrar ya']);

        $this->assertStringContainsString('style="background: #FF0000;">Entrar ya</button>', $html);
    }

    public function test_button_defaults_are_rendered_when_not_configured(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('style="background: #2563EB;">Ingresar</button>', $html);
    }
}
