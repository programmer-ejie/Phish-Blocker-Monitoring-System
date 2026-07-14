@extends('layouts.admin', ['title' => 'Logs'])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fs-3 mb-1">Monitoring Logs</h1>
                    <p class="mb-0">Dummy table data using your sample columns before backend integration.</p>
                </div>
                <button class="btn btn-primary">Export UI Sample</button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card table-responsive">
                <table class="table mb-0 text-nowrap table-hover">
                    <thead class="table-light border-light">
                        <tr>
                            <th>Time</th>
                            <th>URL</th>
                            <th>Score</th>
                            <th>Risk Level</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Computer #</th>
                            <th>Campus</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr class="align-middle">
                                <td>{{ $record['time'] }}</td>
                                <td>{{ $record['url'] }}</td>
                                <td>{{ number_format($record['score'], 4) }}</td>
                                <td>{{ $record['risk_level'] }}</td>
                                <td>{{ $record['status'] }}</td>
                                <td>{{ $record['reason'] }}</td>
                                <td>{{ $record['computer_number'] }}</td>
                                <td>{{ $record['campus_name'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
