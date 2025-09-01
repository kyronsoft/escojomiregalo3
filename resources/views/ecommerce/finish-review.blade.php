@extends('ecommerce.main')

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
                                <div class="col-md-4">
                                    <label class="form-label">Ciudad</label>
                                    <input type="text" name="ciudad" value="{{ old('ciudad', $colaborador->ciudad) }}"
                                        class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Barrio</label>
                                    <input type="text" name="barrio" value="{{ old('barrio', $colaborador->barrio) }}"
                                        class="form-control">
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('modalUpdateData');
            if (el && typeof bootstrap !== 'undefined') {
                new bootstrap.Modal(el).show();
            }
        });
    </script>
@endpush
