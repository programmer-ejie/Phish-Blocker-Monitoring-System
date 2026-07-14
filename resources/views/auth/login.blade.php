@extends('layouts.auth-template', ['title' => 'Admin Login'])

@section('body')
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card" style="max-width:420px; width:100%;">
            <div class="card-body p-5">
                <div class="text-center mb-3">
                    <a href="{{ route('home') }}" class="mb-4 d-inline-block">
                        <img src="{{ asset('template/src/assets/images/logo-icon.svg') }}" alt="" width="36">
                        <span class="ms-2 fw-bold fs-3 text-dark align-middle">Phish Block</span>
                    </a>
                    <h1 class="card-title mb-3 h5">Sign in to the monitoring console</h1>
                    <p class="small text-muted mb-4">Use the static admin demo account for now.</p>
                </div>

                @if (session('status'))
                    <div class="alert alert-info small">{{ session('status') }}</div>
                @endif

                <form class="needs-validation mt-3" method="POST" action="{{ route('login.attempt') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $defaultEmail) }}" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label d-flex justify-content-between">
                            <span>Password</span>
                            <span class="small link-primary">Static demo</span>
                        </label>
                        <input id="password" name="password" type="password" class="form-control" value="{{ $defaultPassword }}" required>
                    </div>

                    <div class="alert alert-light border small">
                        <div class="d-flex justify-content-between"><span>Email</span><strong>{{ $defaultEmail }}</strong></div>
                        <div class="d-flex justify-content-between mt-2"><span>Password</span><strong>{{ $defaultPassword }}</strong></div>
                    </div>

                    @error('email')
                        <div class="alert alert-danger small">{{ $message }}</div>
                    @enderror

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input id="remember" class="form-check-input" type="checkbox" checked>
                            <label class="form-check-label small" for="remember">Remember me</label>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100" type="submit">Sign in</button>
                </form>

                <div class="text-center mt-3 small text-muted">
                    Return to <a href="{{ route('home') }}" class="link-primary">homepage</a>
                </div>
            </div>
        </div>
    </div>
@endsection
