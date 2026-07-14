@extends('layouts.admin', ['title' => 'Alerts'])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fs-3 mb-1">Alerts & Incidents</h1>
                    <p class="mb-0">Static response queue for critical detections, ownership, and escalation flow design.</p>
                </div>
                <span class="badge bg-danger">Priority Monitoring</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card table-responsive">
                <table class="table mb-0 text-nowrap table-hover">
                    <thead class="table-light border-light">
                        <tr>
                            <th>Alert</th>
                            <th>Campus</th>
                            <th>Severity</th>
                            <th>Owner</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($alerts as $alert)
                            <tr class="align-middle">
                                <td>{{ $alert['title'] }}</td>
                                <td>{{ $alert['campus'] }}</td>
                                <td><span class="badge bg-{{ in_array($alert['severity'], ['Critical','High']) ? 'danger' : 'warning' }}">{{ $alert['severity'] }}</span></td>
                                <td>{{ $alert['owner'] }}</td>
                                <td>{{ $alert['status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
