<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('logs:apply-trusted-policy {--apply : Apply changes; otherwise preview only}', function () {
    $policy = json_decode(file_get_contents(base_path('extension-setup/api/trusted_urls.json')), true, 512, JSON_THROW_ON_ERROR);
    $matches = \App\Models\PhishingLog::whereIn('status', ['Block', 'Suspicious'])->get()->filter(function ($log) use ($policy) {
        $url = $log->url;
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443) || str_contains($url, '\\') || preg_match('/[\x00-\x20]/', $url)) {
            return false;
        }
        // Do not relabel known threat-intelligence matches.
        if (str_contains($log->reason ?? '', 'PhishTank') || str_contains($log->reason ?? '', 'Google Safe Browsing')) {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $canonical = 'https://'.$host.$path.(isset($parts['query']) ? '?'.$parts['query'] : '');

        return in_array($host, $policy['trusted_hosts'], true)
            || in_array($path, $policy['trusted_paths'][$host] ?? [], true)
            || in_array($canonical, $policy['trusted_urls'], true);
    });
    $this->info($matches->count().' historical logs match the trusted policy.');
    if (! $this->option('apply') || $matches->isEmpty()) {
        return;
    }
    $backup = 'log-policy-backups/'.now()->format('Ymd-His').'-'.\Illuminate\Support\Str::uuid().'.json';
    if (! \Illuminate\Support\Facades\Storage::disk('local')->put($backup, $matches->values()->toJson(JSON_PRETTY_PRINT))) {
        throw new \RuntimeException('Backup failed; no logs were changed.');
    }
    \Illuminate\Support\Facades\DB::transaction(function () use ($matches, $policy) {
        foreach ($matches as $candidate) {
            $log = \App\Models\PhishingLog::lockForUpdate()->findOrFail($candidate->id);
            $metadata = $log->metadata ?? [];
            $metadata['trusted_policy_original'] ??= [
                'status' => $log->status, 'risk_level' => $log->risk_level,
                'reason' => $log->reason, 'score' => $log->score,
            ];
            $metadata['trusted_policy_version'] = $policy['version'];
            $metadata['trusted_policy_applied_at'] = now()->toIso8601String();
            $log->update(['status' => 'Proceed', 'risk_level' => 'Low',
                'reason' => 'Trusted URL override: approved campus policy; original ML score retained',
                'metadata' => $metadata]);
        }
    });
    $this->info('Updated '.$matches->count().' logs. Backup: '.\Illuminate\Support\Facades\Storage::disk('local')->path($backup));
})->purpose('Preview or apply reviewed trusted URL rules to historical logs, preserving original verdicts');
