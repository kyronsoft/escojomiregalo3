@extends('layouts.admin.master')

@section('title', 'Detalle Juguete / Combo')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Detalle del registro</h5>
                        <div>
                            {{-- <a href="{{ route('campaign_toys.edit', $toy->id) }}" class="btn btn-primary btn-sm">Editar</a> --}}
                            <a href="{{ route('campaign_toys.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="row g-3">
                                {{-- <div class="col-md-4">
                                    <label class="form-label">ID</label>
                                    <input type="text" class="form-control" value="{{ $toy->id }}" readonly>
                                </div> --}}
                                <div class="col-md-4">
                                    <label class="form-label">Campaña</label>
                                    <input type="text" class="form-control"
                                        value="{{ $toy->campaign->nombre ?? 'ID ' . $toy->idcampaign }}" readonly>
                                </div>
                                {{-- <div class="col-md-4">
                                    <label class="form-label">Combo</label>
                                    <input type="text" class="form-control" value="{{ $toy->combo }}" readonly>
                                </div> --}}

                                <div class="col-md-4">
                                    <label class="form-label">Referencia</label>
                                    <input type="text" class="form-control" value="{{ $toy->referencia }}" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" class="form-control" value="{{ $toy->nombre }}" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Género</label>
                                    <input type="text" class="form-control" value="{{ $toy->genero }}" readonly>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Desde</label>
                                    <input type="text" class="form-control" value="{{ $toy->desde }}" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Hasta</label>
                                    <input type="text" class="form-control" value="{{ $toy->hasta }}" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Unidades</label>
                                    <input type="text" class="form-control" value="{{ $toy->unidades }}" readonly>
                                </div>

                                {{-- <div class="col-md-4">
                                    <label class="form-label">Precio unitario</label>
                                    <input type="text" class="form-control"
                                        value="{{ number_format($toy->precio_unitario) }}" readonly>
                                </div> --}}
                                {{-- <div class="col-md-4">
                                    <label class="form-label">% (texto)</label>
                                    <input type="text" class="form-control" value="{{ $toy->porcentaje }}" readonly>
                                </div> --}}
                                <div class="col-md-4">
                                    <label class="form-label">Seleccionadas</label>
                                    <input type="text" class="form-control" value="{{ $toy->seleccionadas }}" readonly>
                                </div>

                                {{-- <div class="col-md-4">
                                    <label class="form-label">Escogidos</label>
                                    <input type="text" class="form-control" value="{{ $toy->escogidos }}" readonly>
                                </div> --}}
                                {{-- <div class="col-md-4">
                                    <label class="form-label">ID Original</label>
                                    <input type="text" class="form-control" value="{{ $toy->idoriginal }}" readonly>
                                </div> --}}
                                <div class="col-md-12">
                                    <label class="form-label">Descripción</label>
                                    <textarea class="form-control" rows="3" readonly>{{ $toy->descripcion }}</textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Imagen</label><br>
                                    @if ($toy->imagenppal)
                                        <img src="{{ asset('storage/' . $toy->imagenppal) }}" alt="Imagen"
                                            class="img-fluid rounded" style="max-width:300px;">
                                    @else
                                        <em>Sin imagen</em>
                                    @endif
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Actualizado</label>
                                    <input type="text" class="form-control" value="{{ $toy->updated_at }}" readonly>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
