<?php

use App\Models\Alert;
use App\Models\User;

test('dashboard priority alerts show ten per page without overlapping records', function () {
    $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    foreach (range(1, 12) as $number) {
        Alert::create(['title' => 'Pagination alert '.$number, 'severity' => 'High', 'owner' => 'Test', 'status' => 'Open']);
    }
    $this->actingAs($user)->get('/admin/dashboard')->assertOk()
        ->assertViewHas('alerts', function ($alerts) {
            expect($alerts->total())->toBe(12);
            expect($alerts->perPage())->toBe(10);
            expect($alerts->count())->toBe(10);
            expect($alerts->items()[0]['title'])->toBe('Pagination alert 12');
            expect($alerts->nextPageUrl())->toContain('alerts_page=2')->toContain('#priority-alerts');

            return true;
        });
    $this->get('/admin/dashboard?alerts_page=2')->assertOk()
        ->assertViewHas('alerts', function ($alerts) {
            expect($alerts->count())->toBe(2);
            expect(array_column($alerts->items(), 'title'))->toBe(['Pagination alert 2', 'Pagination alert 1']);

            return true;
        });
});
