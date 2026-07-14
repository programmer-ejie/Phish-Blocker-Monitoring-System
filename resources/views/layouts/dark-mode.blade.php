<script>
    (() => {
        const savedTheme = localStorage.getItem('phish-block-theme');
        const preferredTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.dataset.theme = savedTheme || preferredTheme;
        document.documentElement.setAttribute('data-bs-theme', savedTheme || preferredTheme);
    })();
</script>
<style>
    :root {
        --pb-page: #ffffff;
        --pb-surface: #ffffff;
        --pb-surface-soft: #f7f9fc;
        --pb-text: #171c2b;
        --pb-muted: #64748b;
        --pb-border: #e5e7eb;
    }

    html[data-theme="dark"] {
        color-scheme: dark;
        --pb-page: #0b1220;
        --pb-surface: #111b2e;
        --pb-surface-soft: #0f192a;
        --pb-text: #e8edf6;
        --pb-muted: #a9b5c8;
        --pb-border: #26344b;
    }

    * {
        scrollbar-width: thin;
        scrollbar-color: #ef5b35 var(--pb-surface-soft);
    }

    *::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    *::-webkit-scrollbar-track {
        margin: 3px;
        border-radius: 999px;
        background: var(--pb-surface-soft);
    }

    *::-webkit-scrollbar-thumb {
        min-height: 40px;
        border: 2px solid var(--pb-surface-soft);
        border-radius: 999px;
        background: linear-gradient(180deg, #ff7958, #ef5b35);
    }

    *::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, #ef5b35, #d94826);
    }

    *::-webkit-scrollbar-corner {
        background: var(--pb-surface-soft);
    }

    .theme-toggle {
        display: inline-grid;
        width: 42px;
        height: 42px;
        padding: 0;
        place-items: center;
        border: 1px solid var(--pb-border);
        border-radius: 0.75rem;
        color: var(--pb-text);
        background: var(--pb-surface-soft);
        transition: color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
    }

    .theme-toggle:hover,
    .theme-toggle:focus-visible {
        color: #ef5b35;
        background: rgba(239, 91, 53, 0.1);
        transform: translateY(-1px);
    }

    .theme-toggle svg {
        width: 19px;
        height: 19px;
        stroke: currentColor;
    }

    html[data-theme="light"] .theme-icon-sun,
    html[data-theme="dark"] .theme-icon-moon {
        display: none;
    }

    html[data-theme="dark"] body,
    html[data-theme="dark"] .content,
    html[data-theme="dark"] .login-page {
        color: var(--pb-text);
        background-color: var(--pb-page) !important;
    }

    html[data-theme="dark"] .sidebar,
    html[data-theme="dark"] .sidebar .logo-area,
    html[data-theme="dark"] .topbar,
    html[data-theme="dark"] .public-header-nav,
    html[data-theme="dark"] .login-header,
    html[data-theme="dark"] .login-header .navbar-collapse,
    html[data-theme="dark"] .public-header-nav .navbar-collapse,
    html[data-theme="dark"] .card,
    html[data-theme="dark"] .dropdown-menu,
    html[data-theme="dark"] .login-shell,
    html[data-theme="dark"] .list-group-item,
    html[data-theme="dark"] .modal-content,
    html[data-theme="dark"] footer {
        color: var(--pb-text);
        border-color: var(--pb-border) !important;
        background-color: var(--pb-surface) !important;
    }

    html[data-theme="dark"] .text-dark,
    html[data-theme="dark"] .text-secondary,
    html[data-theme="dark"] .footer-heading-color,
    html[data-theme="dark"] .public-header-nav .navbar-brand span,
    html[data-theme="dark"] .login-header .navbar-brand span,
    html[data-theme="dark"] .sidebar .nav-link,
    html[data-theme="dark"] .site-nav-link,
    html[data-theme="dark"] .login-header .nav-link,
    html[data-theme="dark"] h1,
    html[data-theme="dark"] h2,
    html[data-theme="dark"] h3,
    html[data-theme="dark"] h4,
    html[data-theme="dark"] h5,
    html[data-theme="dark"] h6 {
        color: var(--pb-text) !important;
    }

    html[data-theme="dark"] .text-muted,
    html[data-theme="dark"] .sidebar-section-label,
    html[data-theme="dark"] .link-900 {
        color: var(--pb-muted) !important;
    }

    html[data-theme="dark"] .form-control,
    html[data-theme="dark"] .form-select,
    html[data-theme="dark"] .input-group-text {
        color: var(--pb-text);
        border-color: var(--pb-border);
        background-color: #0c1627;
    }

    html[data-theme="dark"] .form-control::placeholder {
        color: #7f8da3 !important;
    }

    html[data-theme="dark"] table,
    html[data-theme="dark"] th,
    html[data-theme="dark"] td,
    html[data-theme="dark"] .table > :not(caption) > * > * {
        color: var(--pb-text);
        border-color: var(--pb-border);
        background-color: var(--pb-surface) !important;
    }

    html[data-theme="dark"] .table-light > :not(caption) > * > *,
    html[data-theme="dark"] thead th {
        background-color: #17243a !important;
    }

    html[data-theme="dark"] .card-header,
    html[data-theme="dark"] .card-footer {
        color: var(--pb-text) !important;
        border-color: var(--pb-border) !important;
        background: #17243a !important;
    }

    html[data-theme="dark"] .card-header h1,
    html[data-theme="dark"] .card-header h2,
    html[data-theme="dark"] .card-header h3,
    html[data-theme="dark"] .card-header h4,
    html[data-theme="dark"] .card-header h5,
    html[data-theme="dark"] .card-header h6,
    html[data-theme="dark"] .card-header .card-title {
        color: #f4f7fb !important;
    }

    html[data-theme="dark"] .sidebar .nav-link.is-current,
    html[data-theme="dark"] .sidebar .nav-link.active {
        color: #ff7655 !important;
        background: rgba(239, 91, 53, 0.16) !important;
        box-shadow: none;
    }

    html[data-theme="dark"] .sidebar .nav-link.is-current .nav-text,
    html[data-theme="dark"] .sidebar .nav-link.is-current i,
    html[data-theme="dark"] .sidebar .nav-link.active .nav-text,
    html[data-theme="dark"] .sidebar .nav-link.active i {
        color: #ff7655 !important;
    }

    html[data-theme="dark"] .btn-light,
    html[data-theme="dark"] .topbar .btn-light {
        color: var(--pb-text) !important;
        border-color: var(--pb-border) !important;
        background: #17243a !important;
    }

    html[data-theme="dark"] .site-nav-link.active,
    html[data-theme="dark"] .login-header .nav-link.active {
        color: #ffffff !important;
        background: #ef5b35 !important;
        box-shadow: 0 7px 18px rgba(239, 91, 53, 0.24);
    }

    html[data-theme="dark"] .site-nav-link.active i,
    html[data-theme="dark"] .site-nav-link.active span,
    html[data-theme="dark"] .login-header .nav-link.active i,
    html[data-theme="dark"] .login-header .nav-link.active span {
        color: #ffffff !important;
    }

    html[data-theme="dark"] .login-visual-panel,
    html[data-theme="dark"] .system-icon-panel {
        background: linear-gradient(145deg, #14233a, #101b2e) !important;
    }

    html[data-theme="dark"] .feature-system-icon {
        background: #172a46;
    }

    html[data-theme="dark"] .system-cta,
    html[data-theme="dark"] .bg-light,
    html[data-theme="dark"] .alert-light {
        color: var(--pb-text) !important;
        background: #142238 !important;
    }

    html[data-theme="dark"] .navbar-toggler {
        border-color: var(--pb-border);
    }

    html[data-theme="dark"] .navbar-toggler-icon {
        filter: invert(1) grayscale(1);
    }

    html[data-theme="dark"] .border,
    html[data-theme="dark"] .border-top,
    html[data-theme="dark"] .border-bottom,
    html[data-theme="dark"] .border-end,
    html[data-theme="dark"] .border-start {
        border-color: var(--pb-border) !important;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggles = document.querySelectorAll('[data-theme-toggle]');

        const applyTheme = theme => {
            document.documentElement.dataset.theme = theme;
            document.documentElement.setAttribute('data-bs-theme', theme);
            localStorage.setItem('phish-block-theme', theme);
            document.querySelectorAll('[data-theme-image]').forEach(image => {
                const nextSource = theme === 'dark' ? image.dataset.darkSrc : image.dataset.lightSrc;
                if (nextSource && image.getAttribute('src') !== nextSource) image.setAttribute('src', nextSource);
            });
            toggles.forEach(toggle => {
                const isDark = theme === 'dark';
                toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
                toggle.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
                toggle.setAttribute('aria-pressed', String(isDark));
            });
        };

        applyTheme(document.documentElement.dataset.theme || 'light');
        toggles.forEach(toggle => toggle.addEventListener('click', () => {
            applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
        }));
    });
</script>
