<?php

use App\Models\PhishingLog;
use Illuminate\Support\Facades\Storage;

test('trusted policy previews then updates approved logs preserving scores and excluded verdicts', function () {
    Storage::fake('local');
    $make = fn ($url, $reason = 'ML warning') => PhishingLog::create([
        'url' => $url, 'campus_name' => 'SLSU-TO', 'computer_number' => 1,
        'score' => .99, 'risk_level' => 'High', 'status' => 'Block', 'reason' => $reason,
    ]);
    $trusted = $make('https://drive.google.com/file/d/brand-new-file/view');
    $excluded = collect([
        'https://coingraph-update.com/', 'http://mail.printakid.com/',
        'https://testcsfund.site.je/', 'https://l.facebook.com/l.php?u=https://drive.google.com',
        'https://drive.google.com.evil.example/', 'https://drive.google.com@evil.example/',
    ])->map(fn ($url) => $make($url));
    $threat = $make('https://docs.google.com/forms/d/known-threat', 'Phishing (PhishTank)');
    $this->artisan('logs:apply-trusted-policy')->assertSuccessful();
    expect($trusted->fresh()->status)->toBe('Block');
    $this->artisan('logs:apply-trusted-policy', ['--apply' => true])->assertSuccessful();
    $trusted->refresh();
    expect($trusted->status)->toBe('Proceed');
    expect($trusted->risk_level)->toBe('Low');
    expect($trusted->score)->toBe(.99);
    expect($trusted->metadata['trusted_policy_original']['status'])->toBe('Block');
    foreach ($excluded->push($threat) as $log) {
        expect($log->fresh()->status)->toBe('Block');
    }
    expect(Storage::disk('local')->allFiles('log-policy-backups'))->toHaveCount(1);
    $this->artisan('logs:apply-trusted-policy', ['--apply' => true])->assertSuccessful();
    expect(Storage::disk('local')->allFiles('log-policy-backups'))->toHaveCount(1);
});
