<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const EMAIL = 'admin@gmail.com';
    private const PASSWORD = 'admin';

    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('is_admin_authenticated')) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login', [
            'defaultEmail' => self::EMAIL,
            'defaultPassword' => self::PASSWORD,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (
            $credentials['email'] !== self::EMAIL ||
            $credentials['password'] !== self::PASSWORD
        ) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Invalid credentials. Use admin@gmail.com and admin for the static demo login.',
                ]);
        }

        $request->session()->put('is_admin_authenticated', true);
        $request->session()->put('admin_user', [
            'name' => 'System Administrator',
            'email' => self::EMAIL,
            'role' => 'Security Operations Lead',
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Welcome back. Static admin access granted.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['is_admin_authenticated', 'admin_user']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'You have been logged out.');
    }
}
