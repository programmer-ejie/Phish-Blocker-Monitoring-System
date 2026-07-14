@extends('layouts.admin', ['title' => 'Analytics'])

@section('content')
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
                            <div class="progress-bar" style="width: {{ $point['requests'] }}%"></div>
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
                    @foreach ($riskDistribution as $item)
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ $item['label'] }}</span>
                            <span>{{ $item['count'] }}</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar bg-{{ $item['tone'] === 'danger' ? 'danger' : ($item['tone'] === 'warning' ? 'warning' : ($item['tone'] === 'success' ? 'success' : 'info')) }}" style="width: {{ min(100, $item['count'] / 9) }}%"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white px-4 py-3">
                    <h4 class="mb-0 h5">Operational Observations</h4>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4"><div class="border rounded p-3 h-100"><h5 class="h6">Morning spike</h5><p class="mb-0 small text-secondary">Most scans happen between 8:00 AM and 10:00 AM.</p></div></div>
                        <div class="col-md-4"><div class="border rounded p-3 h-100"><h5 class="h6">Critical clustering</h5><p class="mb-0 small text-secondary">Critical detections center on spoofed academic domains.</p></div></div>
                        <div class="col-md-4"><div class="border rounded p-3 h-100"><h5 class="h6">Queue health</h5><p class="mb-0 small text-secondary">Manual review remains manageable for future backend workflows.</p></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
