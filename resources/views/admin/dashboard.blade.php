@extends('layouts.admin', ['title' => 'Dashboard'])

@section('content')
    <style>
        .dashboard-chart-card { min-height: 360px; }
        .activity-chart { display: flex; height: 230px; align-items: end; gap: clamp(0.55rem, 2vw, 1.4rem); padding: 1rem 0 0; border-bottom: 1px solid var(--pb-border); }
        .activity-column { display: flex; height: 100%; min-width: 0; flex: 1; flex-direction: column; align-items: center; justify-content: end; gap: 0.6rem; }
        .activity-value { color: var(--pb-muted); font-size: 0.75rem; font-weight: 600; }
        .activity-bar { width: min(44px, 72%); min-height: 8px; border-radius: 0.65rem 0.65rem 0.2rem 0.2rem; background: linear-gradient(180deg, #ff8a65, #ef5b35); box-shadow: 0 8px 18px rgba(239, 91, 53, 0.18); transition: filter 0.2s ease, transform 0.2s ease; }
        .activity-column:hover .activity-bar { filter: saturate(1.15); transform: translateY(-3px); }
        .activity-label { color: var(--pb-muted); font-size: 0.72rem; white-space: nowrap; }
        .status-donut { position: relative; display: grid; width: 178px; height: 178px; margin: 0 auto; place-items: center; border-radius: 50%; }
        .status-donut::after { position: absolute; width: 112px; height: 112px; border-radius: 50%; background: var(--pb-surface); box-shadow: inset 0 0 0 1px var(--pb-border); content: ''; }
        .status-donut__content { position: relative; z-index: 1; text-align: center; }
        .status-legend-dot { width: 10px; height: 10px; flex: 0 0 auto; border-radius: 50%; }
        .campus-health-card { height: 100%; border: 1px solid var(--pb-border); border-radius: 0.9rem; background: var(--pb-surface); transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease; }
        .campus-health-card:hover { border-color: rgba(239, 91, 53, 0.4); box-shadow: 0 12px 28px rgba(16, 24, 40, 0.08); transform: translateY(-2px); }
        .campus-metric { padding: 0.7rem; border-radius: 0.7rem; background: var(--pb-surface-soft); }
        .campus-metric span { display: block; color: var(--pb-muted); font-size: 0.7rem; }
        .health-track { height: 7px; overflow: hidden; border-radius: 999px; background: var(--pb-surface-soft); }
        .health-track > span { display: block; height: 100%; border-radius: inherit; }
        .school-seal { padding: 3px; object-fit: contain; background: #fff; border: 1px solid var(--pb-border); }
    </style>
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fs-3 mb-1">Dashboard</h1>
                    <p class="mb-0">Campus-wide phishing monitoring overview and quick actions.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-3 px-4 py-3">
            <div>
                <h4 class="mb-1 h5">Campus Health Intelligence</h4>
                <p class="mb-0 small text-secondary">Live uptime, endpoint coverage, scan outcomes, and computed health</p>
            </div>
            <a href="{{ route('admin.campuses') }}" class="btn btn-sm btn-outline-secondary">Campus details</a>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                @foreach ($campusBreakdown as $campus)
                    <div class="col-lg-6 col-12">
                        <article class="campus-health-card p-4">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <img src="{{ asset('images/slsu.webp') }}" alt="Southern Leyte State University seal" width="46" height="46" class="rounded-circle school-seal flex-shrink-0">
                                    <div class="min-w-0">
                                        <h5 class="h6 fw-bold mb-1 text-break">{{ $campus['name'] }}</h5>
                                        <span class="small text-secondary">Synced {{ $campus['last_sync'] }}</span>
                                    </div>
                                </div>
                                <span class="badge flex-shrink-0 bg-{{ $campus['tone'] }}-subtle text-{{ $campus['tone'] }} border border-{{ $campus['tone'] }}">{{ $campus['health'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between small mb-2">
                                <span>Health score</span><strong>{{ $campus['health_score'] }}%</strong>
                            </div>
                            <div class="health-track mb-3"><span class="bg-{{ $campus['tone'] }}" style="width: {{ $campus['health_score'] }}%"></span></div>
                            <div class="row g-2">
                                <div class="col-sm-3 col-6"><div class="campus-metric"><strong>{{ $campus['uptime'] }}%</strong><span>Uptime</span></div></div>
                                <div class="col-sm-3 col-6"><div class="campus-metric"><strong>{{ $campus['computers'] }}</strong><span>Endpoints</span></div></div>
                                <div class="col-sm-3 col-6"><div class="campus-metric"><strong class="text-success">{{ $campus['proceed'] }}</strong><span>Proceed</span></div></div>
                                <div class="col-sm-3 col-6"><div class="campus-metric"><strong class="text-danger">{{ $campus['blocked'] }}</strong><span>Blocked</span></div></div>
                            </div>
                            <div class="d-flex justify-content-between mt-3 small text-secondary">
                                <span>{{ $campus['scans'] }} scans</span>
                                <span>{{ $campus['suspicious'] }} suspicious</span>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-3 col-12">
            <div class="card p-4 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-2">
                <div class="d-flex gap-3">
                    <div class="icon-shape icon-md bg-primary text-white rounded-2">
                        <i class="ti ti-world-search fs-4"></i>
                    </div>
                    <div>
                        <h2 class="mb-3 fs-6">Total Scans</h2>
                        <h3 class="fw-bold mb-0">{{ number_format($summary['total_scans']) }}</h3>
                        <p class="text-primary mb-0 small">Across all active campus endpoints</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-12">
            <div class="card p-4 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-2">
                <div class="d-flex gap-3">
                    <div class="icon-shape icon-md bg-danger text-white rounded-2">
                        <i class="ti ti-shield-x fs-4"></i>
                    </div>
                    <div>
                        <h2 class="mb-3 fs-6">Blocked Today</h2>
                        <h3 class="fw-bold mb-0">{{ $summary['blocked_today'] }}</h3>
                        <p class="text-danger mb-0 small">Threats automatically denied</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-12">
            <div class="card p-4 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-2">
                <div class="d-flex gap-3">
                    <div class="icon-shape icon-md bg-warning text-white rounded-2">
                        <i class="ti ti-clock-exclamation fs-4"></i>
                    </div>
                    <div>
                        <h2 class="mb-3 fs-6">Suspicious Detections</h2>
                        <h3 class="fw-bold mb-0">{{ $summary['review_queue'] }}</h3>
                        <p class="text-warning mb-0 small">Cases waiting for validation</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-12">
            <div class="card p-4 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-2">
                <div class="d-flex gap-3">
                    <div class="icon-shape icon-md bg-success text-white rounded-2">
                        <i class="ti ti-shield-check fs-4"></i>
                    </div>
                    <div>
                        <h2 class="mb-3 fs-6">Safe Traffic Rate</h2>
                        <h3 class="fw-bold mb-0">{{ $summary['safe_rate'] }}%</h3>
                        <p class="text-success mb-0 small">Verified safe browsing ratio</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent px-4 py-3">
                    <h3 class="h5 mb-0">Recent Monitoring Logs</h3>
                    <a href="{{ route('admin.logs') }}" class="btn btn-sm btn-outline-secondary">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 text-nowrap table-hover">
                        <thead class="table-light border-light">
                            <tr>
                                <th>Time</th>
                                <th>URL</th>
                                <th>Campus</th>
                                <th>Risk</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($records, 0, 6) as $record)
                                <tr class="align-middle">
                                    <td>{{ $record['time'] }}</td>
                                    <td>{{ $record['url'] }}</td>
                                    <td>{{ $record['campus_name'] }}</td>
                                    <td>
                                        <span class="badge bg-{{ match(strtolower($record['risk_level'])) {
                                            'critical', 'high' => 'danger-subtle text-danger border border-danger',
                                            'medium' => 'warning-subtle text-warning border border-warning',
                                            'low' => 'info-subtle text-info border border-info',
                                            default => 'success-subtle text-success border border-success',
                                        } }}">{{ $record['risk_level'] }}</span>
                                    </td>
                                    <td>{{ $record['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center px-4 py-3">
                    <h4 class="mb-0 h5">Priority Alerts</h4>
                    <a href="{{ route('admin.alerts') }}" class="small text-primary text-decoration-underline">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($alerts as $alert)
                        <li class="list-group-item d-flex align-items-center gap-3">
                            <img src="{{ asset('images/slsu.webp') }}" alt="Southern Leyte State University seal" class="avatar avatar-md rounded-circle school-seal">
                            <div class="flex-grow-1">
                                <p class="mb-1">{{ $alert['title'] }}</p>
                                <small>{{ $alert['campus'] }} • {{ $alert['owner'] }}</small>
                            </div>
                            <span class="badge bg-{{ in_array($alert['severity'], ['Critical','High']) ? 'danger' : 'warning' }}">{{ $alert['severity'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="card dashboard-chart-card">
                <div class="card-header bg-transparent px-4 py-3">
                    <h4 class="mb-1 h5">Scan Activity</h4>
                    <p class="mb-0 small text-secondary">Database-recorded extension checks during the last seven hours</p>
                </div>
                <div class="card-body px-4 pt-2 pb-4">
                    <div class="activity-chart" role="img" aria-label="Hourly scan activity chart">
                        @foreach ($hourlyTraffic as $point)
                            <div class="activity-column" title="{{ $point['requests'] }} scans at {{ $point['hour'] }}">
                                <span class="activity-value">{{ $point['requests'] }}</span>
                                <span class="activity-bar" style="height: {{ $point['percentage'] }}%"></span>
                                <span class="activity-label">{{ $point['hour'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            @php
                $proceedShare = $statusDistribution[0]['percentage'];
                $suspiciousEnd = $proceedShare + $statusDistribution[1]['percentage'];
            @endphp
            <div class="card dashboard-chart-card">
                <div class="card-header bg-transparent px-4 py-3">
                    <h4 class="mb-1 h5">Extension Status Mix</h4>
                    <p class="mb-0 small text-secondary">All monitored URL decisions</p>
                </div>
                <div class="card-body d-flex flex-column justify-content-center px-4 py-3">
                    <div class="status-donut mb-4" style="background: conic-gradient(#16a34a 0 {{ $proceedShare }}%, #f59e0b {{ $proceedShare }}% {{ $suspiciousEnd }}%, #ef4444 {{ $suspiciousEnd }}% 100%);">
                        <div class="status-donut__content">
                            <strong class="d-block fs-3">{{ number_format($summary['total_scans']) }}</strong>
                            <span class="small text-secondary">total scans</span>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        @foreach ($statusDistribution as $status)
                            <div class="d-flex align-items-center gap-2">
                                <span class="status-legend-dot" style="background: {{ $status['color'] }}"></span>
                                <span class="flex-grow-1">{{ $status['label'] }}</span>
                                <strong>{{ $status['count'] }}</strong>
                                <span class="small text-secondary" style="width: 38px; text-align: right">{{ $status['percentage'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
