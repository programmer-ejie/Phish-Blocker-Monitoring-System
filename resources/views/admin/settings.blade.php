@extends('layouts.admin', ['title' => 'Settings'])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fs-3 mb-1">Settings</h1>
                    <p class="mb-0">Static placeholders for thresholds, notifications, and access rules while the backend is still pending.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @foreach ($settingsGroups as $group)
            <div class="col-lg-4 col-12">
                <div class="card h-100">
                    <div class="card-header bg-white px-4 py-3">
                        <h4 class="mb-0 h5">{{ $group['title'] }}</h4>
                    </div>
                    <div class="card-body p-4">
                        @foreach ($group['items'] as $item)
                            <div class="d-flex justify-content-between {{ !$loop->last ? 'border-bottom pb-3 mb-3' : '' }}">
                                <span>{{ $item['label'] }}</span>
                                <strong>{{ $item['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
