<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('phishing_logs')->update([
            'status' => DB::raw("case when risk_level in ('High', 'Critical') then 'Block' when risk_level = 'Medium' then 'Suspicious' else 'Proceed' end"),
        ]);

        if (Schema::hasColumn('phishing_logs', 'action')) {
            Schema::table('phishing_logs', function (Blueprint $table): void {
                $table->dropColumn('action');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('phishing_logs', 'action')) {
            Schema::table('phishing_logs', function (Blueprint $table): void {
                $table->string('action', 50)->nullable();
            });
        }
    }
};
