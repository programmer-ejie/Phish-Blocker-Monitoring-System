<?php

use App\Models\Alert;
use App\Models\PhishingLog;
use App\Models\User;

function unreadTestAlert(): Alert
{
    return Alert::create(['title' => 'Unread alert', 'severity' => 'Critical', 'owner' => 'Test', 'status' => 'Open']);
}

test('reading notifications reduces the count without resolving incidents', function () {
    $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $first = unreadTestAlert();
    $second = unreadTestAlert();
    $this->actingAs($user)->get('/admin/dashboard')->assertOk()->assertViewHas('unreadAlertCount', 2);
    $this->post(route('admin.alerts.read', $first))->assertRedirect(route('admin.alerts'));
    expect($first->fresh()->read_at)->not->toBeNull();
    expect($first->fresh()->status)->toBe('Open');
    $this->get('/admin/dashboard')->assertOk()->assertViewHas('unreadAlertCount', 1)
        ->assertViewHas('notifications', fn ($items) => count($items) === 1 && $items[0]['id'] === $second->id);
    $this->post(route('admin.alerts.read-all'))->assertRedirect();
    $this->get('/admin/alerts')->assertOk()->assertViewHas('unreadAlertCount', 0)->assertViewHas('notifications', []);
    unreadTestAlert();
    $this->get('/admin/dashboard')->assertOk()->assertViewHas('unreadAlertCount', 1);
});

test('delete all alerts preserves phishing logs', function () {
    $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    unreadTestAlert();
    PhishingLog::create(['url' => 'https://example.com', 'risk_level' => 'Safe', 'status' => 'Proceed', 'campus_name' => 'SLSU-TO', 'computer_number' => 1]);
    $this->actingAs($user)->delete(route('admin.alerts.delete-all'))->assertRedirect(route('admin.alerts'));
    expect(Alert::count())->toBe(0);
    expect(PhishingLog::count())->toBe(1);
});

test('guests and non administrators cannot change alerts', function () {
    $alert = unreadTestAlert();
    $this->post(route('admin.alerts.read', $alert))->assertRedirect('/login');
    $this->post(route('admin.alerts.read-all'))->assertRedirect('/login');
    $this->delete(route('admin.alerts.delete-all'))->assertRedirect('/login');
    $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
    $this->actingAs($user)->post(route('admin.alerts.read', $alert))->assertForbidden();
    $this->post(route('admin.alerts.read-all'))->assertForbidden();
    $this->delete(route('admin.alerts.delete-all'))->assertForbidden();
    expect(Alert::count())->toBe(1);
    expect($alert->fresh()->read_at)->toBeNull();
});
