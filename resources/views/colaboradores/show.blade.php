@extends('layouts.admin.master')

@section('title', 'Detalle colaborador')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header pb-0">
                <h5>Colaborador: {{ $colaborador->nombre }}</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Documento</dt>
                    <dd class="col-sm-9">{{ $colaborador->documento }}</dd>
                    <dt class="col-sm-3">Nombre</dt>
                    <dd class="col-sm-9">{{ $colaborador->nombre }}</dd>
                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9">{{ $colaborador->email }}</dd>
                    <dt class="col-sm-3">Teléfono</dt>
                    <dd class="col-sm-9">{{ $colaborador->telefono }}</dd>
                    <dt class="col-sm-3">Dirección</dt>
                    <dd class="col-sm-9">{{ $colaborador->direccion }}</dd>
                    <dt class="col-sm-3">Barrio</dt>
                    <dd class="col-sm-9">{{ $colaborador->barrio }}</dd>
                    <dt class="col-sm-3">Ciudad</dt>
                    <dd class="col-sm-9">{{ $colaborador->ciudad }}</dd>
                    <dt class="col-sm-3">NIT</dt>
                    <dd class="col-sm-9">{{ $colaborador->nit }}</dd>
                    <dt class="col-sm-3">Observaciones</dt>
                    <dd class="col-sm-9">{{ $colaborador->observaciones }}</dd>
                    <dt class="col-sm-3">Enviado</dt>
                    <dd class="col-sm-9">{{ $colaborador->enviado ? 'Sí' : 'No' }}</dd>
                    <dt class="col-sm-3">Política datos</dt>
                    <dd class="col-sm-9">{{ $colaborador->politicadatos }}</dd>
                    <dt class="col-sm-3">Update datos</dt>
                    <dd class="col-sm-9">{{ $colaborador->updatedatos }}</dd>
                    <dt class="col-sm-3">Welcome</dt>
                    <dd class="col-sm-9">{{ $colaborador->welcome }}</dd>
                    <dt class="col-sm-3">Sucursal</dt>
                    <dd class="col-sm-9">{{ $colaborador->sucursal }}</dd>
                    <dt class="col-sm-3">Actualizado</dt>
                    <dd class="col-sm-9">{{ optional($colaborador->updated_at)->format('Y-m-d H:i') }}</dd>
                </dl>
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('colaboradores.edit', $colaborador) }}" class="btn btn-primary">Editar</a>
                    <a href="{{ route('colaboradores.index') }}" class="btn btn-outline-secondary">Volver</a>
                </div>
            </div>
        </div>
    </div>
@endsection
