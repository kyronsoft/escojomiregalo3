@extends('ecommerce.main')

@push('css')
    {{-- Incluye esto solo si tu layout NO trae select2.css --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
@endpush

@section('content')
    <div class="container py-4">
        <div class="alert alert-info">
            Antes de finalizar, por favor verifica y actualiza tus datos de contacto.
        </div>

        {{-- Modal --}}
        <div class="modal fade" id="modalUpdateData" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
            data-bs-keyboard="false">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Actualiza tus datos</h5>
                    </div>

                    <form method="POST" action="{{ route('ecommerce.cart.finish.update') }}">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Dirección</label>
                                    <input type="text" name="direccion"
                                           value="{{ old('direccion', $colaborador->direccion) }}" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" name="telefono"
                                           value="{{ old('telefono', $colaborador->telefono) }}" class="form-control">
                                </div>

                                {{-- Ciudad con Select2 (valor = codigo, texto = nombre) --}}
                                <div class="col-md-4">
                                    <label class="col-form-label" for="ciudad">Ciudad</label>
                                        <select id="ciudad" name="ciudad"
                                            class="form-control @error('ciudad') is-invalid @enderror"></select>
                                        @error('ciudad')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    <small class="text-muted">Empieza a escribir para buscar (mín. 2 letras)</small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Barrio</label>
                                    <input type="text" name="barrio"
                                           value="{{ old('barrio', $colaborador->barrio) }}" class="form-control">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Observaciones</label>
                                    <textarea name="observaciones" rows="3" class="form-control">{{ old('observaciones', $colaborador->observaciones) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-primary" type="submit">Guardar y finalizar</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Carga jQuery/Select2 SOLO si el layout NO los trae ya.
         Si tu layout ecommerce.main ya incluye jQuery, quita esta línea de jQuery. --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    {{-- Select2 --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('modalUpdateData');

            if (!modalEl) return;

            // Abre el modal
            if (typeof bootstrap !== 'undefined') {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }

            // Inicializa Select2 SOLO cuando el modal ya está visible
            modalEl.addEventListener('shown.bs.modal', function () {
                // Usa la instancia global de jQuery para evitar conflictos
                const $ = window.jQuery || window.$;
                if (!$) {
                    console.error('jQuery no está disponible. Verifica que el layout no quite jQuery.');
                    return;
                }
                if (typeof $.fn.select2 === 'undefined') {
                    console.error('Select2 no está disponible. Revisa el <script> de Select2.');
                    return;
                }

                const $el = $('#ciudad');

                if (!$el.length) return;

                // Evita doble init
                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }

                $el.select2({
                    placeholder: 'Seleccione ciudad',
                    allowClear: true,
                    width: '100%',
                    // CLAVE: fija el contenedor del dropdown al modal
                    dropdownParent: $('#modalUpdateData'),
                    ajax: {
                        url: '{{ route('api.ciudades') }}',
                        dataType: 'json',
                        delay: 250,
                        data: params => ({
                            q: params.term || '',
                            page: params.page || 1,
                            per_page: 20,
                        }),
                        processResults: (data, params) => {
                            params.page = params.page || 1;
                            return {
                                results: data.results || [],
                                pagination: { more: !!(data.pagination && data.pagination.more) }
                            };
                        },
                        cache: true
                    }
                });

                // Preselección si vuelves con old()
                @if (old('ciudad'))
                    const preId = @json(old('ciudad'));
                    const preText = @json(old('ciudad_text', null));
                    if (preId) {
                        const opt = new Option(preText || ('Ciudad ' + preId), preId, true, true);
                        $el.append(opt).trigger('change');
                    }
                @endif
            }, { once: true }); // solo una vez
        });
    </script>
@endpush

