<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login', [
            'defaultEmail' => '',
            'defaultPassword' => '',
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'The provided credentials do not match an active account.',
                ]);
        }

        $request->session()->regenerate();

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Welcome back.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'You have been logged out.');
    }
}
