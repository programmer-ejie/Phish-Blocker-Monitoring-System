<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(file_get_contents(
                base_path('supabase/migrations/20260717000000_ensure_phishing_logs.sql')
            ));

            return;
        }

        Schema::create('phishing_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campus_id')->nullable();
            $table->text('url');
            $table->double('score')->nullable();
            $table->string('risk_level');
            $table->string('status', 50);
            $table->text('reason')->nullable();
            $table->unsignedInteger('computer_number')->nullable();
            $table->string('campus_name', 100)->nullable();
            $table->json('metadata')->default('{}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phishing_logs');
    }
};
