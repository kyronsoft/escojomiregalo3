@extends('layouts.admin.master')

@section('title', 'Seleccionados')

@push('css')
    <style>
        .table thead th {
            white-space: nowrap;
        }

        .filter-group .form-control,
        .filter-group .form-select {
            height: 38px;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Seleccionados</h3>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- Filtros --}}
        <form method="GET" class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end filter-group">
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1">Campaña</label>
                        <select name="idcampaign" class="form-select">
                            <option value="">-- Todas --</option>
                            @foreach ($campaigns as $c)
                                <option value="{{ $c->id }}"
                                    {{ (string) $c->id === (string) $campaignId ? 'selected' : '' }}>
                                    {{ $c->nombre ?? 'Campaña #' . $c->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">Referencia</label>
                        <input type="text" name="referencia" class="form-control" value="{{ $referencia }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">Documento</label>
                        <input type="text" name="documento" class="form-control" value="{{ $documento }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1">Desde</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1">Hasta</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label mb-1">/ pág.</label>
                        <select name="per_page" class="form-select">
                            @foreach ([10, 25, 50, 100, 200] as $pp)
                                <option value="{{ $pp }}" {{ (int) $perPage === $pp ? 'selected' : '' }}>
                                    {{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-12 d-flex gap-2 justify-content-end">
                        <a href="{{ route('seleccionados.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        <a href="{{ route('seleccionados.export', request()->query()) }}" class="btn btn-success">
                            Exportar Excel
                        </a>
                        <button class="btn btn-primary" type="submit">Filtrar</button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Tabla --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 90px;">ID</th>
                                <th>Campaña</th>
                                <th>Referencia</th>
                                <th>Nombre Juguete</th>
                                <th>Documento</th>
                                <th style="width: 180px;">Fecha selección</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    <td>{{ $r->id }}</td>
                                    <td>
                                        {{ $r->campaign_name ?? '#' . $r->idcampaing }}
                                    </td>
                                    <td>{{ $r->referencia }}</td>
                                    <td>{{ $r->toy_name }}</td>
                                    <td>{{ $r->documento }}</td>
                                    <td>{{ \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No hay registros.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-3 d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Mostrando {{ $rows->firstItem() ?? 0 }}-{{ $rows->lastItem() ?? 0 }} de {{ $rows->total() }}
                        registros
                    </div>
                    <div>
                        {{ $rows->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
