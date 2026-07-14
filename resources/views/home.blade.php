@extends('layouts.home-template', ['title' => 'Phish Block Monitoring System'])

@section('body')
    <main class="main" id="top">
        <nav class="navbar navbar-expand-lg navbar-light fixed-top py-5 d-block" data-navbar-on-scroll="data-navbar-on-scroll">
            <div class="container">
                <a class="navbar-brand" href="{{ route('home') }}">
                    <span class="fw-bold fs-3 text-dark">Phish Block</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse border-top border-lg-0 mt-4 mt-lg-0" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto pt-2 pt-lg-0 font-base align-items-lg-center align-items-start">
                        <li class="nav-item px-3 px-xl-4"><a class="nav-link fw-medium" href="#service">Features</a></li>
                        <li class="nav-item px-3 px-xl-4"><a class="nav-link fw-medium" href="#destination">Preview</a></li>
                        <li class="nav-item px-3 px-xl-4"><a class="nav-link fw-medium" href="#booking">Workflow</a></li>
                        <li class="nav-item px-3 px-xl-4"><a class="nav-link fw-medium" href="#testimonial">Feedback</a></li>
                        <li class="nav-item px-3 px-xl-4"><a class="nav-link fw-medium" href="{{ route('login') }}">Login</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <section style="padding-top: 7rem;">
            <div class="bg-holder" style="background-image:url({{ asset('home/public/assets/img/hero/hero-bg.svg') }});"></div>
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-5 col-lg-6 order-0 order-md-1 text-end">
                        <img class="pt-7 pt-md-0 hero-img" src="{{ asset('home/public/assets/img/hero/hero-img.png') }}" alt="hero-header" />
                    </div>
                    <div class="col-md-7 col-lg-6 text-md-start text-center py-6">
                        <h4 class="fw-bold text-danger mb-3">SLSU Security Monitoring Platform</h4>
                        <h1 class="hero-title">Monitor risky URLs, campus devices, and phishing decisions from one place.</h1>
                        <p class="mb-4 fw-medium">
                            This frontend-first monitoring system gives administrators a clear home page, static login, and dashboard-ready experience while backend integration is still in progress.
                        </p>
                        <div class="text-center text-md-start">
                            <a class="btn btn-primary btn-lg me-md-4 mb-3 mb-md-0 border-0 primary-btn-shadow" href="{{ route('login') }}" role="button">Open Admin Login</a>
                            <div class="w-100 d-block d-md-none"></div>
                            <a href="#destination" role="button">
                                <span class="btn btn-danger round-btn-lg rounded-circle me-3 danger-btn-shadow">
                                    <img src="{{ asset('home/public/assets/img/hero/play.svg') }}" width="15" alt="play"/>
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
                <div class="position-absolute z-index--1 end-0 d-none d-lg-block">
                    <img src="{{ asset('home/public/assets/img/category/shape.svg') }}" style="max-width: 200px" alt="service" />
                </div>
                <div class="mb-7 text-center">
                    <h5 class="text-secondary">CATEGORY</h5>
                    <h3 class="fs-xl-10 fs-lg-8 fs-7 fw-bold font-cursive text-capitalize">Core Monitoring Features</h3>
                </div>
                <div class="row">
                    @foreach ($features as $index => $feature)
                        <div class="col-lg-4 col-sm-6 mb-6">
                            <div class="card service-card shadow-hover rounded-3 text-center align-items-center h-100">
                                <div class="card-body p-xxl-5 p-4">
                                    <img src="{{ asset('home/public/assets/img/category/icon'.(($index % 4) + 1).'.png') }}" width="75" alt="Service" />
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
                <div class="position-absolute start-100 bottom-0 translate-middle-x d-none d-xl-block ms-xl-n4">
                    <img src="{{ asset('home/public/assets/img/dest/shape.svg') }}" alt="destination" />
                </div>
                <div class="mb-7 text-center">
                    <h5 class="text-secondary">Top Modules</h5>
                    <h3 class="fs-xl-10 fs-lg-8 fs-7 fw-bold font-cursive text-capitalize">Admin Panel Preview</h3>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card overflow-hidden shadow h-100">
                            <img class="card-img-top" src="{{ asset('home/public/assets/img/dest/dest1.jpg') }}" alt="Dashboard" />
                            <div class="card-body py-4 px-3">
                                <div class="d-flex flex-column flex-lg-row justify-content-between mb-3">
                                    <h4 class="text-secondary fw-medium">Dashboard</h4><span class="fs-1 fw-medium">{{ number_format($summary['total_scans']) }}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <img src="{{ asset('home/public/assets/img/dest/navigation.svg') }}" style="margin-right: 14px" width="20" alt="navigation" />
                                    <span class="fs-0 fw-medium">Central overview and KPI monitoring</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card overflow-hidden shadow h-100">
                            <img class="card-img-top" src="{{ asset('home/public/assets/img/dest/dest2.jpg') }}" alt="Logs" />
                            <div class="card-body py-4 px-3">
                                <div class="d-flex flex-column flex-lg-row justify-content-between mb-3">
                                    <h4 class="text-secondary fw-medium">Logs</h4><span class="fs-1 fw-medium">{{ count($records) }}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <img src="{{ asset('home/public/assets/img/dest/navigation.svg') }}" style="margin-right: 14px" width="20" alt="navigation" />
                                    <span class="fs-0 fw-medium">Sample URL records with ML score columns</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card overflow-hidden shadow h-100">
                            <img class="card-img-top" src="{{ asset('home/public/assets/img/dest/dest3.jpg') }}" alt="Analytics" />
                            <div class="card-body py-4 px-3">
                                <div class="d-flex flex-column flex-lg-row justify-content-between mb-3">
                                    <h4 class="text-secondary fw-medium">Analytics</h4><span class="fs-1 fw-medium">{{ $summary['blocked_today'] }}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <img src="{{ asset('home/public/assets/img/dest/navigation.svg') }}" style="margin-right: 14px" width="20" alt="navigation" />
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
                            <h5 class="text-secondary">Easy and Fast</h5>
                            <h3 class="fs-xl-10 fs-lg-8 fs-7 fw-bold font-cursive text-capitalize">Deploy your monitoring UI in 3 easy steps</h3>
                        </div>
                        <div class="d-flex align-items-start mb-5">
                            <div class="bg-primary me-sm-4 me-3 p-3" style="border-radius: 13px">
                                <img src="{{ asset('home/public/assets/img/steps/selection.svg') }}" width="22" alt="steps" />
                            </div>
                            <div class="flex-1">
                                <h5 class="text-secondary fw-bold fs-0">Review Home & Login</h5>
                                <p>Start with a public homepage and a static admin login flow for faster UI validation.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-5">
                            <div class="bg-danger me-sm-4 me-3 p-3" style="border-radius: 13px">
                                <img src="{{ asset('home/public/assets/img/steps/water-sport.svg') }}" width="22" alt="steps" />
                            </div>
                            <div class="flex-1">
                                <h5 class="text-secondary fw-bold fs-0">Validate Monitoring Modules</h5>
                                <p>Preview dashboard, logs, analytics, alerts, campuses, and settings before connecting backend data.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-5">
                            <div class="bg-info me-sm-4 me-3 p-3" style="border-radius: 13px">
                                <img src="{{ asset('home/public/assets/img/steps/taxi.svg') }}" width="22" alt="steps" />
                            </div>
                            <div class="flex-1">
                                <h5 class="text-secondary fw-bold fs-0">Integrate Real Detection Data</h5>
                                <p>Replace dummy entries with live records once APIs and campus collectors are ready.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 d-flex justify-content-center align-items-start">
                        <div class="card position-relative shadow" style="max-width: 370px;">
                            <div class="position-absolute z-index--1 me-10 me-xxl-0" style="right:-160px;top:-210px;">
                                <img src="{{ asset('home/public/assets/img/steps/bg.png') }}" style="max-width:550px;" alt="shape" />
                            </div>
                            <div class="card-body p-3">
                                <img class="mb-4 mt-2 rounded-2 w-100" src="{{ asset('home/public/assets/img/steps/booking-img.jpg') }}" alt="booking" />
                                <div>
                                    <h5 class="fw-medium">Static Demo Access</h5>
                                    <p class="fs--1 mb-3 fw-medium">Credential | admin@gmail.com</p>
                                    <div class="icon-group mb-4">
                                        <span class="btn icon-item"><img src="{{ asset('home/public/assets/img/steps/leaf.svg') }}" alt=""/></span>
                                        <span class="btn icon-item"><img src="{{ asset('home/public/assets/img/steps/map.svg') }}" alt=""/></span>
                                        <span class="btn icon-item"><img src="{{ asset('home/public/assets/img/steps/send.svg') }}" alt=""/></span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center mt-n1">
                                            <img class="me-3" src="{{ asset('home/public/assets/img/steps/building.svg') }}" width="18" alt="building" />
                                            <span class="fs--1 fw-medium">{{ $summary['active_campuses'] }} active campuses</span>
                                        </div>
                                        <a class="btn" href="{{ route('login') }}">
                                            <img src="{{ asset('home/public/assets/img/steps/heart.svg') }}" width="20" alt="step" />
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
                            <h3 class="fs-xl-10 fs-lg-8 fs-7 fw-bold font-cursive text-capitalize">Sample detections for UI preview.</h3>
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
                                                    <img class="rounded-circle fit-cover" src="{{ asset('home/public/assets/img/testimonial/author'.($loop->index === 0 ? '' : $loop->index + 1).'.png') }}" height="65" width="65" alt="" />
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

        <div class="position-relative pt-9 pt-lg-8 pb-6 pb-lg-8">
            <div class="container">
                <div class="row row-cols-lg-5 row-cols-md-3 row-cols-2 flex-center">
                    @for ($i = 1; $i <= 5; $i++)
                        <div class="col">
                            <div class="card shadow-hover mb-4" style="border-radius:10px;">
                                <div class="card-body text-center">
                                    <img class="img-fluid" src="{{ asset('home/public/assets/img/partner/'.$i.'.png') }}" alt="" />
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <section class="pt-6">
            <div class="container">
                <div class="py-8 px-5 position-relative text-center" style="background-color: rgba(223, 215, 249, 0.199);border-radius: 129px 20px 20px 20px;">
                    <div class="position-absolute start-100 top-0 translate-middle ms-md-n3 ms-n4 mt-3">
                        <img src="{{ asset('home/public/assets/img/cta/send.png') }}" style="max-width:70px;" alt="send icon" />
                    </div>
                    <div class="position-absolute end-0 top-0 z-index--1">
                        <img src="{{ asset('home/public/assets/img/cta/shape-bg2.png') }}" width="264" alt="cta shape" />
                    </div>
                    <div class="position-absolute start-0 bottom-0 ms-3 z-index--1 d-none d-sm-block">
                        <img src="{{ asset('home/public/assets/img/cta/shape-bg1.png') }}" style="max-width: 340px;" alt="cta shape" />
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            <h2 class="text-secondary lh-1-7 mb-7">Access the admin panel, preview the full UI, and continue building the phishing monitoring backend when you’re ready.</h2>
                            <form class="row g-3 align-items-center w-lg-75 mx-auto">
                                <div class="col-sm">
                                    <div class="input-group-icon">
                                        <input class="form-control form-little-squirrel-control" type="text" value="admin@gmail.com / admin" aria-label="email" readonly />
                                        <img class="input-box-icon" src="{{ asset('home/public/assets/img/cta/mail.svg') }}" width="17" alt="mail" />
                                    </div>
                                </div>
                                <div class="col-sm-auto">
                                    <a class="btn btn-danger orange-gradient-btn fs--1" href="{{ route('login') }}">Login Now</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="pb-0 pb-lg-4">
            <div class="container">
                <div class="row">
                    <div class="col-lg-3 col-md-7 col-12 mb-4 mb-md-6 mb-lg-0 order-0">
                        <h3 class="mb-4 fw-bold">Phish Block</h3>
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
                            <li class="mb-2"><span class="link-900 fs-1 fw-medium text-decoration-none">admin@gmail.com</span></li>
                            <li class="mb-2"><span class="link-900 fs-1 fw-medium text-decoration-none">Password: admin</span></li>
                        </ul>
                    </div>
                    <div class="col-lg-3 col-md-5 col-12 mb-4 mb-md-6 mb-lg-0 order-lg-4 order-md-1">
                        <div class="icon-group mb-4">
                            <a class="text-decoration-none icon-item shadow-social" id="facebook" href="#!"><i class="fab fa-facebook-f"></i></a>
                            <a class="text-decoration-none icon-item shadow-social" id="instagram" href="#!"><i class="fab fa-instagram"></i></a>
                            <a class="text-decoration-none icon-item shadow-social" id="twitter" href="#!"><i class="fab fa-twitter"></i></a>
                        </div>
                        <h4 class="fw-medium font-sans-serif text-secondary mb-3">Open the admin interface</h4>
                        <div class="d-flex align-items-center">
                            <a href="{{ route('login') }}"><img class="me-2" src="{{ asset('home/public/assets/img/play-store.png') }}" alt="play store" /></a>
                            <a href="{{ route('login') }}"><img src="{{ asset('home/public/assets/img/apple-store.png') }}" alt="apple store" /></a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="py-5 text-center">
            <p class="mb-0 text-secondary fs--1 fw-medium">All rights reserved @ Phish Block Monitoring System</p>
        </div>
    </main>
@endsection
