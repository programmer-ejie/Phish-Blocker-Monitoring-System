<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Device;
use App\Models\PhishingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'campus_name' => ['required', Rule::in(Device::CAMPUSES)],
        ]);
        $data['device_uuid'] = strtolower($data['device_uuid']);

        $device = DB::transaction(function () use ($data): Device {
            // Serialize allocation per campus, including concurrent first registrations.
            Campus::query()->upsert([
                ['code' => $data['campus_name'], 'name' => $data['campus_name'],
                    'is_active' => true, 'last_sync_at' => now()],
            ], ['code'], ['is_active', 'last_sync_at', 'updated_at']);
            $campus = Campus::where('code', $data['campus_name'])->lockForUpdate()->firstOrFail();
            $device = Device::where($data)->first();
            if (! $device) {
                // Reserve numbers already used by manually configured extensions, too.
                $number = max(
                    (int) Device::where('campus_name', $data['campus_name'])->max('computer_number'),
                    (int) PhishingLog::where('campus_name', $data['campus_name'])->max('computer_number'),
                ) + 1;
                $device = Device::create([
                    ...$data,
                    'computer_number' => $number,
                    'name' => $data['campus_name'].'-PC-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                ]);
            }
            $device->update(['last_seen_at' => now()]);
            $numbers = DB::table('phishing_logs')->where('campus_name', $data['campus_name'])
                ->whereNotNull('computer_number')->select('computer_number')
                ->union(DB::table('devices')->where('campus_name', $data['campus_name'])->select('computer_number'));
            $campus->update(['computer_count' => DB::query()->fromSub($numbers, 'campus_computers')->count()]);

            return $device;
        }, 5);

        return response()->json($device->only(['device_uuid', 'campus_name', 'computer_number', 'name']));
    }
}
