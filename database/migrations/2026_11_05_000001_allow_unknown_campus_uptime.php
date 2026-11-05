<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->decimal('uptime_percentage', 5, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Preserve unknown measurements rather than replacing them with invented uptime.
        Schema::table('campuses', function (Blueprint $table) {
            $table->decimal('uptime_percentage', 5, 2)->nullable()->default(100)->change();
        });
    }
};
