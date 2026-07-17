<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Campus;
use App\Models\PhishingLog;
use App\Models\SystemSetting;
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
            'hourlyTraffic' => $this->hourlyTraffic(),
            'statusDistribution' => $this->statusDistribution(),
        ], $request));
    }

    public function logs(Request $request): View
    {
        return view('admin.logs', $this->adminViewData('logs', [
            'records' => PhishingLog::query()->latest()->paginate(6)->withQueryString()->through(fn (PhishingLog $log) => [
                'url' => $log->url,
                'score' => $log->score ?? 0,
                'risk_level' => $log->risk_level,
                'status' => $log->status,
                'reason' => $log->reason,
                'computer_number' => $log->computer_number,
                'campus_name' => $log->campus_name,
                'time' => $log->created_at?->format('h:i A') ?? '—',
            ]),
        ], $request));
    }

    public function analytics(Request $request): View
    {
        return view('admin.analytics', $this->adminViewData('analytics', [
            'hourlyTraffic' => $this->hourlyTraffic(),
            'riskDistribution' => $this->riskDistribution(),
            'campusAnalytics' => $this->campusBreakdown(),
        ], $request));
    }

    public function alerts(Request $request): View
    {
        return view('admin.alerts', $this->adminViewData('alerts', [
            'alertSummary' => $this->alertSummary(),
            'alerts' => Alert::with('campus')->latest()->paginate(5)->withQueryString()->through(fn (Alert $alert) => [
                'title' => $alert->title,
                'campus' => $alert->campus?->code ?? 'System-wide',
                'severity' => $alert->severity,
                'owner' => $alert->owner,
                'status' => $alert->status,
            ]),
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
            'notifications' => $this->notifications(),
            'adminUser' => $request->user()?->only(['name', 'email', 'role']) ?? [],
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
        return PhishingLog::query()->latest()->limit(100)->get()->map(fn (PhishingLog $log) => [
            'url' => $log->url,
            'score' => $log->score ?? 0,
            'risk_level' => $log->risk_level,
            'status' => $log->status,
            'reason' => $log->reason,
            'computer_number' => $log->computer_number,
            'campus_name' => $log->campus_name,
            'time' => $log->created_at?->format('h:i A') ?? '—',
        ])->all();
    }

    private function buildSummary(): array
    {
        $total = PhishingLog::count();
        $safe = PhishingLog::whereIn('risk_level', ['Safe', 'Low'])->count();

        return [
            'total_scans' => $total,
            'blocked_today' => PhishingLog::whereDate('created_at', today())->where('status', 'Block')->count(),
            'review_queue' => PhishingLog::where('status', 'Suspicious')->count(),
            'active_campuses' => Campus::where('is_active', true)->count(),
            'safe_rate' => $total ? (int) round(($safe / $total) * 100) : 0,
            'critical_incidents' => Alert::where('severity', 'Critical')->whereNotIn('status', ['Resolved', 'Closed'])->count(),
        ];
    }

    private function campusBreakdown(): array
    {
        $logs = PhishingLog::get(['campus_name', 'status']);

        return Campus::orderBy('code')->get()->map(function (Campus $campus) use ($logs): array {
            $campusLogs = $logs->where('campus_name', $campus->code);
            $scans = $campusLogs->count();
            $blocked = $campusLogs->where('status', 'Block')->count();
            $suspicious = $campusLogs->where('status', 'Suspicious')->count();
            $proceed = $campusLogs->where('status', 'Proceed')->count();
            $threatRate = $scans ? (($blocked + $suspicious) / $scans) * 100 : 0;
            $healthScore = max(0, min(100, (int) round((float) $campus->uptime_percentage - ($threatRate * 0.35))));
            $health = $healthScore >= 95 ? 'Healthy' : ($healthScore >= 85 ? 'Watch' : 'At Risk');

            return [
                'name' => $campus->code,
                'scans' => $scans,
                'blocked' => $blocked,
                'suspicious' => $suspicious,
                'proceed' => $proceed,
                'computers' => $campus->computer_count,
                'uptime' => (float) $campus->uptime_percentage,
                'last_sync' => $campus->last_sync_at?->diffForHumans() ?? 'Never',
                'health_score' => $healthScore,
                'threat_rate' => (int) round($threatRate),
                'proceed_percentage' => $scans ? (int) round(($proceed / $scans) * 100) : 0,
                'suspicious_percentage' => $scans ? (int) round(($suspicious / $scans) * 100) : 0,
                'blocked_percentage' => $scans ? (int) round(($blocked / $scans) * 100) : 0,
                'health' => $health,
                'tone' => $health === 'Healthy' ? 'success' : ($health === 'Watch' ? 'warning' : 'danger'),
            ];
        })->all();
    }

    private function hourlyTraffic(): array
    {
        $logs = PhishingLog::where('created_at', '>=', now()->subHours(6)->startOfHour())->get();

        $traffic = collect(range(6, 0))->map(function (int $hoursAgo) use ($logs): array {
            $hour = now()->subHours($hoursAgo)->startOfHour();

            return [
                'hour' => $hour->format('H:00'),
                'requests' => $logs->filter(fn (PhishingLog $log) => $log->created_at?->between($hour, $hour->copy()->endOfHour()))->count(),
            ];
        });
        $max = max(1, (int) $traffic->max('requests'));

        return $traffic->map(fn (array $point) => $point + [
            'percentage' => max(4, (int) round(($point['requests'] / $max) * 100)),
        ])->all();
    }

    private function statusDistribution(): array
    {
        $counts = PhishingLog::selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $total = max(1, (int) $counts->sum());

        return collect([
            'Proceed' => ['tone' => 'success', 'color' => '#16a34a'],
            'Suspicious' => ['tone' => 'warning', 'color' => '#f59e0b'],
            'Block' => ['tone' => 'danger', 'color' => '#ef4444'],
        ])->map(function (array $style, string $status) use ($counts, $total): array {
            $count = (int) ($counts[$status] ?? 0);

            return [
                'label' => $status,
                'count' => $count,
                'percentage' => (int) round(($count / $total) * 100),
                ...$style,
            ];
        })->values()->all();
    }

    private function riskDistribution(): array
    {
        $tones = ['Safe' => 'success', 'Low' => 'info', 'Medium' => 'warning', 'High' => 'danger', 'Critical' => 'danger'];
        $counts = PhishingLog::selectRaw('risk_level, count(*) as aggregate')->groupBy('risk_level')->pluck('aggregate', 'risk_level');

        return collect($tones)->map(fn (string $tone, string $label) => [
            'label' => $label, 'count' => (int) ($counts[$label] ?? 0), 'tone' => $tone,
        ])->values()->all();
    }

    private function alertQueue(): array
    {
        return Alert::with('campus')->latest()->get()->map(fn (Alert $alert) => [
            'title' => $alert->title,
            'campus' => $alert->campus?->code ?? 'System-wide',
            'severity' => $alert->severity,
            'owner' => $alert->owner,
            'status' => $alert->status,
        ])->all();
    }

    private function notifications(): array
    {
        return Alert::with('campus')->latest()->limit(2)->get()->map(fn (Alert $alert) => [
            'title' => $alert->title,
            'campus' => $alert->campus?->code ?? 'System-wide',
            'severity' => $alert->severity,
            'status' => $alert->status,
            'time' => $alert->created_at?->diffForHumans() ?? 'Recently',
        ])->all();
    }

    private function alertSummary(): array
    {
        return [
            'total' => Alert::count(),
            'priority' => Alert::whereIn('severity', ['Critical', 'High'])->count(),
            'active' => Alert::whereNotIn('status', ['Resolved', 'Closed'])->count(),
            'campuses' => Alert::whereNotNull('campus_id')->distinct('campus_id')->count('campus_id'),
        ];
    }

    private function campusOperations(): array
    {
        return Campus::orderBy('code')->get()->map(fn (Campus $campus) => [
            'name' => $campus->code,
            'computers' => $campus->computer_count,
            'last_sync' => $campus->last_sync_at?->diffForHumans() ?? 'Never',
            'uptime' => number_format((float) $campus->uptime_percentage, 1).'%',
            'status' => $campus->status,
        ])->all();
    }

    private function settingsGroups(): array
    {
        return SystemSetting::orderBy('sort_order')->get()->groupBy('group')->map(fn ($items, string $group) => [
            'title' => $group,
            'items' => $items->map->only(['label', 'value'])->all(),
        ])->values()->all();
    }
}
