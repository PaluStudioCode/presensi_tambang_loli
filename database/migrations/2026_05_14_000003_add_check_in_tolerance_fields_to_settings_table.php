<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('check_in_late_tolerance_minutes')->default(20)->after('check_in_time');
            $table->unsignedSmallInteger('check_in_max_late_minutes')->default(40)->after('check_in_late_tolerance_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_late_tolerance_minutes',
                'check_in_max_late_minutes',
            ]);
        });
    }
};
