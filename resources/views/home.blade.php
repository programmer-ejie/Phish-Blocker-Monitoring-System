@extends('layouts.home-template', ['title' => 'Phish Block Monitoring System'])

@section('body')
    <style>
        .public-header-nav {
            height: 76px;
            padding-block: 0 !important;
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid #eef0f3;
            box-shadow: 0 4px 18px rgba(20, 33, 61, 0.05);
        }

        .public-brand {
            display: inline-flex;
            align-items: center;
            font-family: 'Poppins', sans-serif;
        }

        .site-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.65rem 0.9rem !important;
            border-radius: 0.7rem;
            color: #202737 !important;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            font-weight: 600 !important;
            line-height: 1.25;
            transition: color 0.2s ease, background-color 0.2s ease;
        }

        .site-nav-link:hover,
        .site-nav-link:focus,
        .site-nav-link.active {
            color: #ef5b35 !important;
            background: rgba(239, 91, 53, 0.1);
        }

        .site-nav-link i {
            width: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .phish-hero-image {
            width: 100%;
            border-radius: 1.5rem;
            box-shadow: 0 24px 65px rgba(21, 33, 61, 0.14);
        }

        .landing-hero {
            padding-top: 5.5rem;
            padding-bottom: 2.5rem;
        }

        .public-page-shell { display: flex; min-height: 100vh; min-height: 100dvh; padding-bottom: 68px; flex-direction: column; }
        .site-footer { position: fixed; right: 0; bottom: 0; left: 0; z-index: 1025; margin: 0; padding: 0.95rem 1.5rem; border-top: 1px solid #e4e7ec; background: rgba(255, 255, 255, 0.95); box-shadow: 0 -8px 24px rgba(16, 24, 40, 0.06); color: #667085; backdrop-filter: blur(14px); }
        .site-footer__inner { display: flex; align-items: center; justify-content: center; gap: 0.65rem; font-family: 'Poppins', sans-serif; font-size: 0.82rem; line-height: 1.5; text-align: center; }
        .site-footer__brand { color: #344054; font-weight: 650; }
        .site-footer__separator { width: 3px; height: 3px; border-radius: 50%; background: #98a2b3; }

        .landing-hero .hero-title {
            font-size: clamp(3rem, 5.2vw, 5.5rem);
            line-height: 1.06;
        }

        .system-icon-panel {
            display: grid;
            min-height: 220px;
            place-items: center;
            background: linear-gradient(145deg, #eef5ff, #f8faff);
        }

        .system-icon-panel i {
            color: #2563eb;
            font-size: 4.25rem;
        }

        .module-preview-image {
            display: block;
            width: 100%;
            aspect-ratio: 2.15 / 1;
            object-fit: cover;
            object-position: top center;
            background: var(--pb-surface-soft);
        }

        .feature-system-icon {
            display: inline-grid;
            width: 76px;
            height: 76px;
            margin-bottom: 1.25rem;
            place-items: center;
            border-radius: 1.25rem;
            color: #2563eb;
            background: #eef5ff;
            font-size: 1.8rem;
        }

        @media (max-width: 991.98px) {
            .public-header-nav .navbar-collapse {
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

        @media (max-width: 767.98px) {
            .landing-hero {
                padding-top: 6.25rem;
            }

            .landing-hero .hero-title {
                font-size: clamp(2.5rem, 12vw, 4rem);
            }

            .public-page-shell { padding-bottom: 88px; }
            .site-footer { padding: 0.8rem 1rem; }
            .site-footer__inner { flex-wrap: wrap; gap: 0.25rem 0.5rem; }
            .site-footer__description { width: 100%; }
        }
    </style>

    <main class="main public-page-shell" id="top">
        <nav class="public-header-nav navbar navbar-expand-lg navbar-light fixed-top px-3 px-lg-5">
            <div class="container-fluid">
                <a class="public-brand navbar-brand" href="{{ route('home') }}">
                    <img src="{{ asset('images/branding/phish-block-logo.png') }}" alt="Phish Block logo" width="44" height="44" class="me-2">
                    <span class="fw-bold fs-3 text-dark">Phish Block</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse border-top border-lg-0 mt-4 mt-lg-0" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto pt-2 pt-lg-0 align-items-lg-center align-items-start gap-lg-3">
                        <li class="nav-item"><a class="nav-link site-nav-link active" data-section="service" href="#service"><i class="fas fa-shield-alt" aria-hidden="true"></i><span>Features</span></a></li>
                        <li class="nav-item"><a class="nav-link site-nav-link" data-section="destination" href="#destination"><i class="fas fa-chart-line" aria-hidden="true"></i><span>Preview</span></a></li>
                        <li class="nav-item"><a class="nav-link site-nav-link" data-section="booking" href="#booking"><i class="fas fa-project-diagram" aria-hidden="true"></i><span>Workflow</span></a></li>
                        <li class="nav-item"><a class="nav-link site-nav-link" data-section="testimonial" href="#testimonial"><i class="fas fa-comment-dots" aria-hidden="true"></i><span>Feedback</span></a></li>
                        <li class="nav-item d-flex align-items-center">@include('layouts.theme-toggle')</li>
                        <li class="nav-item"><a class="nav-link site-nav-link" href="{{ route('login') }}"><i class="fas fa-user-lock" aria-hidden="true"></i><span>Login</span></a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <section class="landing-hero">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-5 col-lg-6 order-0 order-md-1">
                        <img class="phish-hero-image" src="{{ asset('images/branding/landing-security-hero.webp') }}" alt="Phish Block monitoring dashboard protecting connected campus devices from phishing threats" />
                    </div>
                    <div class="col-md-7 col-lg-6 text-md-start text-center py-3">
                        <h4 class="fw-bold text-danger mb-3">SLSU Phishing Protection</h4>
                        <h1 class="hero-title">Stop phishing across every campus.</h1>
                        <p class="mb-4 fw-medium">
                            Phish Block gives administrators real-time visibility into suspicious URLs, endpoint activity, risk levels, alerts, and campus-wide protection from one secure console.
                        </p>
                        <div class="text-center text-md-start">
                            <a class="btn btn-primary btn-lg me-md-4 mb-3 mb-md-0 border-0 primary-btn-shadow" href="{{ route('login') }}" role="button">Open Admin Login</a>
                            <div class="w-100 d-block d-md-none"></div>
                            <a href="#destination" role="button">
                                <span class="btn btn-danger round-btn-lg rounded-circle me-3 danger-btn-shadow">
                                    <i class="fas fa-chart-line" aria-hidden="true"></i>
                                </span>
                            </a>
                            <span class="fw-medium">View UI Preview</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="pt-5 pt-md-9" id="service">
            <div class="container">
                <div class="mb-7 text-center">
                    <h5 class="text-secondary">Platform Capabilities</h5>
                    <h3 class="fs-xl-10 fs-lg-8 fs-7 fw-bold font-cursive text-capitalize">Core Monitoring Features</h3>
                </div>
                <div class="row">
                    @foreach ($features as $index => $feature)
                        <div class="col-lg-4 col-sm-6 mb-6">
                            <div class="card service-card shadow-hover rounded-3 text-center align-items-center h-100">
                                <div class="card-body p-xxl-5 p-4">
                                    <span class="feature-system-icon"><i class="fas {{ ['fa-link', 'fa-shield-alt', 'fa-chart-bar', 'fa-bell', 'fa-university', 'fa-file-alt'][$index % 6] }}" aria-hidden="true"></i></span>
                                    <h4 class="mb-3">{{ $feature['title'] }}</h4>
                                    <p class="mb-0 fw-medium">{{ $feature['description'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="pt-5" id="destination">
            <div class="container">
                <div class="mb-7 text-center">
                    <h5 class="text-secondary">Top Modules</h5>
                    <h3 class="fs-xl-10 fs-lg-8 fs-7 fw-bold font-cursive text-capitalize">Admin Panel Preview</h3>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card overflow-hidden shadow h-100">
                            <img class="module-preview-image" data-theme-image src="{{ asset('images/landing_page/dark-dashboard.webp') }}" data-light-src="{{ asset('images/landing_page/dark-dashboard.webp') }}" data-dark-src="{{ asset('images/landing_page/light-dashboard.webp') }}" alt="Phish Block dashboard preview">
                            <div class="card-body py-4 px-3">
                                <div class="d-flex flex-column flex-lg-row justify-content-between mb-3">
                                    <h4 class="text-secondary fw-medium">Dashboard</h4><span class="fs-1 fw-medium">{{ number_format($summary['total_scans']) }}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-eye text-primary me-3" aria-hidden="true"></i>
                                    <span class="fs-0 fw-medium">Central overview and KPI monitoring</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card overflow-hidden shadow h-100">
                            <img class="module-preview-image" data-theme-image src="{{ asset('images/landing_page/light-logs.webp') }}" data-light-src="{{ asset('images/landing_page/light-logs.webp') }}" data-dark-src="{{ asset('images/landing_page/dark-logs.webp') }}" alt="Phish Block monitoring logs preview">
                            <div class="card-body py-4 px-3">
                                <div class="d-flex flex-column flex-lg-row justify-content-between mb-3">
                                    <h4 class="text-secondary fw-medium">Logs</h4><span class="fs-1 fw-medium">{{ count($records) }}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-search text-primary me-3" aria-hidden="true"></i>
                                    <span class="fs-0 fw-medium">Sample URL records with ML score columns</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card overflow-hidden shadow h-100">
                            <img class="module-preview-image" data-theme-image src="{{ asset('images/landing_page/light-analytics.webp') }}" data-light-src="{{ asset('images/landing_page/light-analytics.webp') }}" data-dark-src="{{ asset('images/landing_page/dark-analytics.webp') }}" alt="Phish Block analytics preview">
                            <div class="card-body py-4 px-3">
                                <div class="d-flex flex-column flex-lg-row justify-content-between mb-3">
                                    <h4 class="text-secondary fw-medium">Analytics</h4><span class="fs-1 fw-medium">{{ $summary['blocked_today'] }}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-chart-line text-primary me-3" aria-hidden="true"></i>
                                    <span class="fs-0 fw-medium">Operational trends and risk breakdowns</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="booking">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <div class="mb-4 text-start">
                            <h5 class="text-secondary">Protection Workflow</h5>
                            <h3 class="fs-xl-10 fs-lg-8 fs-7 fw-bold font-cursive text-capitalize">From suspicious URL to informed response</h3>
                        </div>
                        <div class="d-flex align-items-start mb-5">
                            <div class="bg-primary me-sm-4 me-3 p-3" style="border-radius: 13px">
                                <i class="fas fa-link text-white" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1">
                                <h5 class="text-secondary fw-bold fs-0">Capture URL Activity</h5>
                                <p>Collect suspicious links and endpoint events from connected campus devices.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-5">
                            <div class="bg-danger me-sm-4 me-3 p-3" style="border-radius: 13px">
                                <i class="fas fa-microscope text-white" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1">
                                <h5 class="text-secondary fw-bold fs-0">Analyze and Classify Risk</h5>
                                <p>Evaluate detection signals, risk scores, and incident context in a centralized review flow.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-5">
                            <div class="bg-info me-sm-4 me-3 p-3" style="border-radius: 13px">
                                <i class="fas fa-ban text-white" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1">
                                <h5 class="text-secondary fw-bold fs-0">Block and Monitor</h5>
                                <p>Stop confirmed threats, notify administrators, and track protection across every campus.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 d-flex justify-content-center align-items-start">
                        <div class="card position-relative shadow" style="max-width: 370px;">
                            <div class="card-body p-3">
                                <img class="mb-4 mt-2 rounded-2 w-100" src="{{ asset('images/branding/landing-security-hero.webp') }}" alt="Campus phishing monitoring and protection workflow" />
                                <div>
                                    <h5 class="fw-medium">Campus Protection Status</h5>
                                    <p class="fs--1 mb-3 fw-medium">Centralized visibility across protected endpoints</p>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center mt-n1">
                                            <i class="fas fa-university text-primary me-3" aria-hidden="true"></i>
                                            <span class="fs--1 fw-medium">{{ $summary['active_campuses'] }} active campuses</span>
                                        </div>
                                        <a class="btn" href="{{ route('login') }}">
                                            <i class="fas fa-arrow-right text-primary" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="testimonial">
            <div class="container">
                <div class="row">
                    <div class="col-lg-5">
                        <div class="mb-8 text-start">
                            <h5 class="text-secondary">Monitoring Feed</h5>
                            <h3 class="fs-xl-10 fs-lg-8 fs-7 fw-bold font-cursive text-capitalize">Recent phishing detections and decisions.</h3>
                        </div>
                    </div>
                    <div class="col-lg-1"></div>
                    <div class="col-lg-6">
                        <div class="pe-7 ps-5 ps-lg-0">
                            <div class="carousel slide carousel-fade position-static" id="testimonialIndicator" data-bs-ride="carousel">
                                <div class="carousel-indicators">
                                    <button class="active" type="button" data-bs-target="#testimonialIndicator" data-bs-slide-to="0" aria-current="true" aria-label="Testimonial 0"></button>
                                    <button type="button" data-bs-target="#testimonialIndicator" data-bs-slide-to="1" aria-label="Testimonial 1"></button>
                                    <button type="button" data-bs-target="#testimonialIndicator" data-bs-slide-to="2" aria-label="Testimonial 2"></button>
                                </div>
                                <div class="carousel-inner">
                                    @foreach (array_slice($records, 0, 3) as $record)
                                        <div class="carousel-item position-relative {{ $loop->first ? 'active' : '' }}">
                                            <div class="card shadow" style="border-radius:10px;">
                                                <div class="position-absolute start-0 top-0 translate-middle">
                                                    <span class="d-inline-grid bg-white rounded-circle shadow p-2"><img src="{{ asset('images/slsu.webp') }}" height="49" width="49" alt="Southern Leyte State University seal" /></span>
                                                </div>
                                                <div class="card-body p-4">
                                                    <p class="fw-medium mb-4">"{{ $record['reason'] }}"</p>
                                                    <h5 class="text-secondary">{{ $record['url'] }}</h5>
                                                    <p class="fw-medium fs--1 mb-0">{{ $record['campus_name'] }} | Computer #{{ $record['computer_number'] }} | {{ $record['risk_level'] }}</p>
                                                </div>
                                            </div>
                                            <div class="card shadow-sm position-absolute top-0 z-index--1 mb-3 w-100 h-100" style="border-radius:10px;transform:translate(25px, 25px)"></div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="carousel-navigation d-flex flex-column flex-between-center position-absolute end-0 top-lg-50 bottom-0 translate-middle-y z-index-1 me-3 me-lg-0" style="height:60px;width:20px;">
                                    <button class="carousel-control-prev position-static" type="button" data-bs-target="#testimonialIndicator" data-bs-slide="prev"><img src="{{ asset('home/public/assets/img/icons/up.svg') }}" width="16" alt="icon" /></button>
                                    <button class="carousel-control-next position-static" type="button" data-bs-target="#testimonialIndicator" data-bs-slide="next"><img src="{{ asset('home/public/assets/img/icons/down.svg') }}" width="16" alt="icon" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="pt-6">
            <div class="container">
                <div class="system-cta py-8 px-5 position-relative text-center" style="background: linear-gradient(135deg, #eef5ff, #fff5f1); border-radius: 32px;">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            <span class="feature-system-icon"><i class="fas fa-shield-alt" aria-hidden="true"></i></span>
                            <h2 class="text-secondary lh-1-7 mb-4">Protect every campus with one phishing monitoring and response workspace.</h2>
                            <p class="text-secondary mb-4">Review detections, investigate risky URLs, monitor endpoints, and coordinate faster security decisions.</p>
                            <a class="btn btn-danger orange-gradient-btn fs--1" href="{{ route('login') }}"><i class="fas fa-user-lock me-2" aria-hidden="true"></i>Open Secure Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="pb-0 pb-lg-4">
            <div class="container">
                <div class="row">
                    <div class="col-lg-3 col-md-7 col-12 mb-4 mb-md-6 mb-lg-0 order-0">
                        <div class="d-flex align-items-center mb-4">
                            <img src="{{ asset('images/branding/phish-block-logo.png') }}" alt="Phish Block logo" width="44" height="44" class="me-2">
                            <h3 class="mb-0 fw-bold">Phish Block</h3>
                        </div>
                        <p class="fs--1 text-secondary mb-0 fw-medium">Secure URL monitoring, campus visibility, and admin response workflows in one system.</p>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-4 mb-lg-0 order-lg-1 order-md-2">
                        <h4 class="footer-heading-color fw-bold font-sans-serif mb-3 mb-lg-4">System</h4>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><a class="link-900 fs-1 fw-medium text-decoration-none" href="#service">Features</a></li>
                            <li class="mb-2"><a class="link-900 fs-1 fw-medium text-decoration-none" href="#destination">Preview</a></li>
                            <li class="mb-2"><a class="link-900 fs-1 fw-medium text-decoration-none" href="#booking">Workflow</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-4 mb-lg-0 order-lg-2 order-md-3">
                        <h4 class="footer-heading-color fw-bold font-sans-serif mb-3 mb-lg-4">Admin</h4>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><a class="link-900 fs-1 fw-medium text-decoration-none" href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                            <li class="mb-2"><a class="link-900 fs-1 fw-medium text-decoration-none" href="{{ route('admin.logs') }}">Logs</a></li>
                            <li class="mb-2"><a class="link-900 fs-1 fw-medium text-decoration-none" href="{{ route('admin.analytics') }}">Analytics</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-4 mb-lg-0 order-lg-3 order-md-4">
                        <h4 class="footer-heading-color fw-bold font-sans-serif mb-3 mb-lg-4">Access</h4>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><a class="link-900 fs-1 fw-medium text-decoration-none" href="{{ route('login') }}">Login</a></li>
                            <li class="mb-2"><span class="link-900 fs-1 fw-medium text-decoration-none">Authorized administrators</span></li>
                            <li class="mb-2"><span class="link-900 fs-1 fw-medium text-decoration-none">Secure session access</span></li>
                        </ul>
                    </div>
                    <div class="col-lg-3 col-md-5 col-12 mb-4 mb-md-6 mb-lg-0 order-lg-4 order-md-1">
                        <h4 class="fw-bold font-sans-serif text-secondary mb-3">System Coverage</h4>
                        <p class="fs--1 text-secondary fw-medium mb-2"><i class="fas fa-university text-primary me-2" aria-hidden="true"></i>{{ $summary['active_campuses'] }} active campuses</p>
                        <p class="fs--1 text-secondary fw-medium mb-2"><i class="fas fa-shield-alt text-primary me-2" aria-hidden="true"></i>{{ number_format($summary['total_scans']) }} URLs monitored</p>
                        <p class="fs--1 text-secondary fw-medium mb-0"><i class="fas fa-ban text-danger me-2" aria-hidden="true"></i>{{ number_format($summary['blocked_today']) }} threats blocked today</p>
                    </div>
                </div>
            </div>
        </section>

        @include('layouts.footer')
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const links = [...document.querySelectorAll('.site-nav-link[data-section]')];
            const sections = links
                .map(link => document.getElementById(link.dataset.section))
                .filter(Boolean);

            const setActiveLink = id => {
                links.forEach(link => link.classList.toggle('active', link.dataset.section === id));
            };

            const observer = new IntersectionObserver(entries => {
                const visible = entries
                    .filter(entry => entry.isIntersecting)
                    .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

                if (visible) setActiveLink(visible.target.id);
            }, { rootMargin: '-25% 0px -55%', threshold: [0.05, 0.25, 0.5] });

            sections.forEach(section => observer.observe(section));
            links.forEach(link => link.addEventListener('click', () => setActiveLink(link.dataset.section)));
        });
    </script>
@endsection
