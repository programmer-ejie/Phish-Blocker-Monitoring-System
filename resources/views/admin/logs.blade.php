@extends('layouts.admin', ['title' => 'Logs'])

@section('content')
    <style>
        @media (min-width: 992px) {
            body:has(.logs-page) {
                overflow-y: hidden;
            }

            body:has(.logs-page) .admin-page-content {
                min-height: calc(100vh - 5rem);
                height: calc(100vh - 5rem);
                overflow: hidden;
            }

            body:has(.logs-page) .admin-content-shell {
                min-height: 0;
                overflow: hidden;
            }
        }
    </style>

    <div class="logs-page">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fs-3 mb-1">Monitoring Logs</h1>
                    <p class="mb-0">Database-recorded extension monitoring results across all campuses.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="table-responsive">
                <table class="table mb-0 text-nowrap table-hover align-middle">
                    <thead class="table-light border-light">
                        <tr>
                            <th>Time</th>
                            <th>Score</th>
                            <th>Risk Level</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Computer #</th>
                            <th>Campus</th>
                            <th>URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr class="align-middle">
                                <td>{{ $record['time'] }}</td>
                                <td>{{ number_format($record['score'], 4) }}</td>
                                <td>{{ $record['risk_level'] }}</td>
                                <td>{{ $record['status'] }}</td>
                                <td>{{ $record['reason'] }}</td>
                                <td>{{ $record['computer_number'] }}</td>
                                <td>{{ $record['campus_name'] }}</td>
                                <td class="text-nowrap" style="min-width: 420px;">{{ $record['url'] }}</td>
                            </tr>
                        @endforeach
                        @if ($records->isEmpty())
                            <tr><td colspan="8" class="py-5 text-center text-secondary">No monitoring logs found.</td></tr>
                        @endif
                    </tbody>
                </table>
                </div>
                <div class="card-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 bg-transparent px-4 py-3">
                    <p class="mb-0 small text-secondary">Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }} records</p>
                    {{ $records->onEachSide(1)->links() }}
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
