@extends('layouts.admin', ['title' => 'Campuses'])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fs-3 mb-1">Campus Operations</h1>
                    <p class="mb-0">Frontend view for rollout coverage, sync status, connected devices, and uptime by campus.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @foreach ($campuses as $campus)
            <div class="col-lg-6 col-12">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center px-4 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <img src="{{ asset('images/slsu.webp') }}" alt="Southern Leyte State University seal" width="42" height="42" class="rounded-circle border bg-white p-1">
                            <h4 class="mb-0 h5">{{ $campus['name'] }}</h4>
                        </div>
                        <span class="badge bg-{{ strtolower($campus['status']) === 'healthy' ? 'success' : 'warning' }}">{{ $campus['status'] }}</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
                            <span>Connected computers</span>
                            <strong>{{ $campus['computers'] }}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
                            <span>Last sync</span>
                            <strong>{{ $campus['last_sync'] }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Uptime</span>
                            <strong>{{ $campus['uptime'] }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
