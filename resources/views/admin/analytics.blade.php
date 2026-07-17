@extends('layouts.admin', ['title' => 'Analytics'])

@section('content')
    <style>
        .analytics-chart-bar { min-width: 4px; border-radius: 999px; transition: width .2s ease; }
        .campus-health-card, .campus-outcome-card { border: 1px solid var(--pb-border); border-radius: .9rem; background: var(--pb-surface-soft); }
        .campus-health-card .progress { height: .55rem; background: color-mix(in srgb, var(--pb-text) 9%, transparent); }
        .campus-outcome-donut { display: grid; width: 5.4rem; height: 5.4rem; place-items: center; border-radius: 50%; background: conic-gradient(#16a34a 0 var(--proceed), #f59e0b var(--proceed) calc(var(--proceed) + var(--suspicious)), #ef4444 calc(var(--proceed) + var(--suspicious)) 100%); }
        .campus-outcome-donut.is-empty { background: color-mix(in srgb, var(--pb-text) 12%, transparent); }
        .campus-outcome-donut__center { display: grid; width: 3.9rem; height: 3.9rem; place-items: center; border-radius: 50%; color: var(--pb-text); background: var(--pb-surface-soft); font-size: .78rem; font-weight: 700; line-height: 1.15; text-align: center; }
        .analytics-key { width: .6rem; height: .6rem; border-radius: 50%; display: inline-block; }
        .campus-mix-proceed { background: #16a34a; }
        .campus-mix-suspicious { background: #f59e0b; }
        .campus-mix-block { background: #ef4444; }
    </style>

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fs-3 mb-1">Analytics</h1>
                    <p class="mb-0">Traffic patterns, risk distribution, and response metrics for the phishing monitoring system.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6 col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent px-4 py-3">
                    <h3 class="h5 mb-0">Hourly Traffic</h3>
                    <select class="form-select form-select-sm" style="max-width: 160px;">
                        <option selected>Today</option>
                    </select>
                </div>
                <div class="card-body p-4">
                    @foreach ($hourlyTraffic as $point)
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ $point['hour'] }}</span>
                            <span>{{ $point['requests'] }} requests</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar analytics-chart-bar" style="width: {{ $point['percentage'] }}%"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent px-4 py-3">
                    <h3 class="h5 mb-0">Risk Distribution</h3>
                    <select class="form-select form-select-sm" style="max-width: 160px;">
                        <option selected>Live Mix</option>
                    </select>
                </div>
                <div class="card-body p-4">
                    @php($largestRiskCount = max(1, collect($riskDistribution)->max('count')))
                    @foreach ($riskDistribution as $item)
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ $item['label'] }}</span>
                            <span>{{ $item['count'] }}</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar analytics-chart-bar bg-{{ $item['tone'] === 'danger' ? 'danger' : ($item['tone'] === 'warning' ? 'warning' : ($item['tone'] === 'success' ? 'success' : 'info')) }}" style="width: {{ (int) round(($item['count'] / $largestRiskCount) * 100) }}%"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 bg-transparent px-4 py-3">
                    <div>
                        <h4 class="mb-1 h5">Campus Detection Mix</h4>
                        <p class="mb-0 small text-secondary">Extension outcomes by campus, calculated from all recorded monitoring logs.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-3 small text-secondary">
                        <span><i class="analytics-key campus-mix-proceed me-1"></i>Proceed</span>
                        <span><i class="analytics-key campus-mix-suspicious me-1"></i>Suspicious</span>
                        <span><i class="analytics-key campus-mix-block me-1"></i>Block</span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @forelse ($campusAnalytics as $campus)
                            <div class="col-lg-6 col-12">
                                <div class="campus-outcome-card h-100 p-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="campus-outcome-donut {{ $campus['scans'] === 0 ? 'is-empty' : '' }}" style="--proceed: {{ $campus['proceed_percentage'] }}%; --suspicious: {{ $campus['suspicious_percentage'] }}%;" role="img" aria-label="{{ $campus['name'] }}: {{ $campus['proceed'] }} proceed, {{ $campus['suspicious'] }} suspicious, {{ $campus['blocked'] }} blocked">
                                            <span class="campus-outcome-donut__center">{{ $campus['scans'] }}<small>scans</small></span>
                                        </div>
                                        <div class="min-w-0">
                                            <strong class="d-block mb-2 text-nowrap">{{ $campus['name'] }}</strong>
                                            <div class="small text-success mb-1">{{ $campus['proceed'] }} Proceed</div>
                                            <div class="small text-warning mb-1">{{ $campus['suspicious'] }} Suspicious</div>
                                            <div class="small text-danger">{{ $campus['blocked'] }} Blocked</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="mb-0 text-secondary">No campus monitoring data is available yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-transparent px-4 py-3">
                    <h4 class="mb-1 h5">Campus Health &amp; Threat Exposure</h4>
                    <p class="mb-0 small text-secondary">Health combines reported endpoint uptime with the campus detection rate.</p>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @foreach ($campusAnalytics as $campus)
                            <div class="col-xl col-lg-4 col-md-6">
                                <div class="campus-health-card h-100 p-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                        <strong>{{ $campus['name'] }}</strong>
                                        <span class="badge text-bg-{{ $campus['tone'] }}">{{ $campus['health'] }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between small mb-1"><span>Health score</span><strong>{{ $campus['health_score'] }}%</strong></div>
                                    <div class="progress mb-3"><div class="progress-bar bg-{{ $campus['tone'] }}" style="width: {{ $campus['health_score'] }}%"></div></div>
                                    <div class="d-flex justify-content-between small text-secondary"><span>Threat exposure</span><strong class="text-{{ $campus['threat_rate'] >= 25 ? 'danger' : ($campus['threat_rate'] > 0 ? 'warning' : 'success') }}">{{ $campus['threat_rate'] }}%</strong></div>
                                    <div class="small text-secondary mt-2">{{ $campus['computers'] }} endpoints · {{ $campus['uptime'] }}% uptime</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
