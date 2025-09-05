@extends('layouts.admin.master')

@section('title', 'Editar Juguete')

@push('css')
    <style>
        .toy-thumb {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border-radius: .5rem;
            background: #f5f5f5;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Editar juguete — {{ $campaign->nombre }} (ID {{ $campaign->id }})</h3>
            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>

        {{-- Errores de validación --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Hay errores en el formulario:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Flash --}}
        @if (session('success'))
            <div class="alert alert-success mb-3">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger mb-3">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST"
                    action="{{ route('campaigns.toys.update', ['campaign' => $campaign->id, 'toy' => $toy->id]) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label">ID</label>
                            <input type="text" class="form-control" value="{{ $toy->id }}" disabled>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label" for="referencia">Referencia</label>
                            <input type="text" id="referencia" name="referencia"
                                class="form-control @error('referencia') is-invalid @enderror" maxlength="100"
                                value="{{ old('referencia', $toy->referencia) }}">
                            @error('referencia')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="nombre">Nombre</label>
                            <input type="text" id="nombre" name="nombre"
                                class="form-control @error('nombre') is-invalid @enderror"
                                value="{{ old('nombre', $toy->nombre) }}">
                            @error('nombre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label" for="genero">Género</label>
                            @php $g = old('genero', $toy->genero); @endphp
                            <select id="genero" name="genero" class="form-select @error('genero') is-invalid @enderror">
                                <option value="">Seleccione</option>
                                <option value="NIÑO" @selected($g === 'NIÑO')>NIÑO</option>
                                <option value="NIÑA" @selected($g === 'NIÑA')>NIÑA</option>
                                <option value="UNISEX" @selected($g === 'UNISEX')>UNISEX</option>
                            </select>
                            @error('genero')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label" for="unidades">Unidades</label>
                            <input type="number" id="unidades" name="unidades" min="0" step="1"
                                class="form-control @error('unidades') is-invalid @enderror"
                                value="{{ old('unidades', $toy->unidades) }}">
                            @error('unidades')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label" for="precio_unitario">Precio unitario</label>
                            <input type="number" id="precio_unitario" name="precio_unitario" min="0" step="1"
                                class="form-control @error('precio_unitario') is-invalid @enderror"
                                value="{{ old('precio_unitario', $toy->precio_unitario) }}">
                            @error('precio_unitario')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label" for="porcentaje">% / Seleccionadas</label>
                            <input type="text" id="porcentaje" name="porcentaje"
                                class="form-control @error('porcentaje') is-invalid @enderror"
                                value="{{ old('porcentaje', $toy->porcentaje) }}">
                            @error('porcentaje')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="descripcion">Descripción</label>
                            <textarea id="descripcion" name="descripcion" rows="4"
                                class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $toy->descripcion) }}</textarea>
                            @error('descripcion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Imágenes del juguete</label>

                            @php
                                $placeholder = asset('assets/images/placeholder.png');
                                // Mapa ref => URL pública (lo pasa el controlador)
                                $imageMap = $toy->image_map ?? [];
                                // Partes (refs) del combo, o única referencia
                                $parts = array_keys($imageMap);
                                $defaultSp = old('sp_path', $toy->referencia . '.jpg'); // raíz del drive
                            @endphp

                            {{-- Galería (todas las partes) --}}
                            <div class="d-flex flex-wrap gap-3 mb-3">
                                @forelse ($parts as $ref)
                                    @php $u = $imageMap[$ref] ?: $placeholder; @endphp
                                    <div class="text-center">
                                        <img id="toy-thumb-{{ $ref }}" src="{{ $u }}"
                                            class="toy-thumb" alt="img {{ $ref }}"
                                            onerror="this.onerror=null; this.src='{{ $placeholder }}';">
                                        <div class="small mt-1 text-muted">{{ $ref }}</div>
                                    </div>
                                @empty
                                    {{-- fallback si no se pudo calcular partes --}}
                                    <div class="text-center">
                                        <img id="toy-thumb-__single" src="{{ $toy->image_url ?? $placeholder }}"
                                            class="toy-thumb" alt="imagen"
                                            onerror="this.onerror=null; this.src='{{ $placeholder }}';">
                                    </div>
                                @endforelse
                            </div>

                            {{-- Herramientas SharePoint (no es form para evitar anidar) --}}
                            <div id="sp-tools" class="row g-2">
                                @csrf
                                <div class="col-12">
                                    <label class="form-label" for="sp_path">Nombre(s) de archivo en SharePoint</label>
                                    <input type="text" id="sp_path" name="sp_path" class="form-control"
                                        placeholder="Dejar vacío para usar {{ $toy->referencia }} dividido por +. Ej: ABC.jpg o ABC+DEF"
                                        value="{{ $defaultSp }}">
                                    <div class="form-text">
                                        Las imágenes están en la <strong>raíz</strong> del drive. Si no indicas extensión,
                                        se intentará .jpg, .jpeg, .png.<br>
                                        Si la referencia es un combo (con <code>+</code>), se descargarán <em>todas</em>.
                                    </div>
                                </div>

                                <div class="col-12 d-flex gap-2">
                                    <button type="button" id="btn-sp-download" class="btn btn-primary">Descargar desde
                                        SharePoint</button>
                                    <button type="button" id="btn-sp-retry"
                                        class="btn btn-outline-secondary d-none">Reintentar</button>
                                </div>

                                <div id="sp-result" class="small mt-2"></div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-4 d-flex justify-content-end">
                        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary me-2">Cancelar</a>
                        <button class="btn btn-primary" type="submit">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script>
        (function() {
            const btn = document.getElementById('btn-sp-download');
            const retry = document.getElementById('btn-sp-retry');
            const input = document.getElementById('sp_path');
            const result = document.getElementById('sp-result');
            const url = @json(route('campaigns.toys.image.fetch', ['campaign' => $campaign->id, 'toy' => $toy->id]));
            const csrf = @json(csrf_token());
            const parts = @json(array_keys($imageMap)); // refs del combo

            function setBusy(state, text = 'Descargando imagen(es) desde SharePoint…') {
                btn.disabled = state;
                retry.classList.toggle('d-none', state);
                result.innerHTML = state ? `<span class="text-muted">${text}</span>` : '';
                if (state) {
                    const count = (input.value.trim() ? input.value.trim().split('+') : parts).filter(Boolean).length ||
                        1;
                    $.blockUI({
                        message: `
                    <div class="p-3 d-flex flex-column align-items-center">
                        <div class="spinner-border" role="status"></div>
                        <div class="mt-2">${text} <br><small>(Archivos: ${count})</small></div>
                    </div>`,
                        css: {
                            border: 'none',
                            padding: '15px',
                            backgroundColor: '#000',
                            opacity: 0.65,
                            color: '#fff',
                            borderRadius: '10px'
                        },
                        baseZ: 3000
                    });
                } else {
                    $.unblockUI();
                }
            }

            function cacheBust(src) {
                const sep = src.includes('?') ? '&' : '?';
                return src + sep + 't=' + Date.now();
            }

            async function doFetch() {
                setBusy(true);
                try {
                    const fd = new FormData();
                    // Si el usuario deja vacío, el backend usará las refs del combo del juguete
                    if (input.value.trim()) fd.append('sp_path', input.value.trim());

                    const r = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: fd
                    });
                    const ct = r.headers.get('content-type') || '';
                    const data = ct.includes('application/json') ? await r.json() : {
                        ok: false,
                        message: await r.text()
                    };

                    if (!r.ok) {
                        result.innerHTML =
                            `<span class="text-danger">Error: ${data.message || 'No se pudo descargar'}</span>`;
                        retry.classList.remove('d-none');
                        return;
                    }

                    // Esperamos { ok, summary: {ok,total,fail}, results: [{ref, ok, image_url, source, message}] }
                    const summary = data.summary || {};
                    const results = Array.isArray(data.results) ? data.results : [];

                    // Actualiza todas las thumbs que tengan image_url
                    results.forEach(it => {
                        if (it.ok && it.image_url) {
                            const el = document.getElementById('toy-thumb-' + it.ref);
                            if (el) el.src = cacheBust(it.image_url);
                        }
                    });

                    const ok = Number(summary.ok || 0),
                        total = Number(summary.total || results.length || 0),
                        fail = Number(summary.fail || (total - ok));
                    if (ok > 0 && fail === 0) {
                        result.innerHTML =
                            `<span class="text-success">Se descargaron ${ok}/${total} imagen(es) correctamente.</span>`;
                        retry.classList.add('d-none');
                    } else if (ok > 0 && fail > 0) {
                        const tried = data.tried ? `<br><small>Intentos: ${data.tried.join(', ')}</small>` : '';
                        result.innerHTML =
                            `<span class="text-warning">Descargadas ${ok}/${total}. Fallaron ${fail}.${tried}</span>`;
                        retry.classList.remove('d-none');
                    } else {
                        const tried = data.tried ? `<br><small>Intentos: ${data.tried.join(', ')}</small>` : '';
                        result.innerHTML =
                            `<span class="text-danger">No se encontró ninguna imagen.${tried}<br>${data.message || ''}</span>`;
                        retry.classList.remove('d-none');
                    }
                } catch (e) {
                    result.innerHTML = `<span class="text-danger">Error de red: ${e?.message || ''}</span>`;
                    retry.classList.remove('d-none');
                } finally {
                    setBusy(false);
                }
            }

            btn.addEventListener('click', doFetch);
            retry.addEventListener('click', doFetch);
        })();
    </script>
@endpush
