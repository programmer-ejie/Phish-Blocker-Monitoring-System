<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Campus;
use App\Models\Device;
use App\Models\PhishingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PhishingLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'detected_at' => ['nullable', 'date_format:Y-m-d\\TH:i:s.v\\Z'],
            'url' => ['required', 'string', 'url:http,https', 'max:16384'],
            'score' => ['nullable', 'numeric', 'between:0,1'],
            'risk_level' => ['required', Rule::in(['Safe', 'Low', 'Medium', 'High', 'Critical'])],
            'status' => ['required', Rule::in(['Proceed', 'Suspicious', 'Block'])],
            'reason' => ['nullable', 'string', 'max:8192'],
            'device_uuid' => ['sometimes', 'required', 'uuid'],
            'computer_number' => ['required_without:device_uuid', 'integer', 'min:1', 'max:4294967295'],
            'campus_name' => ['required', 'string', 'max:30'],
            'metadata' => ['sometimes', 'array'],
            'metadata.ml_engine' => ['nullable', 'string', 'max:100'],
            'metadata.model_threshold' => ['nullable', 'numeric', 'between:0,1'],
            'metadata.model_block_threshold' => ['nullable', 'numeric', 'between:0,1'],
            'metadata.model_decision' => ['nullable', Rule::in(['Proceed', 'Suspicious', 'Block'])],
        ]);

        $log = DB::transaction(function () use ($data): PhishingLog {
            if (isset($data['device_uuid'])) {
                $device = Device::where('device_uuid', strtolower($data['device_uuid']))
                    ->where('campus_name', $data['campus_name'])->first();
                if (! $device) {
                    throw ValidationException::withMessages([
                        'device_uuid' => 'Register this device for the campus before submitting logs.',
                    ]);
                }
                $data['device_id'] = $device->id;
                $data['computer_number'] = $device->computer_number;
                $device->update(['last_seen_at' => now()]);
            }
            unset($data['device_uuid']);

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
                'metadata' => array_intersect_key($data['metadata'] ?? [], array_flip(['ml_engine', 'model_threshold', 'model_block_threshold', 'model_decision'])),
            ]);

            // Include registered devices even before they have submitted their first log.
            $numbers = DB::table('phishing_logs')->where('campus_name', $data['campus_name'])
                ->whereNotNull('computer_number')->select('computer_number')
                ->union(DB::table('devices')->where('campus_name', $data['campus_name'])->select('computer_number'));
            Campus::where('code', $data['campus_name'])->update([
                'computer_count' => DB::query()->fromSub($numbers, 'campus_computers')->count(),
                'last_sync_at' => now(),
            ]);

            if ($campus) {
                $campus->update([
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
