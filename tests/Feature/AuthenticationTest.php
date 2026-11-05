<?php

use App\Models\User;

test('guests can open the login form', function () {
    $this->get('/login')->assertOk()->assertViewIs('auth.login');
});

test('signed-in administrators opening login are sent to the admin dashboard', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($user)->get('/login')->assertRedirect('/admin/dashboard');
});

test('guests are redirected away from the admin dashboard', function () {
    $this->get('/admin/dashboard')->assertRedirect('/login');
});

test('an active administrator can log in and see the database dashboard', function () {
    User::create([
        'name' => 'System Administrator',
        'email' => 'admin@example.test',
        'password' => 'secure-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->post('/login', [
        'email' => 'admin@example.test',
        'password' => 'secure-password',
    ])->assertRedirect('/admin/dashboard');

    $this->get('/admin/dashboard')->assertOk();
});

test('invalid credentials do not create an authenticated session', function () {
    $this->post('/login', [
        'email' => 'missing@example.test',
        'password' => 'incorrect-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
