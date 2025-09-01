@extends('ecommerce.main')

@section('content')
    @php
        use Illuminate\Support\Facades\Storage;
        use Illuminate\Support\Str;
    @endphp

    <div class="container-fluid px-0">
        {{-- Banner campaña (full-bleed) --}}
        @if (!empty($campaignBannerUrl))
            <style>
                .campaign-banner-bleed {
                    width: 100vw;
                    margin-left: calc(50% - 50vw);
                    margin-right: calc(50% - 50vw);
                }

                .campaign-banner-bleed img {
                    display: block;
                    width: 100%;
                    height: 25vh;
                    object-fit: cover;
                }

                @media (min-width: 992px) {
                    .campaign-banner-bleed img {
                        height: 33.333vh;
                    }
                }
            </style>
            <div class="campaign-banner-bleed">
                <img src="{{ $campaignBannerUrl }}" alt="Banner campaña" loading="lazy">
            </div>
        @else
            <img class="img-fluid w-100" src="{{ asset('storage/images/banner-claro.jpg') }}" alt="Banner">
        @endif
    </div>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body text-center p-5">

                        <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle"
                            style="width:84px;height:84px;">
                            <i class="icon-check" style="font-size: 36px; color:#198754;"></i>
                        </div>

                        <h3 class="mb-2">¡Listo! Tu selección ha finalizado</h3>
                        <p class="text-muted mb-4">
                            Hemos registrado tus juguetes seleccionados. Recibirás un correo electrónico confirmando las
                            referencias que has elegido.
                        </p>

                        @if (session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif

                        {{-- Resumen (opcional) --}}
                        @isset($items)
                            @if ($items->count())
                                <div class="table-responsive mb-4">
                                    <table class="table table-borderless align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:140px;">Imagen</th>
                                                <th>Referencia / Nombre</th>
                                                <th style="width:120px;" class="text-center">Cantidad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($items as $row)
                                                @php
                                                    $imgRel = trim((string) ($row->imagenppal ?? ''));
                                                    $imgRel = ltrim($imgRel, '/');
                                                    $imgPath =
                                                        $imgRel !== ''
                                                            ? (Str::startsWith($imgRel, 'campaign_toys/')
                                                                ? $imgRel
                                                                : "campaign_toys/{$row->idcampaing}/{$imgRel}")
                                                            : '';
                                                @endphp
                                                <tr>
                                                    <td class="text-center">
                                                        @if ($imgPath !== '')
                                                            <img src="{{ Storage::url($imgPath) }}" alt="{{ $row->toy_nombre }}"
                                                                class="img-fluid" style="max-width:120px">
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="small text-muted">Ref:
                                                            <strong>{{ $row->referencia }}</strong>
                                                        </div>
                                                        <div class="fw-semibold">{{ $row->toy_nombre }}</div>
                                                    </td>
                                                    <td class="text-center"><span class="badge bg-secondary">1</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endisset
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
