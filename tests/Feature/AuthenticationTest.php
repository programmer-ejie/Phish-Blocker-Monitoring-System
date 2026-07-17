<?php

use App\Models\User;

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
