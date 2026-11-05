@extends('layouts.admin', ['title' => 'Alerts'])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fs-3 mb-1">Alerts</h1>
                    <p class="mb-0 text-secondary">Live alert records from the monitoring database.</p>
                </div>
                <span class="badge bg-transparent border text-dark">{{ $alerts->total() }} total</span>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    <div class="d-flex flex-wrap gap-2 mb-4">
        <form method="POST" action="{{ route('admin.alerts.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary" @disabled($unreadAlertCount === 0)>Mark all as read ({{ $unreadAlertCount }})</button>
        </form>
        <form method="POST" action="{{ route('admin.alerts.delete-all') }}" onsubmit="return confirm('Delete all alerts visible to your account? This cannot be undone. Phishing logs will be kept.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger" @disabled($alerts->total() === 0)>Delete all alerts</button>
        </form>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card h-100"><div class="card-body p-3 d-flex align-items-center gap-3"><span class="rounded-circle bg-primary-subtle text-primary p-2"><i class="ti ti-bell fs-4"></i></span><div><span class="small text-secondary d-block">Total alerts</span><strong class="fs-4">{{ $alertSummary['total'] }}</strong></div></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100"><div class="card-body p-3 d-flex align-items-center gap-3"><span class="rounded-circle bg-danger-subtle text-danger p-2"><i class="ti ti-alert-triangle fs-4"></i></span><div><span class="small text-secondary d-block">High priority</span><strong class="fs-4">{{ $alertSummary['priority'] }}</strong></div></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100"><div class="card-body p-3 d-flex align-items-center gap-3"><span class="rounded-circle bg-warning-subtle text-warning p-2"><i class="ti ti-search fs-4"></i></span><div><span class="small text-secondary d-block">Active investigations</span><strong class="fs-4">{{ $alertSummary['active'] }}</strong></div></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100"><div class="card-body p-3 d-flex align-items-center gap-3"><span class="rounded-circle bg-success-subtle text-success p-2"><i class="ti ti-building-community fs-4"></i></span><div><span class="small text-secondary d-block">Campuses affected</span><strong class="fs-4">{{ $alertSummary['campuses'] }}</strong></div></div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="table-responsive">
                    <table class="table mb-0 text-nowrap table-hover align-middle">
                        <thead class="table-light border-light">
                            <tr>
                                <th>Alert</th>
                                <th>Campus</th>
                                <th>Severity</th>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Read status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($alerts as $alert)
                                @php($severityTone = in_array($alert['severity'], ['Critical', 'High']) ? 'danger' : ($alert['severity'] === 'Medium' ? 'warning' : 'info'))
                                <tr>
                                    <td class="fw-medium">{{ $alert['title'] }}</td>
                                    <td>{{ $alert['campus'] }}</td>
                                    <td><span class="badge text-bg-{{ $severityTone }}">{{ $alert['severity'] }}</span></td>
                                    <td>{{ $alert['owner'] }}</td>
                                    <td><span class="small">{{ $alert['status'] }}</span></td>
                                    <td>
                                        @if ($alert['read'])
                                            <span class="small text-secondary">Read</span>
                                        @else
                                            <form method="POST" action="{{ route('admin.alerts.read', $alert['id']) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Mark as read</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-5 text-center text-secondary">No alerts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 bg-transparent px-4 py-3">
                    <p class="mb-0 small text-secondary">Showing {{ $alerts->firstItem() ?? 0 }}–{{ $alerts->lastItem() ?? 0 }} of {{ $alerts->total() }} alerts</p>
                    {{ $alerts->onEachSide(1)->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
