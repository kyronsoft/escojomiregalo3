<?php

namespace Tests\Feature;

use App\Mail\SelectionCompleted;
use App\Models\Campaign;
use App\Models\Empresa;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_banner_url_is_null_when_not_configured(): void
    {
        $this->assertNull((new Campaign(['banner' => null]))->bannerUrl());
        $this->assertNull((new Campaign(['banner' => '']))->bannerUrl());
        $this->assertNull((new Campaign(['banner' => 'ND']))->bannerUrl());
    }

    public function test_banner_url_is_null_when_file_is_missing(): void
    {
        $campaign = new Campaign(['banner' => 'campaigns/1/banner.png']);

        $this->assertNull($campaign->bannerUrl());
    }

    public function test_banner_url_is_returned_when_file_exists(): void
    {
        Storage::disk('public')->put('campaigns/1/banner.png', 'x');

        $campaign = new Campaign(['banner' => 'campaigns/1/banner.png']);

        $this->assertSame(url('/storage/campaigns/1/banner.png'), $campaign->bannerUrl());
    }

    public function test_banner_url_accepts_legacy_storage_prefix(): void
    {
        Storage::disk('public')->put('campaigns/1/banner.png', 'x');

        $campaign = new Campaign(['banner' => 'storage/app/public/campaigns/1/banner.png']);

        $this->assertSame(url('/storage/campaigns/1/banner.png'), $campaign->bannerUrl());
    }

    public function test_logo_uses_stored_path_when_it_exists(): void
    {
        Storage::disk('public')->put('campaigns/800141506/logo.png', 'x');

        $empresa = new Empresa(['nit' => '800141506', 'logo' => 'campaigns/800141506/logo.png']);

        $this->assertSame(url('/storage/campaigns/800141506/logo.png'), $empresa->logoUrl());
    }

    public function test_logo_falls_back_to_empresas_folder_when_stored_path_is_empty(): void
    {
        Storage::disk('public')->put('empresas/800194600/logo.jpg', 'x');

        $empresa = new Empresa(['nit' => '800194600', 'logo' => null]);

        $this->assertSame(url('/storage/empresas/800194600/logo.jpg'), $empresa->logoUrl());
    }

    public function test_logo_is_null_when_no_file_exists(): void
    {
        $empresa = new Empresa(['nit' => '999', 'logo' => 'campaigns/999/logo.png']);

        $this->assertNull($empresa->logoUrl());
    }

    public function test_selection_mail_shows_child_name_next_to_each_reference(): void
    {
        $mailable = new SelectionCompleted('Ana', null, [
            [
                'referencia'   => 'MGP-61651',
                'toy_nombre'   => 'SLIDING TOY CAR',
                'imagenppal'   => '',
                'idcampaing'   => 27,
                'child_nombre' => '3 Niño',
            ],
        ]);

        $html = $mailable->render();

        $this->assertStringContainsString('Para: <strong>3 Niño</strong>', $html);
        $this->assertStringContainsString('Ref: <strong>MGP-61651</strong>', $html);
    }
}
