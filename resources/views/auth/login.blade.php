@extends('layouts.auth-template', ['title' => 'Admin Login'])

@section('body')
    <style>
        .login-header {
            position: relative;
            z-index: 10;
            height: 76px;
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid #eef0f3;
            box-shadow: 0 4px 18px rgba(20, 33, 61, 0.05);
        }

        .login-header .navbar {
            height: 100%;
        }

        .login-header .navbar-brand {
            font-family: 'Poppins', sans-serif;
        }

        .login-header .nav-link {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: #202737;
            border-radius: 0.7rem;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.25;
            padding: 0.65rem 0.9rem !important;
            transition: color 0.2s ease, background-color 0.2s ease;
        }

        .login-header .nav-link:hover,
        .login-header .nav-link:focus,
        .login-header .nav-link.active {
            color: #ef5b35;
            background: rgba(239, 91, 53, 0.1);
        }

        .login-header .nav-link i {
            width: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }

        @media (max-width: 991.98px) {
            .login-header .navbar-collapse {
                position: absolute;
                top: 76px;
                right: 0;
                left: 0;
                padding: 0.75rem 1.25rem 1rem;
                background: #fff;
                border-bottom: 1px solid #eef0f3;
                box-shadow: 0 10px 18px rgba(20, 33, 61, 0.08);
            }
        }

        .login-page {
            min-height: calc(100vh - 76px);
            padding: 1.25rem;
            background: #f7f9fc;
        }

        .login-shell {
            width: 100%;
            max-width: 1320px;
            max-height: 680px;
            overflow: hidden;
            border: 1px solid #e8ebf0;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 18px 50px rgba(20, 33, 61, 0.1);
        }

        .login-form-panel {
            padding: clamp(1.5rem, 3vw, 3rem);
        }

        .login-form-wrap {
            width: 100%;
            max-width: 480px;
            margin-inline: auto;
        }

        .login-visual-panel {
            min-height: 520px;
            padding: clamp(1.5rem, 3vw, 3rem);
            background: linear-gradient(145deg, #eef5ff 0%, #f7f9ff 58%, #fff4f0 100%);
        }

        .login-visual-copy {
            max-width: 560px;
        }

        .login-visual-panel img {
            width: 100%;
            max-height: 430px;
            object-fit: contain;
            object-position: center;
            border-radius: 0.85rem;
        }

        @media (min-width: 992px) {
            body {
                overflow: hidden;
            }

            .login-page {
                height: calc(100vh - 76px);
                min-height: 0;
            }

            .login-shell {
                height: min(680px, calc(100vh - 108px));
            }
        }

        @media (max-height: 760px) and (min-width: 992px) {
            .login-page {
                padding-block: 0.75rem;
            }

            .login-form-panel,
            .login-visual-panel {
                padding-block: 1.25rem;
            }

            .login-visual-panel img {
                max-height: 330px;
            }
        }

        @media (max-width: 991.98px) {
            .login-shell {
                max-width: 640px;
            }

            .login-visual-panel {
                min-height: 0;
            }
        }
    </style>

    <header class="login-header">
        <nav class="navbar navbar-expand-lg px-3 px-lg-5">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="{{ route('home') }}">
                    <img src="{{ asset('images/branding/phish-block-logo.png') }}" alt="Phish Block logo" width="44" height="44">
                    <span class="ms-2 fw-bold fs-3 text-dark">Phish Block</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#loginNavigation" aria-controls="loginNavigation" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="loginNavigation">
                    <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                        <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#service"><i class="fas fa-shield-alt" aria-hidden="true"></i><span>Features</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#destination"><i class="fas fa-chart-line" aria-hidden="true"></i><span>Preview</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#booking"><i class="fas fa-project-diagram" aria-hidden="true"></i><span>Workflow</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#testimonial"><i class="fas fa-comment-dots" aria-hidden="true"></i><span>Feedback</span></a></li>
                        <li class="nav-item d-flex align-items-center">@include('layouts.theme-toggle')</li>
                        <li class="nav-item"><a class="nav-link active" href="{{ route('login') }}" aria-current="page"><i class="fas fa-user-lock" aria-hidden="true"></i><span>Login</span></a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="login-page container-fluid d-flex align-items-center justify-content-center px-md-5">
        <div class="login-shell row g-0">
            <section class="login-form-panel col-lg-5 d-flex align-items-center" aria-labelledby="login-title">
                <div class="login-form-wrap">
                    <div class="mb-4">
                        <span class="badge bg-primary-subtle text-primary mb-3">Secure administrator access</span>
                        <h1 id="login-title" class="h3 mb-2">Welcome back</h1>
                        <p class="text-muted mb-0">Sign in to monitor phishing activity and protect every connected campus.</p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-info small">{{ session('status') }}</div>
                    @endif

                    <form class="needs-validation" method="POST" action="{{ route('login.attempt') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $defaultEmail) }}" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input id="password" name="password" type="password" class="form-control" value="{{ $defaultPassword }}" required>
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
            </section>

            <section class="login-visual-panel col-lg-7 d-flex flex-column justify-content-center">
                <div class="login-visual-copy mb-3">
                    <p class="text-primary fw-semibold text-uppercase small mb-2">One protected network</p>
                    <h2 class="h3 mb-2">Stop phishing before it reaches your campuses.</h2>
                    <p class="text-muted mb-0">See threats, connected devices, and security decisions in one clear monitoring workspace.</p>
                </div>
                <img src="{{ asset('images/branding/login-security-illustration.webp') }}" alt="Campus devices protected from phishing threats by the Phish Block monitoring system">
            </section>
        </div>
    </main>
@endsection
