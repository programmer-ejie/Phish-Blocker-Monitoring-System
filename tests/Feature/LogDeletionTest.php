<?php

use App\Models\PhishingLog;
use App\Models\User;

function deletionTestLog(string $campus = 'SLSU-TO'): PhishingLog
{
    return PhishingLog::create([
        'url' => 'https://example.com/test', 'campus_name' => $campus,
        'computer_number' => 1, 'risk_level' => 'Low', 'status' => 'Proceed',
    ]);
}

test('administrators can delete a selected log and return to a valid page', function () {
    $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    foreach (range(1, 6) as $number) {
        deletionTestLog();
    }
    $log = deletionTestLog();
    $this->actingAs($user)->get('/admin/logs')->assertOk()
        ->assertSee('data-delete-log', false)->assertSee('Delete monitoring log?');
    $this->delete(route('admin.logs.destroy', $log), ['page' => 2])
        ->assertRedirect(route('admin.logs', ['page' => 1]))
        ->assertSessionHas('status', 'Monitoring log deleted.');
    $this->assertDatabaseMissing('phishing_logs', ['id' => $log->id]);
    expect(PhishingLog::count())->toBe(6);
    $this->delete(route('admin.logs.destroy', $log))->assertNotFound();
});

test('campus administrators can only delete their own campus logs', function () {
    $own = deletionTestLog();
    $other = deletionTestLog('SLSU-Main');
    $user = User::factory()->create(['role' => 'campus_admin', 'campus_name' => 'SLSU-TO', 'is_active' => true]);
    $this->actingAs($user)->delete(route('admin.logs.destroy', $other))->assertNotFound();
    $this->assertDatabaseHas('phishing_logs', ['id' => $other->id]);
    $this->delete(route('admin.logs.destroy', $own))->assertRedirect();
    $this->assertDatabaseMissing('phishing_logs', ['id' => $own->id]);
});

test('guests and unauthorized users cannot delete logs', function () {
    $log = deletionTestLog();
    $this->delete(route('admin.logs.destroy', $log))->assertRedirect('/login');
    foreach ([['role' => 'user', 'is_active' => true], ['role' => 'admin', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes))
            ->delete(route('admin.logs.destroy', $log))->assertForbidden();
    }
    $this->assertDatabaseHas('phishing_logs', ['id' => $log->id]);
});

test('bulk deletion can target one campus or all campuses', function () {
    $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    foreach (range(1, 8) as $number) {
        deletionTestLog();
    }
    $other = deletionTestLog('SLSU-Main');
    $this->actingAs($user)->get('/admin/logs')->assertOk()
        ->assertSee('All campuses')->assertSee('Confirm Delete');
    $this->delete(route('admin.logs.delete-all'), ['campus' => 'SLSU-TO'])
        ->assertRedirect(route('admin.logs'));
    expect(PhishingLog::count())->toBe(1);
    $this->assertDatabaseHas('phishing_logs', ['id' => $other->id]);
    $this->delete(route('admin.logs.delete-all'), ['campus' => 'all'])->assertRedirect();
    expect(PhishingLog::count())->toBe(0);
    $this->delete(route('admin.logs.delete-all'), ['campus' => 'all'])->assertRedirect();
});

test('bulk deletion validates campus and enforces campus boundaries', function () {
    $own = deletionTestLog();
    $other = deletionTestLog('SLSU-Main');
    $user = User::factory()->create(['role' => 'campus_admin', 'campus_name' => 'SLSU-TO', 'is_active' => true]);
    $this->actingAs($user)->get('/admin/logs')->assertViewHas('deletionCampuses', ['SLSU-TO']);
    foreach ([[], ['campus' => 'unknown'], ['campus' => 'SLSU-Main']] as $data) {
        $this->delete(route('admin.logs.delete-all'), $data)->assertSessionHasErrors('campus');
        expect(PhishingLog::count())->toBe(2);
    }
    $this->delete(route('admin.logs.delete-all'), ['campus' => 'all'])->assertRedirect();
    $this->assertDatabaseMissing('phishing_logs', ['id' => $own->id]);
    $this->assertDatabaseHas('phishing_logs', ['id' => $other->id]);
});

test('bulk deletion rejects guests and unauthorized users', function () {
    $log = deletionTestLog();
    $this->delete(route('admin.logs.delete-all'), ['campus' => 'all'])->assertRedirect('/login');
    foreach ([['role' => 'user', 'is_active' => true], ['role' => 'admin', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes))
            ->delete(route('admin.logs.delete-all'), ['campus' => 'all'])->assertForbidden();
    }
    $this->assertDatabaseHas('phishing_logs', ['id' => $log->id]);
});
