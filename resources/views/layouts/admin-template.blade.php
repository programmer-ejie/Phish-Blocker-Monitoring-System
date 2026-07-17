<!DOCTYPE html>
<html lang="en">
<head>
    <title>{{ $title ?? 'Dashboard' }} | Phish Block Monitoring System</title>
    @include('layouts.template-head')
    @include('layouts.dark-mode')
</head>
<body>
    <div id="overlay" class="overlay"></div>

    <nav id="topbar" class="navbar bg-white border-bottom fixed-top topbar px-3">
        <button id="toggleBtn" class="d-none d-lg-inline-flex btn btn-light btn-icon btn-sm">
            <i class="ti ti-layout-sidebar-left-expand"></i>
        </button>

        <button id="mobileBtn" class="btn btn-light btn-icon btn-sm d-lg-none me-2">
            <i class="ti ti-layout-sidebar-left-expand"></i>
        </button>

        <div>
            <ul class="list-unstyled d-flex align-items-center mb-0 gap-1">
                <li class="me-2">
                    @include('layouts.theme-toggle')
                </li>
                <li>
                    <a class="position-relative btn-icon btn-sm btn-light btn rounded-circle" data-bs-toggle="dropdown" aria-expanded="false" href="#" role="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bell">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
                            <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
                        </svg>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger mt-2 ms-n2">
                            {{ $summary['critical_incidents'] }}
                            <span class="visually-hidden">alerts</span>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-md p-0">
                        <ul class="list-unstyled p-0 m-0">
                            @forelse (($notifications ?? []) as $notification)
                                <li class="p-3 border-bottom">
                                    <div class="d-flex gap-3">
                                        <img src="{{ asset('images/slsu.webp') }}" alt="Southern Leyte State University seal" class="avatar avatar-sm rounded-circle bg-white p-1" />
                                        <div class="flex-grow-1 small">
                                            <p class="mb-0 fw-medium">{{ $notification['title'] }}</p>
                                            <p class="mb-1">{{ $notification['campus'] }} · {{ $notification['severity'] }} · {{ $notification['status'] }}</p>
                                            <div class="text-secondary">{{ $notification['time'] }}</div>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="px-4 py-4 text-center small text-secondary">No alerts to display.</li>
                            @endforelse
                            <li class="px-4 py-3 text-center">
                                <a href="{{ route('admin.alerts') }}" class="text-primary">View all notifications</a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="ms-3 dropdown">
                    <a href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="{{ asset('images/admin-profile.webp') }}" alt="Administrator profile" class="avatar avatar-sm rounded-circle" />
                    </a>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 220px;">
                        <div>
                            <div class="d-flex gap-3 align-items-center border-dashed border-bottom px-3 py-3">
                                <img src="{{ asset('images/admin-profile.webp') }}" alt="Administrator profile" class="avatar avatar-md rounded-circle" />
                                <div>
                                    <h4 class="mb-0 small">{{ $adminUser['name'] ?? 'System Administrator' }}</h4>
                                    <p class="mb-0 small">{{ $adminUser['email'] ?? 'admin@gmail.com' }}</p>
                                </div>
                            </div>
                            <div class="p-3 d-flex flex-column gap-1 small lh-lg">
                                <a href="{{ route('admin.dashboard') }}"><span>Dashboard</span></a>
                                <a href="{{ route('admin.analytics') }}"><span>Analytics</span></a>
                                <a href="{{ route('admin.settings') }}"><span>Settings</span></a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-link p-0 text-start text-decoration-none">Logout</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <aside id="sidebar" class="sidebar">
        <div class="logo-area">
            <a href="{{ route('admin.dashboard') }}" class="d-inline-flex align-items-center">
                <img src="{{ asset('images/branding/phish-block-logo.png') }}" alt="Phish Block logo" width="36" height="36">
                <span class="logo-text ms-2 fw-bold fs-4 text-dark">Phish Block</span>
            </a>
        </div>
        <ul class="nav flex-column">
            <li class="px-4 py-2"><small class="nav-text">Main</small></li>
            @foreach ($navItems as $item)
                <li>
                    <a class="nav-link {{ $activePage === $item['key'] ? 'active' : '' }}" href="{{ route($item['route']) }}">
                        <i class="ti {{ match($item['key']) {
                            'dashboard' => 'ti-home',
                            'logs' => 'ti-list-details',
                            'analytics' => 'ti-chart-bar',
                            'alerts' => 'ti-alert-circle',
                            'campuses' => 'ti-building-community',
                            default => 'ti-settings',
                        } }}"></i>
                        <span class="nav-text">{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
            <li class="px-4 pt-4 pb-2"><small class="nav-text">Account</small></li>
            <li><a class="nav-link" href="{{ route('login') }}"><i class="ti ti-login"></i><span class="nav-text">Log in</span></a></li>
        </ul>
    </aside>

    <main id="content" class="content py-10">
        <div class="container-fluid">
            @yield('content')
            <div class="row">
                <div class="col-12">
                    <footer class="text-center py-2 mt-6 text-secondary">
                        <p class="mb-0">Copyright © {{ now()->year }} Phish Block Monitoring System. SLSU phishing monitoring and response platform.</p>
                    </footer>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
