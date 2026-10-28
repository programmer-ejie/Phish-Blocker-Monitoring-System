<?php

use App\Models\Alert;
use App\Models\Campus;
use App\Models\PhishingLog;

function extensionLogPayload(array $overrides = []): array
{
    return array_replace([
        'url' => 'https://example.com/Case?Token=AbC',
        'score' => 0.8,
        'risk_level' => 'High',
        'status' => 'Block',
        'reason' => 'ML threshold reached',
        'computer_number' => 1,
        'campus_name' => 'SLSU-TO',
        'metadata' => ['ml_engine' => 'phishing_url_bundle', 'model_threshold' => 0.569018,
            'model_block_threshold' => 0.95, 'model_decision' => 'Suspicious'],
    ], $overrides);
}

test('an extension can submit a log without a browser session', function () {
    $response = $this->postJson('/api/phishing-logs', extensionLogPayload());
    $response->assertCreated()->assertJson(['logged' => true]);
    $this->assertDatabaseHas('phishing_logs', ['url' => 'https://example.com/Case?Token=AbC', 'campus_name' => 'SLSU-TO']);
    $campus = Campus::firstOrFail();
    expect(PhishingLog::firstOrFail()->campus_id)->toBe($campus->id);
    expect(PhishingLog::firstOrFail()->metadata['ml_engine'])->toBe('phishing_url_bundle');
    expect(PhishingLog::firstOrFail()->metadata['model_block_threshold'])->toBe(0.95);
    expect(PhishingLog::firstOrFail()->metadata['model_decision'])->toBe('Suspicious');
    expect($campus->computer_count)->toBe(1);
    expect(Alert::firstOrFail()->severity)->toBe('High');
});

test('safe logs update campus computers without creating alerts or duplicate campuses', function () {
    foreach ([1, 1, 2] as $computer) {
        $this->postJson('/api/phishing-logs', extensionLogPayload([
            'score' => 0.1, 'risk_level' => 'Low', 'status' => 'Proceed', 'computer_number' => $computer,
        ]))->assertCreated();
    }
    expect(Campus::count())->toBe(1);
    expect(Campus::firstOrFail()->computer_count)->toBe(2);
    expect(Alert::count())->toBe(0);
});

test('all six campuses are recorded separately', function () {
    foreach (['SLSU-Bontoc', 'SLSU-Hinunangan', 'SLSU-Maasin', 'SLSU-Main', 'SLSU-SanJuan', 'SLSU-TO'] as $campus) {
        $this->postJson('/api/phishing-logs', extensionLogPayload(['campus_name' => $campus]))->assertCreated();
    }
    expect(Campus::count())->toBe(6);
    expect(PhishingLog::count())->toBe(6);
});

test('invalid extension logs are rejected before database writes', function (array $override, string $field) {
    $this->postJson('/api/phishing-logs', extensionLogPayload($override))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(PhishingLog::count())->toBe(0);
    expect(Campus::count())->toBe(0);
})->with([
    [['url' => 'javascript:alert(1)'], 'url'],
    [['score' => 2], 'score'],
    [['computer_number' => 0], 'computer_number'],
    [['campus_name' => ''], 'campus_name'],
    [['risk_level' => 'Unknown'], 'risk_level'],
    [['status' => 'Unknown'], 'status'],
]);

test('extension callers cannot choose database ids or unexpected metadata', function () {
    $this->postJson('/api/phishing-logs', extensionLogPayload([
        'campus_id' => 999,
        'metadata' => ['ml_engine' => 'phishing_url_bundle', 'unexpected' => 'discarded'],
    ]))->assertCreated();
    $log = PhishingLog::firstOrFail();
    expect($log->campus_id)->not->toBe(999);
    expect($log->metadata)->not->toHaveKey('unexpected');
});

test('detection time preserves UTC and displays Philippine time while receipt time is independent', function () {
    $this->postJson('/api/phishing-logs', extensionLogPayload([
        'detected_at' => '2026-10-27T16:30:00.123Z',
        'created_at' => '2000-01-01T00:00:00Z',
    ]))->assertCreated();
    $log = PhishingLog::firstOrFail();
    expect($log->detected_at->utc()->format('Y-m-d H:i:s.v'))->toBe('2026-10-27 16:30:00.123');
    expect($log->detected_at->setTimezone('Asia/Manila')->format('Y-m-d H:i'))->toBe('2026-10-28 00:30');
    expect($log->created_at->year)->not->toBe(2000);
});

test('detection time is optional for older extensions', function () {
    $this->postJson('/api/phishing-logs', extensionLogPayload())->assertCreated();
    expect(PhishingLog::firstOrFail()->detected_at)->toBeNull();
});

test('detection time requires an explicit UTC timestamp', function () {
    $this->postJson('/api/phishing-logs', extensionLogPayload([
        'detected_at' => '2026-10-28 12:00:00',
    ]))->assertUnprocessable()->assertJsonValidationErrors('detected_at');
});
