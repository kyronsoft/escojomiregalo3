@extends('layouts.admin.master')

@section('title', 'Importar Juguetes/Combos')

@push('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

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

                @if (session('images_summary'))
                    @php $is = session('images_summary'); @endphp
                    <hr>
                    <h6>Descarga de imágenes</h6>
                    <ul class="mb-2">
                        <li>Imágenes descargadas: <strong>{{ $is['ok'] ?? 0 }}</strong></li>
                        <li>Imágenes no encontradas / error: <strong>{{ $is['fail'] ?? 0 }}</strong></li>

                        {{-- Si agregas contadores por toy, puedes mostrarlos así:
    <li>Registros marcados imgexists='S': <strong>{{ $is['toys_marked_S'] ?? 0 }}</strong></li>
    <li>Registros marcados imgexists='N': <strong>{{ $is['toys_marked_N'] ?? 0 }}</strong></li>
    --}}
                    </ul>
                @endif


                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <div class="card">
                    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Importar Juguetes / Combos</h5>
                        <a href="{{ route('campaign_toys.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            El archivo debe incluir columnas: <code>codigo, nombre, descripcion, genero, desde, hasta,
                                unidades, porcentaje, combo</code>.<br>
                            Si <code>codigo</code> contiene <strong>+</strong> (hasta 6 partes), se genera un registro por
                            cada código del combo.
                        </p>

                        <form id="form-import" class="row g-3" method="POST"
                            action="{{ route('campaign_toys.import.run') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="col-12 col-md-6">
                                <label class="form-label">Campaña</label>
                                <select id="idcampaign" name="idcampaign"
                                    class="form-control @error('idcampaign') is-invalid @enderror"></select>
                                @error('idcampaign')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Solo se importará a la campaña seleccionada.</small>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Archivo</label>
                                <input type="file" name="file" accept=".xlsx,.xls,.csv"
                                    class="form-control @error('file') is-invalid @enderror" required>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <a href="{{ route('campaign_toys.index') }}"
                                    class="btn btn-outline-secondary me-2">Cancelar</a>
                                <button class="btn btn-primary" type="submit">Importar</button>
                            </div>
                        </form>

                        @if (session('import_summary'))
                            @php $s = session('import_summary'); @endphp
                            <hr>
                            <h6>Resumen</h6>
                            <ul class="mb-2">
                                <li>Creados: <strong>{{ $s['creados'] }}</strong></li>
                                <li>Actualizados: <strong>{{ $s['actualizados'] }}</strong></li>
                                <li>Omitidos: <strong>{{ $s['omitidos'] }}</strong></li>
                            </ul>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(function() {
            const CSRF = '{{ csrf_token() }}';
            const importUrl = '{{ route('campaign_toys.import.async') }}';
            const progUrl = '{{ route('campaign_toys.import.progress', ['jobId' => '___ID___']) }}';
            const apiCamp = '{{ route('api.campaigns') }}';

            const $form = $('#form-import');
            const $camp = $('#idcampaign');

            // ---------- Util: formatear segundos a "1h 5m 03s" ----------
            function fmtETA(totalSeconds) {
                if (!isFinite(totalSeconds) || totalSeconds <= 0) return '—';
                let s = Math.round(totalSeconds);
                const h = Math.floor(s / 3600);
                s -= h * 3600;
                const m = Math.floor(s / 60);
                s -= m * 60;
                const pad = n => String(n).padStart(2, '0');
                if (h > 0) return `${h}h ${pad(m)}m ${pad(s)}s`;
                if (m > 0) return `${m}m ${pad(s)}s`;
                return `${s}s`;
            }

            // ---------- Select2 campañas (igual que antes) ----------
            $camp.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Seleccione campaña (activas)',
                allowClear: true,
                ajax: {
                    transport: function(params, success, failure) {
                        const req = $.ajax({
                            type: 'GET',
                            url: params.url,
                            data: params.data,
                            headers: {
                                'Accept': 'application/json'
                            },
                            cache: true
                        });
                        req.then(success);
                        req.fail(function(xhr) {
                            console.error('Error Select2 campañas:', xhr.status, xhr
                                .responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'No se pudo cargar la lista de campañas.'
                            });
                            failure(xhr);
                        });
                        return req;
                    },
                    url: apiCamp,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1,
                            per_page: 20,
                            only_active: 1
                        };
                    },
                    processResults: function(data) {
                        const results = Array.isArray(data?.results) ? data.results : [];
                        const more = !!data?.pagination?.more;
                        return {
                            results,
                            pagination: {
                                more
                            }
                        };
                    }
                }
            });

            @if (old('idcampaign'))
                (function() {
                    const oldId = @json(old('idcampaign'));
                    const oldText = @json(old('idcampaign_text', null));
                    if (oldId) {
                        const opt = new Option(oldText || ('Campaña #' + oldId), oldId, true, true);
                        $camp.append(opt).trigger('change');
                    }
                })();
            @endif

            // ---------- BlockUI ----------
            function blockWithProgress(msg = 'En cola…', percent = 0) {
                $.blockUI({
                    message: `
        <div style="width:320px;max-width:92vw;">
          <div class="text-center mb-2">
            <div class="spinner-border" role="status" style="width:1.75rem;height:1.75rem;"></div>
          </div>
          <div class="fw-bold text-center mb-2" id="blk-msg">${msg}</div>
          <div class="text-center small" id="blk-eta">ETA: —</div>
          <div class="progress" style="height:14px;">
            <div id="blk-bar" class="progress-bar" role="progressbar" style="width:${percent}%;">
              ${Math.round(percent)}%
            </div>
          </div>
        </div>`,
                    css: {
                        border: 'none',
                        padding: '15px',
                        backgroundColor: '#000',
                        opacity: 0.75,
                        color: '#fff',
                        borderRadius: '10px'
                    },
                    baseZ: 3000
                });
            }

            function updateProgress(msg, p = null) {
                if (msg) $('#blk-msg').text(msg);
                if (p !== null) {
                    const v = Math.max(0, Math.min(100, Math.round(p)));
                    $('#blk-bar').css('width', v + '%').text(v + '%');
                }
            }

            function setETA(seconds) {
                $('#blk-eta').text('ETA: ' + fmtETA(seconds));
            }

            function unblock() {
                $.unblockUI();
            }

            // ---------- Polling + ETA ----------
            let pollTimer = null;
            let importStartTs = null; // timestamp cuando arrancó el procesamiento
            let knownTotal = null; // total de registros (si el backend lo expone)
            const SECONDS_PER_RECORD = 3; // regla pedida

            function pick(n, ...alts) {
                // toma el primer número válido
                const pool = [n, ...alts];
                for (const v of pool) {
                    const num = Number(v);
                    if (isFinite(num) && num >= 0) return num;
                }
                return null;
            }

            function showETAFromState(state) {
                const t = state && state.timing ? state.timing : {};
                if (t.eta_human) {
                    $('#blk-eta').text('ETA: ' + t.eta_human);
                    return true;
                }
                if (t.eta_seconds != null) {
                    $('#blk-eta').text('ETA: ' + fmtETA(Number(t.eta_seconds)));
                    return true;
                }
                return false;
            }

            function startPolling(jobId) {
                const url = progUrl.replace('___ID___', jobId);
                pollTimer = setInterval(function() {
                    $.ajax({
                        url,
                        method: 'GET',
                        dataType: 'json',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }).done(function(state) {
                        updateProgress(state.message || '', state.percent ?? null);

                        const m = state?.meta || {};
                        const total = [m.total_records, m.total, m.rows_total, state?.total_records,
                                state?.total
                            ]
                            .map(Number).find(v => Number.isFinite(v) && v >= 0);
                        const done = [m.processed_records, m.done, m.rows_done, state
                                ?.processed_records, state?.done
                            ]
                            .map(Number).find(v => Number.isFinite(v) && v >= 0);

                        if (Number.isFinite(total)) knownTotal = total;

                        if (Number.isFinite(knownTotal) && Number.isFinite(done)) {
                            const remaining = Math.max(knownTotal - done, 0);
                            setETA(remaining * SECONDS_PER_RECORD);
                            if (!importStartTs && done > 0) importStartTs = Date.now();
                        } else {
                            // Fallback con percent…
                            const p = Number(state.percent);
                            if (isFinite(p) && p > 0) {
                                if (!importStartTs) importStartTs = Date.now();
                                const elapsed = (Date.now() - importStartTs) / 1000;
                                const procP = Math.max(0, Math.min(100, p)) -
                                40; // 40–100% = procesamiento
                                if (procP > 0) {
                                    const remainingRatio = (100 - (40 + procP)) / procP;
                                    setETA(elapsed * remainingRatio);
                                } else {
                                    setETA(null);
                                }
                            } else {
                                setETA(null);
                            }
                        }

                        if (state.status === 'success' || state.status === 'error') {
                            clearInterval(pollTimer);
                            unblock();

                            // ⬇⬇⬇ CLAVES BILINGÜES (ES/EN) ⬇⬇⬇
                            const sum = state?.counts?.import || {};
                            const img = state?.counts?.images || {};

                            const created = sum.creados ?? sum.created ?? 0;
                            const updated = sum.actualizados ?? sum.updated ?? 0;
                            const skipped = sum.omitidos ?? sum.skipped ?? 0;

                            const imgOk = img.ok ?? img.downloaded ?? 0;
                            const imgFail = img.fail ?? img.errors ?? 0;
                            const imgS = img.toys_marked_S;
                            const imgN = img.toys_marked_N;
                            // ⬆⬆⬆ CLAVES BILINGÜES (ES/EN) ⬆⬆⬆

                            const icon = state.status === 'success' ? 'success' : 'error';
                            const ttl = state.status === 'success' ? 'Proceso finalizado' :
                                'Proceso con errores';

                            const html = `
            <div class="text-start">
              <p><strong>${state.message || ttl}</strong></p>
              <ul>
                <li>Importación — Creados: <b>${created}</b></li>
                <li>Importación — Actualizados: <b>${updated}</b></li>
                <li>Importación — Omitidos: <b>${skipped}</b></li>
              </ul>
              <ul>
                <li>Imágenes — Descargadas (ok): <b>${imgOk}</b></li>
                <li>Imágenes — No encontradas / error (fail): <b>${imgFail}</b></li>
                ${imgS !== undefined ? `<li>Registros imgexists='S': <b>${imgS}</b></li>` : ''}
                ${imgN !== undefined ? `<li>Registros imgexists='N': <b>${imgN}</b></li>` : ''}
              </ul>
            </div>`;

                            Swal.fire({
                                icon,
                                title: ttl,
                                html
                            });
                        }
                    }).fail(function() {
                        clearInterval(pollTimer);
                        unblock();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar el progreso.'
                        });
                    });
                }, 1200);
            }

            // ---------- Submit AJAX ----------
            $form.on('submit', function(e) {
                e.preventDefault();
                if (!$camp.val()) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Falta campaña',
                        text: 'Selecciona una campaña activa antes de importar.'
                    });
                    return;
                }

                const fd = new FormData(this);
                blockWithProgress('Subiendo archivo…', 5);
                importStartTs = null;
                knownTotal = null;

                $.ajax({
                    url: importUrl,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    },
                    data: fd,
                    contentType: false,
                    processData: false,
                    xhr: function() {
                        const xhr = $.ajaxSettings.xhr();
                        if (xhr.upload) {
                            xhr.upload.addEventListener('progress', function(ev) {
                                if (ev.lengthComputable) {
                                    updateProgress('Subiendo archivo…', (ev.loaded / ev
                                        .total) * 40); // 0→40%
                                }
                            });
                        }
                        return xhr;
                    }
                }).done(function(res) {
                    updateProgress('En cola…', 40);
                    // Si el backend ya conoce cuántas filas tiene el archivo al encolarlo, puede devolverlo aquí:
                    // res.total_records, res.meta.total_records, etc.
                    const tr = Number(res?.meta?.total_records ?? res?.total_records);
                    if (isFinite(tr) && tr > 0) {
                        knownTotal = tr;
                        // Muestra ETA inicial (asumiendo que aún no hay procesados)
                        setETA(tr * SECONDS_PER_RECORD);
                    }
                    startPolling(res.job_id);
                }).fail(function(xhr) {
                    unblock();
                    let msg = 'Error en la importación.';
                    try {
                        msg = JSON.parse(xhr.responseText).message || msg;
                    } catch (e) {}
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: msg
                    });
                });
            });
        });
    </script>
@endpush
