<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outside_duties', function (Blueprint $table) {
            $table->timestamp('attended_at')->nullable()->after('rejection_reason');
            $table->string('attendance_photo')->nullable()->after('attended_at');
            $table->string('attendance_location')->nullable()->after('attendance_photo');
        });
    }

    public function down(): void
    {
        Schema::table('outside_duties', function (Blueprint $table) {
            $table->dropColumn([
                'attended_at',
                'attendance_photo',
                'attendance_location',
            ]);
        });
    }
};
