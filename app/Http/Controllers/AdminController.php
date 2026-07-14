<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function home(): View
    {
        return view('home', [
            'summary' => $this->buildSummary(),
            'records' => collect($this->monitoringRecords())->take(5)->all(),
            'features' => [
                [
                    'title' => 'Real-Time URL Screening',
                    'description' => 'Monitor submitted URLs, ML scores, risk levels, and response decisions from every connected campus endpoint.',
                ],
                [
                    'title' => 'Campus-Wide Visibility',
                    'description' => 'Track activity by campus, computer number, and detection trends so admins can spot suspicious behavior faster.',
                ],
                [
                    'title' => 'Response-Ready Admin Panel',
                    'description' => 'Review logs, alert spikes, risk summaries, and operational settings from a single security dashboard.',
                ],
            ],
        ]);
    }

    public function dashboard(Request $request): View
    {
        return view('admin.dashboard', $this->adminViewData('dashboard', [
            'campusBreakdown' => $this->campusBreakdown(),
            'alerts' => $this->alertQueue(),
        ], $request));
    }

    public function logs(Request $request): View
    {
        return view('admin.logs', $this->adminViewData('logs', [], $request));
    }

    public function analytics(Request $request): View
    {
        return view('admin.analytics', $this->adminViewData('analytics', [
            'hourlyTraffic' => $this->hourlyTraffic(),
            'riskDistribution' => $this->riskDistribution(),
        ], $request));
    }

    public function alerts(Request $request): View
    {
        return view('admin.alerts', $this->adminViewData('alerts', [
            'alerts' => $this->alertQueue(),
        ], $request));
    }

    public function campuses(Request $request): View
    {
        return view('admin.campuses', $this->adminViewData('campuses', [
            'campuses' => $this->campusOperations(),
        ], $request));
    }

    public function settings(Request $request): View
    {
        return view('admin.settings', $this->adminViewData('settings', [
            'settingsGroups' => $this->settingsGroups(),
        ], $request));
    }

    private function adminViewData(string $activePage, array $extra, Request $request): array
    {
        return array_merge([
            'activePage' => $activePage,
            'summary' => $this->buildSummary(),
            'records' => $this->monitoringRecords(),
            'adminUser' => $request->session()->get('admin_user', []),
            'navItems' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'key' => 'dashboard'],
                ['label' => 'Logs', 'route' => 'admin.logs', 'key' => 'logs'],
                ['label' => 'Analytics', 'route' => 'admin.analytics', 'key' => 'analytics'],
                ['label' => 'Alerts', 'route' => 'admin.alerts', 'key' => 'alerts'],
                ['label' => 'Campuses', 'route' => 'admin.campuses', 'key' => 'campuses'],
                ['label' => 'Settings', 'route' => 'admin.settings', 'key' => 'settings'],
            ],
        ], $extra);
    }

    private function monitoringRecords(): array
    {
        return [
            ['url' => 'facebook.com', 'score' => 0.6858, 'risk_level' => 'Low', 'status' => 'Proceed', 'reason' => 'Low ML score (0.69)', 'computer_number' => 1, 'campus_name' => 'SLSU-TO', 'time' => '08:12 AM', 'action' => 'Allowed'],
            ['url' => 'student-portal-slsu.site', 'score' => 0.9212, 'risk_level' => 'Critical', 'status' => 'Blocked', 'reason' => 'Credential harvesting pattern detected', 'computer_number' => 4, 'campus_name' => 'SLSU-HC', 'time' => '08:19 AM', 'action' => 'Quarantined'],
            ['url' => 'mail.google.com', 'score' => 0.1714, 'risk_level' => 'Safe', 'status' => 'Proceed', 'reason' => 'Trusted domain and low anomaly score', 'computer_number' => 2, 'campus_name' => 'SLSU-TO', 'time' => '08:25 AM', 'action' => 'Allowed'],
            ['url' => 'update-campus-verification.net', 'score' => 0.8441, 'risk_level' => 'High', 'status' => 'Review', 'reason' => 'Domain age and redirect chain are suspicious', 'computer_number' => 9, 'campus_name' => 'SLSU-LU', 'time' => '08:33 AM', 'action' => 'Pending review'],
            ['url' => 'onedrive.live.com', 'score' => 0.3022, 'risk_level' => 'Safe', 'status' => 'Proceed', 'reason' => 'Recognized service with low phishing confidence', 'computer_number' => 12, 'campus_name' => 'SLSU-BR', 'time' => '08:40 AM', 'action' => 'Allowed'],
            ['url' => 'office-365-login-helpdesk.co', 'score' => 0.8825, 'risk_level' => 'High', 'status' => 'Blocked', 'reason' => 'Brand spoofing keyword cluster matched', 'computer_number' => 7, 'campus_name' => 'SLSU-TO', 'time' => '08:43 AM', 'action' => 'Blocked'],
            ['url' => 'youtube.com', 'score' => 0.2149, 'risk_level' => 'Safe', 'status' => 'Proceed', 'reason' => 'Legitimate high-reputation domain', 'computer_number' => 5, 'campus_name' => 'SLSU-HC', 'time' => '08:48 AM', 'action' => 'Allowed'],
            ['url' => 'payroll-reset-alerts.com', 'score' => 0.7911, 'risk_level' => 'Medium', 'status' => 'Review', 'reason' => 'Suspicious terms detected in URL slug', 'computer_number' => 3, 'campus_name' => 'SLSU-LU', 'time' => '08:55 AM', 'action' => 'Escalated'],
            ['url' => 'drive.google.com', 'score' => 0.2631, 'risk_level' => 'Safe', 'status' => 'Proceed', 'reason' => 'Common productivity domain', 'computer_number' => 11, 'campus_name' => 'SLSU-BR', 'time' => '09:03 AM', 'action' => 'Allowed'],
            ['url' => 'campus-scholarship-form-login.ru', 'score' => 0.9534, 'risk_level' => 'Critical', 'status' => 'Blocked', 'reason' => 'High phishing likelihood with spoofed educational keywords', 'computer_number' => 6, 'campus_name' => 'SLSU-TO', 'time' => '09:11 AM', 'action' => 'Quarantined'],
            ['url' => 'zoom.us', 'score' => 0.1884, 'risk_level' => 'Safe', 'status' => 'Proceed', 'reason' => 'Known conferencing domain', 'computer_number' => 8, 'campus_name' => 'SLSU-LU', 'time' => '09:18 AM', 'action' => 'Allowed'],
            ['url' => 'registrar-document-confirmation.com', 'score' => 0.7365, 'risk_level' => 'Medium', 'status' => 'Review', 'reason' => 'Unverified academic workflow domain', 'computer_number' => 10, 'campus_name' => 'SLSU-HC', 'time' => '09:23 AM', 'action' => 'Pending review'],
        ];
    }

    private function buildSummary(): array
    {
        return [
            'total_scans' => 1284,
            'blocked_today' => 94,
            'review_queue' => 18,
            'active_campuses' => 4,
            'safe_rate' => 82,
            'critical_incidents' => 7,
        ];
    }

    private function campusBreakdown(): array
    {
        return [
            ['name' => 'SLSU-TO', 'scans' => 418, 'blocked' => 38, 'ratio' => 92],
            ['name' => 'SLSU-HC', 'scans' => 286, 'blocked' => 17, 'ratio' => 71],
            ['name' => 'SLSU-LU', 'scans' => 321, 'blocked' => 24, 'ratio' => 79],
            ['name' => 'SLSU-BR', 'scans' => 259, 'blocked' => 15, 'ratio' => 63],
        ];
    }

    private function hourlyTraffic(): array
    {
        return [
            ['hour' => '07:00', 'requests' => 46],
            ['hour' => '08:00', 'requests' => 73],
            ['hour' => '09:00', 'requests' => 91],
            ['hour' => '10:00', 'requests' => 88],
            ['hour' => '11:00', 'requests' => 67],
            ['hour' => '12:00', 'requests' => 54],
            ['hour' => '01:00', 'requests' => 61],
        ];
    }

    private function riskDistribution(): array
    {
        return [
            ['label' => 'Safe', 'count' => 812, 'tone' => 'success'],
            ['label' => 'Low', 'count' => 203, 'tone' => 'info'],
            ['label' => 'Medium', 'count' => 161, 'tone' => 'warning'],
            ['label' => 'High', 'count' => 72, 'tone' => 'danger'],
            ['label' => 'Critical', 'count' => 36, 'tone' => 'danger'],
        ];
    }

    private function alertQueue(): array
    {
        return [
            ['title' => 'Credential spoofing spike', 'campus' => 'SLSU-TO', 'severity' => 'Critical', 'owner' => 'SOC Team', 'status' => 'Investigating'],
            ['title' => 'Repeated redirect anomaly', 'campus' => 'SLSU-LU', 'severity' => 'High', 'owner' => 'Network Admin', 'status' => 'Monitoring'],
            ['title' => 'New suspicious domain family', 'campus' => 'SLSU-HC', 'severity' => 'High', 'owner' => 'Threat Intel', 'status' => 'Reviewing'],
            ['title' => 'Policy threshold tuning', 'campus' => 'SLSU-BR', 'severity' => 'Medium', 'owner' => 'System Admin', 'status' => 'Scheduled'],
        ];
    }

    private function campusOperations(): array
    {
        return [
            ['name' => 'SLSU-TO', 'computers' => 24, 'last_sync' => '3 mins ago', 'uptime' => '99.7%', 'status' => 'Healthy'],
            ['name' => 'SLSU-HC', 'computers' => 18, 'last_sync' => '6 mins ago', 'uptime' => '98.9%', 'status' => 'Healthy'],
            ['name' => 'SLSU-LU', 'computers' => 20, 'last_sync' => '1 min ago', 'uptime' => '99.1%', 'status' => 'Healthy'],
            ['name' => 'SLSU-BR', 'computers' => 15, 'last_sync' => '9 mins ago', 'uptime' => '97.8%', 'status' => 'Warning'],
        ];
    }

    private function settingsGroups(): array
    {
        return [
            [
                'title' => 'Detection Rules',
                'items' => [
                    ['label' => 'Auto-block threshold', 'value' => '0.85'],
                    ['label' => 'Review threshold', 'value' => '0.70'],
                    ['label' => 'Safe allow threshold', 'value' => '0.30'],
                ],
            ],
            [
                'title' => 'Notifications',
                'items' => [
                    ['label' => 'Critical email alerts', 'value' => 'Enabled'],
                    ['label' => 'Campus escalation summary', 'value' => 'Every 30 minutes'],
                    ['label' => 'Daily digest', 'value' => '06:00 PM'],
                ],
            ],
            [
                'title' => 'Access Control',
                'items' => [
                    ['label' => 'Default admin account', 'value' => 'admin@gmail.com'],
                    ['label' => 'Role preset', 'value' => 'Security Operations Lead'],
                    ['label' => 'Session timeout', 'value' => '8 hours'],
                ],
            ],
        ];
    }
}
