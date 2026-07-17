@extends('layouts.admin', ['title' => 'Settings'])

@section('content')
    <style>
        .settings-group-card { overflow: hidden; border: 1px solid var(--pb-border); }
        .settings-group-icon { display: inline-grid; width: 2.45rem; height: 2.45rem; place-items: center; border-radius: .75rem; color: #ef5b35; background: rgba(239, 91, 53, .1); font-size: 1.2rem; }
        .settings-item { padding: 1rem 0; }
        .settings-item:first-child { padding-top: 0; }
        .settings-item:last-child { padding-bottom: 0; }
        .settings-label { display: block; margin-bottom: .35rem; color: var(--pb-muted); font-size: .82rem; font-weight: 500; }
        .settings-value { display: block; color: var(--pb-text); font-weight: 650; line-height: 1.45; overflow-wrap: anywhere; }
    </style>

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fs-3 mb-1">Settings</h1>
                    <p class="mb-0 text-secondary">System configuration loaded from the monitoring database.</p>
                </div>
                <span class="badge bg-transparent border text-dark">{{ collect($settingsGroups)->sum(fn ($group) => count($group['items'])) }} settings</span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @foreach ($settingsGroups as $group)
            <div class="col-lg-4 col-12">
                @php($groupIcon = match($group['title']) {
                    'Detection Rules' => 'ti-shield-check',
                    'Notifications' => 'ti-bell-ringing',
                    default => 'ti-lock-access',
                })
                <div class="card settings-group-card h-100">
                    <div class="card-header d-flex align-items-center gap-3 bg-transparent px-4 py-3">
                        <span class="settings-group-icon"><i class="ti {{ $groupIcon }}"></i></span>
                        <div>
                            <h4 class="mb-0 h5">{{ $group['title'] }}</h4>
                            <span class="small text-secondary">{{ count($group['items']) }} configured items</span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        @foreach ($group['items'] as $item)
                            <div class="settings-item {{ !$loop->last ? 'border-bottom' : '' }}">
                                <span class="settings-label">{{ $item['label'] }}</span>
                                <span class="settings-value">{{ $item['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
