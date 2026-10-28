<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('device_uuid');
            $table->string('campus_name', 30);
            $table->unsignedInteger('computer_number');
            $table->string('name', 80);
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['campus_name', 'device_uuid']);
            $table->unique(['campus_name', 'computer_number']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE devices ENABLE ROW LEVEL SECURITY');
        }
        Schema::table('phishing_logs', function (Blueprint $table): void {
            $table->foreignId('device_id')->nullable()->constrained('devices');
        });
    }

    public function down(): void
    {
        Schema::table('phishing_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('device_id');
        });
        Schema::dropIfExists('devices');
    }
};
