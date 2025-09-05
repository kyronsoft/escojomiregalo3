@extends('layouts.admin.master')

@section('title', 'Dashboard')

@push('css')
    <style>
        .kpi-card {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
        }

        .kpi-card .val {
            font-size: 1.25rem;
            font-weight: 700;
        }

        /* contenedor genérico */
        .chart-wrap {
            position: relative;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 16px;
        }

        /* SOLO el Top 10: altura fija */
        #wrapTop10.chart-wrap {
            height: 380px;
        }

        /* ajústalo a tu gusto */
        /* Si quieres también fijar el otro: #wrapProgress.chart-wrap { height: 360px; } */

        /* el canvas rellena el contenedor (que ya tiene altura fija) */
        .chart-wrap .chart-canvas {
            display: block;
            width: 100% !important;
            height: 100% !important;
            /* ahora SÍ es seguro usar 100% */
        }

        /* Si quieres ajustar por breakpoint: */
        @media (min-width: 992px) {
            .chart-wrap {
                min-height: 360px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid py-3">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Dashboard</h3>
        </div>

        {{-- KPIs simples (opcionales): puedes ajustar a tus necesidades --}}
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <div>
                        <div class="small text-muted">Top 10 juguetes</div>
                        <div class="val">{{ count($topLabels) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <div>
                        <div class="small text-muted">Campañas monitoreadas</div>
                        <div class="val">{{ count($campLabels) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <div>
                        <div class="small text-muted">% Promedio avance</div>
                        <div class="val">
                            @php
                                $avg = count($campPercent)
                                    ? round(array_sum($campPercent) / count($campPercent), 1)
                                    : 0;
                            @endphp
                            {{ $avg }}%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top 10 juguetes --}}
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="chart-wrap">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Top 10 juguetes seleccionados</h5>
                    </div>
                    <div id="wrapTop10" class="chart-wrap">
                        <canvas id="chartTopToys" class="chart-canvas"></canvas>
                    </div>
                </div>
            </div>

            {{-- Avance por campaña (apilado: seleccionados vs pendientes) --}}
            <div class="col-12 col-lg-6">
                <div class="chart-wrap">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Avance de campañas (seleccionados vs pendientes)</h5>
                    </div>
                    <div id="wrapProgress" class="chart-wrap">
                        <canvas id="chartProgress" class="chart-canvas"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabla auxiliar con % por campaña (opcional) --}}
        <div class="row g-3 mt-3">
            <div class="col-12">
                <div class="chart-wrap">
                    <h6 class="mb-3">Porcentaje por campaña</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Campaña</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($campLabels as $i => $label)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td class="text-end">{{ $campPercent[$i] ?? 0 }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">Sin datos</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        // Datos inyectados desde el controlador
        const topLabels = @json($topLabels, JSON_UNESCAPED_UNICODE);
        const topCounts = @json($topCounts);
        const campLabels = @json($campLabels, JSON_UNESCAPED_UNICODE);
        const campSelected = @json($campSelected);
        const campPending = @json($campPending);

        // Chart: Top 10 juguetes
        const ctxTop = document.getElementById('chartTopToys').getContext('2d');
        const chartTop = new Chart(ctxTop, {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [{
                    label: 'Selecciones',
                    data: topCounts,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // rellena #wrapTop10 (380px de alto)
                scales: {
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 0,
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                }
            }
        });

        // Chart: Avance campañas (apilado)
        const ctxProg = document.getElementById('chartProgress').getContext('2d');
        const chartProg = new Chart(ctxProg, {
            type: 'bar',
            data: {
                labels: campLabels,
                datasets: [{
                        label: 'Seleccionados',
                        data: campSelected,
                        stack: 'total'
                    },
                    {
                        label: 'Pendientes',
                        data: campPending,
                        stack: 'total'
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    },
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        ticks: {
                            autoSkip: true,
                            maxRotation: 45,
                            maxTicksLimit: 12
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        precision: 0
                    }
                }
            }
        });
    </script>
@endpush
