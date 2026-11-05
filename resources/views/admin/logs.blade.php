@extends('layouts.admin', ['title' => 'Logs'])

@section('content')
    <style>
        .log-delete-button { border: 0; background: transparent; color: #dc3545; padding: .4rem; font-size: 1.25rem; line-height: 1; }
        .log-delete-button:hover { color: #b02a37; }
        .log-delete-dialog { width: min(480px, calc(100% - 2rem)); padding: 1.5rem; border: 1px solid var(--pb-border, #dee2e6); border-radius: .75rem; background: var(--pb-surface, #fff); color: var(--pb-text, #212529); }
        .log-delete-dialog::backdrop { background: rgba(0, 0, 0, .6); }
        .log-delete-url { overflow-wrap: anywhere; }
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
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fs-3 mb-1">Monitoring Logs</h1>
                    <p class="mb-0">Database-recorded extension monitoring results across all campuses.</p>
                </div>
                <button type="button" class="btn btn-danger" id="open-delete-logs" @disabled($records->total() === 0)>
                    <i class="ti ti-trash me-1" aria-hidden="true"></i> Delete Logs
                </button>
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
                            <th scope="col">Action</th>
                            <th>Time (PHT, UTC+8)</th>
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
                                <td>
                                    <button type="button" class="log-delete-button" data-delete-log
                                        data-delete-url="{{ route('admin.logs.destroy', $record['id']) }}"
                                        data-log-url="{{ $record['url'] }}"
                                        aria-label="Delete monitoring log for {{ $record['url'] }}" title="Delete log">
                                        <i class="ti ti-trash" aria-hidden="true"></i>
                                    </button>
                                </td>
                                <td>{{ $record['time'] }}</td>
                                <td>{{ number_format($record['score'], 4) }}</td>
                                <td>{{ $record['risk_level'] }}</td>
                                <td>{{ $record['status'] }}</td>
                                <td>{{ $record['reason'] }}</td>
                                <td>{{ $record['device_name'] }}</td>
                                <td>{{ $record['campus_name'] }}</td>
                                <td class="text-nowrap" style="min-width: 420px;">{{ $record['url'] }}</td>
                            </tr>
                        @endforeach
                        @if ($records->isEmpty())
                            <tr><td colspan="9" class="py-5 text-center text-secondary">No monitoring logs found.</td></tr>
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
    <dialog id="delete-log-dialog" class="log-delete-dialog" aria-labelledby="delete-log-title" aria-describedby="delete-log-description">
        <h2 id="delete-log-title" class="fs-5">Delete monitoring log?</h2>
        <p id="delete-log-description">This log will be permanently deleted. This cannot be undone.</p>
        <p id="delete-log-url" class="log-delete-url small"></p>
        <form id="delete-log-form" method="POST">
            @csrf
            @method('DELETE')
            <input type="hidden" name="page" value="{{ $records->currentPage() }}">
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" id="cancel-delete-log" autofocus>Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </dialog>
    <dialog id="delete-logs-dialog" class="log-delete-dialog" aria-labelledby="delete-logs-title" aria-describedby="delete-logs-description">
        <h2 id="delete-logs-title" class="fs-5">Delete monitoring logs?</h2>
        <p id="delete-logs-description">Delete every log for the selected campus, across all pages. This cannot be undone.</p>
        <form id="delete-logs-form" method="POST" action="{{ route('admin.logs.delete-all') }}">
            @csrf
            @method('DELETE')
            <label for="delete-logs-campus" class="form-label">Campus</label>
            <select id="delete-logs-campus" name="campus" class="form-select mb-3" required autofocus>
                <option value="all" selected>{{ count($deletionCampuses) > 1 ? 'All campuses' : 'All logs for your campus' }}</option>
                @foreach ($deletionCampuses as $campus)
                    <option value="{{ $campus }}">{{ $campus }}</option>
                @endforeach
            </select>
            <p class="fw-semibold" id="delete-logs-summary" aria-live="polite"></p>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" id="cancel-delete-logs">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
            </div>
        </form>
    </dialog>
    <script>
        (() => {
            const bulkDialog = document.getElementById('delete-logs-dialog');
            const campusSelect = document.getElementById('delete-logs-campus');
            const updateSummary = () => {
                document.getElementById('delete-logs-summary').textContent =
                    'Permanently delete: ' + campusSelect.selectedOptions[0].textContent + '.';
            };
            document.getElementById('open-delete-logs').addEventListener('click', () => {
                campusSelect.value = 'all';
                updateSummary();
                bulkDialog.showModal();
            });
            campusSelect.addEventListener('change', updateSummary);
            document.getElementById('cancel-delete-logs').addEventListener('click', () => bulkDialog.close());
            document.getElementById('delete-logs-form').addEventListener('submit', event => {
                event.currentTarget.querySelector('[type="submit"]').disabled = true;
            });
            const dialog = document.getElementById('delete-log-dialog');
            const form = document.getElementById('delete-log-form');
            document.querySelectorAll('[data-delete-log]').forEach(button => {
                button.addEventListener('click', () => {
                    form.action = button.dataset.deleteUrl;
                    document.getElementById('delete-log-url').textContent = button.dataset.logUrl;
                    dialog.showModal();
                });
            });
            document.getElementById('cancel-delete-log').addEventListener('click', () => dialog.close());
            form.addEventListener('submit', () => {
                form.querySelector('[type="submit"]').disabled = true;
            });
        })();
    </script>
@endsection
