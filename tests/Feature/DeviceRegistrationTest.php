<?php

use App\Models\Campus;
use App\Models\Device;
use App\Models\PhishingLog;
use Illuminate\Support\Str;

function registerTestDevice(string $uuid, string $campus = 'SLSU-TO')
{
    return test()->postJson('/api/devices/register', ['device_uuid' => $uuid, 'campus_name' => $campus]);
}

test('registration is stable and numbers are independent per campus', function () {
    $uuid = (string) Str::uuid();
    registerTestDevice($uuid)->assertOk()->assertJson(['name' => 'SLSU-TO-PC-001']);
    registerTestDevice($uuid)->assertOk()->assertJson(['computer_number' => 1]);
    registerTestDevice((string) Str::uuid())->assertOk()->assertJson(['name' => 'SLSU-TO-PC-002']);
    registerTestDevice($uuid, 'SLSU-Main')->assertOk()->assertJson(['name' => 'SLSU-Main-PC-001']);
    expect(Device::count())->toBe(3);
});

test('new devices avoid computer numbers in legacy logs', function () {
    PhishingLog::create(['url' => 'https://example.com', 'risk_level' => 'Safe', 'status' => 'Proceed',
        'campus_name' => 'SLSU-TO', 'computer_number' => 12]);
    registerTestDevice((string) Str::uuid())->assertOk()->assertJson(['computer_number' => 13]);
});

test('registered identity overrides client computer numbers and links the log', function () {
    $uuid = (string) Str::uuid();
    registerTestDevice($uuid)->assertOk();
    registerTestDevice((string) Str::uuid())->assertOk();
    $this->postJson('/api/phishing-logs', [
        'device_uuid' => $uuid, 'computer_number' => 999, 'campus_name' => 'SLSU-TO',
        'url' => 'https://example.com', 'risk_level' => 'Safe', 'status' => 'Proceed',
    ])->assertCreated();
    $log = PhishingLog::firstOrFail();
    expect($log->computer_number)->toBe(1);
    expect($log->device->name)->toBe('SLSU-TO-PC-001');
    expect(Campus::firstOrFail()->computer_count)->toBe(2);
});

test('registered devices do not need manual computer numbers', function () {
    $uuid = (string) Str::uuid();
    registerTestDevice($uuid)->assertOk();
    $this->postJson('/api/phishing-logs', [
        'device_uuid' => $uuid, 'campus_name' => 'SLSU-TO',
        'url' => 'https://example.com', 'risk_level' => 'Safe', 'status' => 'Proceed',
    ])->assertCreated();
});

test('unknown devices and campus mismatches cannot submit logs', function () {
    $uuid = (string) Str::uuid();
    registerTestDevice($uuid)->assertOk();
    foreach ([[$uuid, 'SLSU-Main'], [(string) Str::uuid(), 'SLSU-TO']] as [$id, $campus]) {
        $this->postJson('/api/phishing-logs', [
            'device_uuid' => $id, 'campus_name' => $campus,
            'url' => 'https://example.com', 'risk_level' => 'Safe', 'status' => 'Proceed',
        ])->assertUnprocessable()->assertJsonValidationErrors('device_uuid');
    }
    expect(PhishingLog::count())->toBe(0);
});

test('invalid registrations create no records', function () {
    registerTestDevice('invalid')->assertUnprocessable();
    registerTestDevice((string) Str::uuid(), 'Unknown')->assertUnprocessable();
    expect(Device::count())->toBe(0);
});
