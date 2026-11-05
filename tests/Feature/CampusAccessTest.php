<?php

use App\Models\Alert;
use App\Models\Campus;
use App\Models\Device;
use App\Models\PhishingLog;
use App\Models\User;

function campusAccessFixtures(): array
{
    $fixtures = [];
    foreach (Device::CAMPUSES as $index => $code) {
        $campus = Campus::create(['code' => $code, 'name' => $code]);
        $alert = Alert::create(['campus_id' => $campus->id, 'title' => $code.' private alert', 'severity' => 'Critical', 'status' => 'Open', 'owner' => 'Test']);
        PhishingLog::create(['campus_id' => $campus->id, 'campus_name' => $code, 'computer_number' => 1,
            'url' => 'https://example.com/'.$code, 'risk_level' => 'High', 'status' => 'Block']);
        $user = User::factory()->create(['role' => 'campus_admin', 'campus_name' => $code, 'is_active' => true]);
        $fixtures[$code] = compact('campus', 'alert', 'user');
    }

    return $fixtures;
}

test('each campus administrator only sees their campus throughout the system', function () {
    $fixtures = campusAccessFixtures();
    foreach ($fixtures as $code => $fixture) {
        $this->actingAs($fixture['user']);
        $this->get('/admin/dashboard?campus_name=SLSU-Main')->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total_scans'] === 1 && $summary['active_campuses'] === 1 && $summary['critical_incidents'] === 1)
            ->assertViewHas('unreadAlertCount', 1)
            ->assertViewHas('campusBreakdown', fn ($items) => array_column($items, 'name') === [$code])
            ->assertViewHas('alerts', fn ($items) => $items->total() === 1 && $items->items()[0]['campus'] === $code)
            ->assertViewHas('notifications', fn ($items) => count($items) === 1 && $items[0]['campus'] === $code)
            ->assertViewHas('hourlyTraffic', fn ($items) => array_sum(array_column($items, 'requests')) === 1);
        $this->get('/admin/logs')->assertOk()->assertViewHas('records', fn ($items) => $items->total() === 1 && $items->items()[0]['campus_name'] === $code);
        $this->get('/admin/analytics')->assertOk()
            ->assertViewHas('campusAnalytics', fn ($items) => array_column($items, 'name') === [$code])
            ->assertViewHas('riskDistribution', fn ($items) => array_sum(array_column($items, 'count')) === 1);
        $this->get('/admin/campuses')->assertOk()->assertViewHas('campuses', fn ($items) => array_column($items, 'name') === [$code]);
        $this->get('/admin/alerts')->assertOk()->assertViewHas('alerts', fn ($items) => $items->total() === 1)
            ->assertViewHas('alertSummary', fn ($items) => $items['total'] === 1 && $items['campuses'] === 1);
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/')->assertOk()->assertViewHas('summary', fn ($items) => $items['total_scans'] === 1);
    }
    $super = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->actingAs($super)->get('/admin/dashboard')->assertOk()->assertViewHas('summary', fn ($items) => $items['total_scans'] === 6);
    $this->get('/admin/settings')->assertOk();
});

test('campus alert actions cannot read or delete another campus alerts', function () {
    $fixtures = campusAccessFixtures();
    $own = $fixtures['SLSU-TO'];
    $other = $fixtures['SLSU-Main'];
    $this->actingAs($own['user'])->post(route('admin.alerts.read', $other['alert']))->assertNotFound();
    $this->post(route('admin.alerts.read', $own['alert']))->assertRedirect();
    $this->post(route('admin.alerts.read-all'))->assertRedirect();
    expect($own['alert']->fresh()->read_at)->not->toBeNull();
    expect($other['alert']->fresh()->read_at)->toBeNull();
    $this->delete(route('admin.alerts.delete-all'))->assertRedirect();
    expect(Alert::count())->toBe(5);
    expect($other['alert']->fresh())->not->toBeNull();
    expect(PhishingLog::count())->toBe(6);
});

test('campus login uses assigned campus and unassigned campus accounts are denied', function () {
    $user = User::factory()->create(['role' => 'campus_admin', 'campus_name' => 'SLSU-TO', 'is_active' => true, 'password' => 'campus-test-password']);
    $this->post('/login', ['email' => $user->email, 'password' => 'campus-test-password', 'campus_name' => 'SLSU-Main'])->assertRedirect('/admin/dashboard');
    $this->get('/admin/dashboard')->assertOk()->assertViewHas('campusBreakdown', fn ($items) => array_column($items, 'name') === ['SLSU-TO']);
    $user->update(['campus_name' => null]);
    $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
});

test('public homepage does not expose campus logs or totals', function () {
    campusAccessFixtures();
    $this->get('/')->assertOk()->assertViewHas('records', [])->assertViewHas('summary', fn ($items) => $items['total_scans'] === 0);
});
