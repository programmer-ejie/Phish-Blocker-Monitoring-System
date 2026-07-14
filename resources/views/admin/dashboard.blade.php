@extends('layouts.admin', ['title' => 'Dashboard'])

@section('content')
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
                        <h2 class="mb-3 fs-6">Review Queue</h2>
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
        <div class="col-lg-7 col-12">
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
                                    <td>{{ $record['action'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5 col-12">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center px-4 py-3">
                    <h4 class="mb-0 h5">Priority Alerts</h4>
                    <a href="{{ route('admin.alerts') }}" class="small text-primary text-decoration-underline">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($alerts as $alert)
                        <li class="list-group-item d-flex align-items-center gap-3">
                            <img src="{{ asset('template/dist/assets/images/avatar-'.(($loop->index % 3) + 1).'.jpg') }}" class="avatar avatar-md rounded-circle">
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

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center px-4 py-3">
                    <h4 class="mb-0 h5">Campus Health</h4>
                </div>
                <div class="card-body p-4">
                    @foreach ($campusBreakdown as $campus)
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>{{ $campus['name'] }}</strong>
                                <small>{{ $campus['scans'] }} scans / {{ $campus['blocked'] }} blocked</small>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $campus['ratio'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center px-4 py-3">
                    <h4 class="mb-0 h5">Recommended Admin Areas</h4>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6"><div class="border rounded p-3 h-100"><h5 class="h6">Logs</h5><p class="mb-0 small text-secondary">Audit trails and incident review.</p></div></div>
                        <div class="col-sm-6"><div class="border rounded p-3 h-100"><h5 class="h6">Analytics</h5><p class="mb-0 small text-secondary">Trends, spikes, and risk distribution.</p></div></div>
                        <div class="col-sm-6"><div class="border rounded p-3 h-100"><h5 class="h6">Alerts</h5><p class="mb-0 small text-secondary">Prioritized investigations and response tracking.</p></div></div>
                        <div class="col-sm-6"><div class="border rounded p-3 h-100"><h5 class="h6">Campuses</h5><p class="mb-0 small text-secondary">Endpoint coverage and uptime visibility.</p></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
