<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 30)->default('user')->index();
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->index();
            }
        });

        if (! Schema::hasTable('campuses')) {
            Schema::create('campuses', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name');
                $table->unsignedInteger('computer_count')->default(0);
                $table->decimal('uptime_percentage', 5, 2)->default(100);
                $table->string('status', 30)->default('Healthy');
                $table->boolean('is_active')->default(true)->index();
                $table->timestampTz('last_sync_at')->nullable();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('alerts')) {
            Schema::create('alerts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title');
                $table->string('severity', 20)->index();
                $table->string('owner', 100);
                $table->string('status', 40)->index();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('group', 60)->index();
                $table->string('label');
                $table->text('value');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestampsTz();
                $table->unique(['group', 'label']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('campuses');
    }
};
