<?php

use App\Models\PhishingLog;
use App\Models\User;

test('newly received logs appear before historical rows with future receipt times', function () {
    $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    foreach (range(1, 6) as $number) {
        PhishingLog::create(['url' => 'https://example.com/old-'.$number,
            'campus_name' => 'SLSU-TO', 'computer_number' => 1,
            'risk_level' => 'Safe', 'status' => 'Proceed', 'created_at' => now()->addHours(8)]);
    }
    $new = PhishingLog::create(['url' => 'https://example.com/time-check',
        'campus_name' => 'SLSU-TO', 'computer_number' => 1,
        'risk_level' => 'Safe', 'status' => 'Proceed',
        'detected_at' => '2026-10-31T08:27:16.178Z']);
    $this->actingAs($user)->get('/admin/logs?page=1')->assertOk()->assertViewHas('records', function ($records) use ($new) {
        expect($records->items()[0]['url'])->toBe($new->url);
        expect($records->items()[0]['time'])->toBe('04:27 PM');
        expect($records->total())->toBe(7);

        return true;
    });
});
