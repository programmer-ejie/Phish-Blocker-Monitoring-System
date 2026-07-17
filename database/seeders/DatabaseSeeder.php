<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Campus;
use App\Models\PhishingLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'phisblocksystem@gmail.com'], [
            'name' => 'System Administrator',
            'password' => Hash::make('@Agoylo031989'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $campusRenames = [
            'SLSU-HC' => ['code' => 'SLSU-Hinunangan', 'name' => 'SLSU Hinunangan'],
            'SLSU-LU' => ['code' => 'SLSU-Maasin', 'name' => 'SLSU Maasin'],
            'SLSU-BR' => ['code' => 'SLSU-Bontoc', 'name' => 'SLSU Bontoc'],
        ];
        foreach ($campusRenames as $oldCode => $replacement) {
            $legacy = Campus::where('code', $oldCode)->first();
            $canonical = Campus::where('code', $replacement['code'])->first();

            if ($legacy && $canonical) {
                PhishingLog::where('campus_id', $legacy->id)->update(['campus_id' => $canonical->id]);
                Alert::where('campus_id', $legacy->id)->update(['campus_id' => $canonical->id]);
                $legacy->delete();
            } elseif ($legacy) {
                $legacy->update($replacement);
            }

            PhishingLog::where('campus_name', $oldCode)->update(['campus_name' => $replacement['code']]);
        }

        $campusRows = [
            ['code' => 'SLSU-TO', 'name' => 'SLSU Tomas Oppus', 'computer_count' => 24, 'uptime_percentage' => 99.7, 'status' => 'Healthy', 'is_active' => true, 'last_sync_at' => now()->subMinutes(3)],
            ['code' => 'SLSU-Main', 'name' => 'SLSU Main Campus', 'computer_count' => 30, 'uptime_percentage' => 99.8, 'status' => 'Healthy', 'is_active' => true, 'last_sync_at' => now()->subMinutes(2)],
            ['code' => 'SLSU-Hinunangan', 'name' => 'SLSU Hinunangan', 'computer_count' => 18, 'uptime_percentage' => 98.9, 'status' => 'Healthy', 'is_active' => true, 'last_sync_at' => now()->subMinutes(6)],
            ['code' => 'SLSU-Maasin', 'name' => 'SLSU Maasin', 'computer_count' => 20, 'uptime_percentage' => 99.1, 'status' => 'Healthy', 'is_active' => true, 'last_sync_at' => now()->subMinute()],
            ['code' => 'SLSU-Bontoc', 'name' => 'SLSU Bontoc', 'computer_count' => 15, 'uptime_percentage' => 97.8, 'status' => 'Warning', 'is_active' => true, 'last_sync_at' => now()->subMinutes(9)],
        ];
        $campuses = collect($campusRows)->mapWithKeys(function (array $row): array {
            $campus = Campus::updateOrCreate(['code' => $row['code']], $row);

            return [$campus->code => $campus];
        });

        $logs = [
            ['facebook.com', .6858, 'Low', 'Proceed', 'Low ML score (0.69)', 1, 'SLSU-TO'],
            ['student-portal-slsu.site', .9212, 'Critical', 'Block', 'Credential harvesting pattern detected', 4, 'SLSU-Hinunangan'],
            ['mail.google.com', .1714, 'Safe', 'Proceed', 'Trusted domain and low anomaly score', 2, 'SLSU-TO'],
            ['update-campus-verification.net', .8441, 'High', 'Block', 'Domain age and redirect chain are suspicious', 9, 'SLSU-Maasin'],
            ['onedrive.live.com', .3022, 'Safe', 'Proceed', 'Recognized service with low phishing confidence', 12, 'SLSU-Bontoc'],
            ['office-365-login-helpdesk.co', .8825, 'High', 'Block', 'Brand spoofing keyword cluster matched', 7, 'SLSU-TO'],
            ['youtube.com', .2149, 'Safe', 'Proceed', 'Legitimate high-reputation domain', 5, 'SLSU-Hinunangan'],
            ['payroll-reset-alerts.com', .7911, 'Medium', 'Suspicious', 'Suspicious terms detected in URL slug', 3, 'SLSU-Maasin'],
            ['drive.google.com', .2631, 'Safe', 'Proceed', 'Common productivity domain', 11, 'SLSU-Bontoc'],
            ['campus-scholarship-form-login.ru', .9534, 'Critical', 'Block', 'High phishing likelihood with spoofed educational keywords', 6, 'SLSU-TO'],
            ['zoom.us', .1884, 'Safe', 'Proceed', 'Known conferencing domain', 8, 'SLSU-Maasin'],
            ['registrar-document-confirmation.com', .7365, 'Medium', 'Suspicious', 'Unverified academic workflow domain', 10, 'SLSU-Hinunangan'],
        ];
        foreach ($logs as $index => [$url, $score, $risk, $status, $reason, $computer, $campusCode]) {
            $log = PhishingLog::updateOrCreate(['url' => $url], [
                'campus_id' => $campuses[$campusCode]->id,
                'score' => $score,
                'risk_level' => $risk,
                'status' => $status,
                'reason' => $reason,
                'computer_number' => $computer,
                'campus_name' => $campusCode,
                'metadata' => ['source' => 'database-seeder'],
            ]);
            $log->forceFill(['created_at' => now()->subMinutes(60 - ($index * 5))])->save();
        }

        $alerts = [
            ['Credential spoofing spike', 'SLSU-TO', 'Critical', 'SOC Team', 'Investigating'],
            ['Repeated redirect anomaly', 'SLSU-Maasin', 'High', 'Network Admin', 'Monitoring'],
            ['New suspicious domain family', 'SLSU-Hinunangan', 'High', 'Threat Intel', 'Reviewing'],
            ['Policy threshold tuning', 'SLSU-Bontoc', 'Medium', 'System Admin', 'Open'],
        ];
        foreach ($alerts as [$title, $campusCode, $severity, $owner, $status]) {
            Alert::updateOrCreate(['title' => $title], [
                'campus_id' => $campuses[$campusCode]->id,
                'severity' => $severity,
                'owner' => $owner,
                'status' => $status,
            ]);
        }

        $settings = [
            ['Detection Rules', 'Auto-block threshold', '0.85'],
            ['Detection Rules', 'Review threshold', '0.70'],
            ['Detection Rules', 'Safe allow threshold', '0.30'],
            ['Notifications', 'Critical email alerts', 'Enabled'],
            ['Notifications', 'Campus escalation summary', 'Every 30 minutes'],
            ['Notifications', 'Daily digest', '06:00 PM'],
            ['Access Control', 'Default admin account', 'phisblocksystem@gmail.com'],
            ['Access Control', 'Role preset', 'Administrator'],
            ['Access Control', 'Session timeout', '2 hours'],
        ];
        foreach ($settings as $order => [$group, $label, $value]) {
            SystemSetting::updateOrCreate(['group' => $group, 'label' => $label], [
                'value' => $value,
                'sort_order' => $order,
            ]);
        }
    }
}
