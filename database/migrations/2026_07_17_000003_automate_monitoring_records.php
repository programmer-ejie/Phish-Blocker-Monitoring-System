<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(file_get_contents(
            base_path('supabase/migrations/20260717000001_automate_monitoring_data.sql')
        ));
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            drop trigger if exists create_monitoring_alert_after_insert on public.phishing_logs;
            drop trigger if exists register_monitoring_campus_before_insert on public.phishing_logs;
            drop function if exists public.create_monitoring_alert();
            drop function if exists public.register_monitoring_campus();
        SQL);
    }
};
