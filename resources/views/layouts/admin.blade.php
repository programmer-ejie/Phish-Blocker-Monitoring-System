<!DOCTYPE html>
<html lang="en">
<head>
    <title>{{ $title ?? 'Dashboard' }} | Phish Block Monitoring System</title>
    @include('layouts.template-head')
    @include('layouts.dark-mode')
    <style>
        .sidebar .nav-link {
            border-radius: 0.75rem;
            margin: 0.15rem 1rem;
            transition: all 0.2s ease-in-out;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link.is-current {
            background: #fff0eb !important;
            color: #ef5b35 !important;
            font-weight: 700;
            box-shadow: none;
        }

        .sidebar .nav-link.active .nav-text,
        .sidebar .nav-link.active i,
        .sidebar .nav-link.is-current .nav-text,
        .sidebar .nav-link.is-current i {
            color: #ef5b35 !important;
        }

        .sidebar .nav-link.active i,
        .sidebar .nav-link.is-current i {
            background: transparent;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(241, 91, 42, 0.06);
            color: #f15a2b;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
        }

        .sidebar-nav {
            flex: 1;
            min-height: 0;
        }

        .sidebar-section-label {
            color: #6c757d;
            font-weight: 600;
        }

        .sidebar-account {
            margin-top: auto;
            padding-bottom: 1rem;
        }

        .sidebar-account form {
            margin: 0;
        }

        .sidebar-account .nav-link {
            width: calc(100% - 2rem);
            border: 0;
            background: #dc3545;
            color: #fff;
            font-weight: 600;
            text-align: left;
        }

        .sidebar-account .nav-link:hover,
        .sidebar-account .nav-link:focus {
            background: #bb2d3b;
            color: #fff;
        }

        .sidebar-account .nav-link i,
        .sidebar-account .nav-link .nav-text {
            color: inherit;
        }

        .sidebar.collapsed .sidebar-account > .sidebar-section-label {
            display: none;
        }

        .sidebar.collapsed .sidebar-account .nav-link {
            display: flex;
            width: 44px;
            height: 44px;
            margin: 0 auto !important;
            padding: 0 !important;
            justify-content: center;
            border-radius: 0.7rem;
            background: #dc3545 !important;
            color: #fff !important;
        }

        .sidebar.collapsed .sidebar-account .nav-link:hover,
        .sidebar.collapsed .sidebar-account .nav-link:focus {
            background: #bb2d3b !important;
        }

        .admin-profile-menu {
            min-width: 250px;
            overflow: hidden;
            border-radius: 0.85rem;
        }

        .profile-menu-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0.75rem;
            border-radius: 0.6rem;
            color: inherit;
            font-weight: 500;
            line-height: 1.4;
            transition: color 0.2s ease, background-color 0.2s ease;
        }

        .profile-menu-link i {
            width: 1.15rem;
            color: #ef5b35;
            font-size: 1.15rem;
            text-align: center;
        }

        .profile-menu-link:hover,
        .profile-menu-link:focus {
            color: #ef5b35;
            background: rgba(239, 91, 53, 0.1);
        }

        .profile-logout-button {
            display: flex;
            width: 100%;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.75rem;
            border: 0;
            border-radius: 0.6rem;
            color: #fff;
            background: #dc3545;
            font-weight: 600;
            line-height: 1.4;
            text-align: left;
            transition: background-color 0.2s ease;
        }

        .profile-logout-button:hover,
        .profile-logout-button:focus {
            color: #fff;
            background: #bb2d3b;
        }

        .profile-logout-button i {
            width: 1.15rem;
            font-size: 1.15rem;
            text-align: center;
        }
    </style>
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
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-md p-0">
                        <ul class="list-unstyled p-0 m-0">
                            <li class="p-3 border-bottom">
                                <div class="d-flex gap-3">
                                    <img src="{{ asset('template/dist/assets/images/avatar-1.jpg') }}" alt="" class="avatar avatar-sm rounded-circle" />
                                    <div class="flex-grow-1 small">
                                        <p class="mb-0">Critical phishing spike detected</p>
                                        <p class="mb-1">SLSU-TO has multiple blocked URLs</p>
                                        <div class="text-secondary">5 minutes ago</div>
                                    </div>
                                </div>
                            </li>
                            <li class="p-3 border-bottom">
                                <div class="d-flex gap-3">
                                    <img src="{{ asset('template/dist/assets/images/avatar-4.jpg') }}" alt="" class="avatar avatar-sm rounded-circle" />
                                    <div class="flex-grow-1 small">
                                        <p class="mb-0">Review queue updated</p>
                                        <p class="mb-1">{{ $summary['review_queue'] }} items waiting for analyst review</p>
                                        <div class="text-secondary">30 minutes ago</div>
                                    </div>
                                </div>
                            </li>
                            <li class="px-4 py-3 text-center">
                                <a href="{{ route('admin.alerts') }}" class="text-primary">View all notifications</a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="ms-3 dropdown">
                    <a href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="{{ asset('template/dist/assets/images/avatar-1.jpg') }}" alt="" class="avatar avatar-sm rounded-circle" />
                    </a>
                    <div class="admin-profile-menu dropdown-menu dropdown-menu-end p-0">
                        <div>
                            <div class="d-flex gap-3 align-items-center border-dashed border-bottom px-3 py-3">
                                <img src="{{ asset('template/dist/assets/images/avatar-1.jpg') }}" alt="" class="avatar avatar-md rounded-circle" />
                                <div>
                                    <h4 class="mb-0 small">{{ $adminUser['name'] ?? 'System Administrator' }}</h4>
                                    <p class="mb-0 small">{{ $adminUser['email'] ?? 'admin@gmail.com' }}</p>
                                </div>
                            </div>
                            <div class="p-3 d-flex flex-column gap-1 small">
                                <a class="profile-menu-link" href="{{ route('admin.dashboard') }}"><i class="ti ti-home" aria-hidden="true"></i><span>Dashboard</span></a>
                                <a class="profile-menu-link" href="{{ route('admin.analytics') }}"><i class="ti ti-chart-bar" aria-hidden="true"></i><span>Analytics</span></a>
                                <a class="profile-menu-link" href="{{ route('admin.logs') }}"><i class="ti ti-list-details" aria-hidden="true"></i><span>Logs</span></a>
                                <form class="mt-2 pt-2 border-top" method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="profile-logout-button"><i class="ti ti-logout" aria-hidden="true"></i><span>Logout</span></button>
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
        <ul class="nav flex-column sidebar-nav">
            @foreach ($navItems as $item)
                @php($isActive = $activePage === $item['key'])
                @if ($item['key'] === 'dashboard')
                    <li class="px-4 py-2 sidebar-section-label"><small class="nav-text">Main</small></li>
                @elseif ($item['key'] === 'logs')
                    <li class="px-4 pt-4 pb-2 sidebar-section-label"><small class="nav-text">Monitoring</small></li>
                @elseif ($item['key'] === 'campuses')
                    <li class="px-4 pt-4 pb-2 sidebar-section-label"><small class="nav-text">Administration</small></li>
                @endif
                <li>
                    <a class="nav-link {{ $isActive ? 'is-current' : '' }}" href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif>
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
            <li class="sidebar-account">
                <div class="px-4 pt-4 pb-2 sidebar-section-label"><small class="nav-text">Account</small></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-link" aria-label="Logout" title="Logout">
                        <i class="ti ti-logout"></i>
                        <span class="nav-text">Logout</span>
                    </button>
                </form>
            </li>
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
