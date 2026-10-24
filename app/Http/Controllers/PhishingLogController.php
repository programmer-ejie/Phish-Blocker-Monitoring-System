<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Campus;
use App\Models\PhishingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PhishingLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'url:http,https', 'max:16384'],
            'score' => ['nullable', 'numeric', 'between:0,1'],
            'risk_level' => ['required', Rule::in(['Safe', 'Low', 'Medium', 'High', 'Critical'])],
            'status' => ['required', Rule::in(['Proceed', 'Suspicious', 'Block'])],
            'reason' => ['nullable', 'string', 'max:8192'],
            'computer_number' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'campus_name' => ['required', 'string', 'max:30'],
            'metadata' => ['sometimes', 'array'],
            'metadata.ml_engine' => ['nullable', 'string', 'max:100'],
            'metadata.model_threshold' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        $log = DB::transaction(function () use ($data): PhishingLog {
            // Existing PostgreSQL deployments already have campus/alert triggers.
            // MySQL and SQLite require these updates in the application instead.
            $usesDatabaseTriggers = DB::getDriverName() === 'pgsql';
            $campus = null;
            if (! $usesDatabaseTriggers) {
                Campus::query()->upsert([
                    ['code' => $data['campus_name'], 'name' => $data['campus_name'],
                        'is_active' => true, 'last_sync_at' => now()],
                ], ['code'], ['is_active', 'last_sync_at', 'updated_at']);
                $campus = Campus::query()->where('code', $data['campus_name'])->lockForUpdate()->firstOrFail();
            }

            $log = PhishingLog::create([
                ...$data,
                'campus_id' => $campus?->id,
                'metadata' => array_intersect_key($data['metadata'] ?? [], array_flip(['ml_engine', 'model_threshold'])),
            ]);

            if ($campus) {
                $campus->update([
                    'computer_count' => PhishingLog::query()->where('campus_id', $campus->id)
                        ->whereNotNull('computer_number')->distinct()->count('computer_number'),
                    'last_sync_at' => now(),
                ]);

                if ($log->status !== 'Proceed') {
                    $severity = $log->risk_level === 'Critical' ? 'Critical'
                        : ($log->status === 'Block' || $log->risk_level === 'High' ? 'High' : 'Medium');
                    Alert::create([
                        'campus_id' => $campus->id,
                        'title' => match ($severity) {
                            'Critical' => 'Critical phishing URL detected',
                            'High' => 'Blocked phishing URL detected',
                            default => 'Suspicious URL detected',
                        },
                        'severity' => $severity,
                        'owner' => 'Automated Detection',
                        'status' => $severity === 'Medium' ? 'Monitoring' : 'Open',
                    ]);
                }
            }

            return $log;
        });

        return response()->json(['logged' => true, 'id' => $log->id], 201);
    }
}
