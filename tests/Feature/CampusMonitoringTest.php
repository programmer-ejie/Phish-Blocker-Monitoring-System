<?php

use App\Models\Campus;
use App\Models\Device;
use App\Models\PhishingLog;
use App\Models\User;
use Illuminate\Support\Str;

test('missing uptime stays unknown while measured zero remains at risk', function () {
    $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $campus = Campus::create(['code' => 'SLSU-SanJuan', 'name' => 'San Juan', 'uptime_percentage' => null]);
    PhishingLog::create(['url' => 'https://example.com', 'campus_name' => $campus->code,
        'computer_number' => 1, 'risk_level' => 'Low', 'status' => 'Proceed']);
    foreach (['/admin/dashboard' => 'campusBreakdown', '/admin/analytics' => 'campusAnalytics'] as $url => $key) {
        $this->actingAs($user)->get($url)->assertOk()->assertSee('Unknown')->assertViewHas($key, function ($items) {
            $item = collect($items)->firstWhere('name', 'SLSU-SanJuan');
            expect($item['uptime'])->toBeNull();
            expect($item['health_score'])->toBeNull();
            expect($item['health'])->toBe('Unknown');
            expect($item['tone'])->toBe('secondary');
            expect($item['proceed'])->toBe(1);
            return true;
        });
    }
    $this->get('/admin/campuses')->assertOk()->assertViewHas('campuses', fn ($items) => collect($items)->firstWhere('name', 'SLSU-SanJuan')['uptime'] === 'Unknown');
    $campus->update(['uptime_percentage' => 0]);
    $this->get('/admin/dashboard')->assertViewHas('campusBreakdown', function ($items) {
        $item = collect($items)->firstWhere('name', 'SLSU-SanJuan');
        expect($item['uptime'])->toBe(0.0);
        expect($item['health_score'])->toBe(0);
        expect($item['health'])->toBe('At Risk');
        return true;
    });
});

test('dashboard lists every extension campus before any activity', function () {
    $extensionCampuses = collect(glob(base_path('extension-setup/extensions-version/*/config.json')))
        ->map(fn ($path) => json_decode(file_get_contents($path), true)['campus_name'])->sort()->values()->all();
    expect($extensionCampuses)->toBe(collect(Device::CAMPUSES)->sort()->values()->all());
    $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $response = $this->actingAs($user)->get('/admin/dashboard')->assertOk();
    $response->assertViewHas('campusBreakdown', function ($campuses) {
        expect(array_column($campuses, 'name'))->toBe(Device::CAMPUSES);
        foreach ($campuses as $campus) {
            expect($campus['computers'])->toBe(0);
            expect($campus['scans'])->toBe(0);
            expect($campus['last_sync'])->toBe('Never');
            expect($campus['health'])->toBe('No activity');
        }

        return true;
    });
});

test('extension activity feeds campus cards and details without stale seeded counts', function () {
    $this->freezeTime();
    $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    foreach (Device::CAMPUSES as $campus) {
        $uuid = (string) Str::uuid();
        $this->postJson('/api/devices/register', ['device_uuid' => $uuid, 'campus_name' => $campus])->assertOk();
        foreach (['Proceed', 'Suspicious', 'Block'] as $status) {
            $this->postJson('/api/phishing-logs', [
                'device_uuid' => $uuid, 'campus_name' => $campus,
                'url' => 'https://example.com', 'risk_level' => 'High', 'status' => $status,
            ])->assertCreated();
        }
        Campus::where('code', $campus)->update(['computer_count' => 99, 'last_sync_at' => now()->addHours(7)]);
    }
    $this->actingAs($user)->get('/admin/dashboard')->assertOk()->assertViewHas('campusBreakdown', function ($campuses) {
        foreach ($campuses as $campus) {
            expect($campus['computers'])->toBe(1);
            expect($campus['scans'])->toBe(3);
            expect($campus['proceed'])->toBe(1);
            expect($campus['suspicious'])->toBe(1);
            expect($campus['blocked'])->toBe(1);
            expect($campus['last_sync'])->not->toContain('from now');
        }

        return true;
    });
    $this->get('/admin/campuses')->assertOk()->assertViewHas('campuses', fn ($campuses) => count($campuses) === 6 && array_unique(array_column($campuses, 'computers')) === [1]);
});
