<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outside_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('duty_date');
            $table->time('planned_start');
            $table->time('planned_end');
            $table->string('location_name');
            $table->string('latitude');
            $table->string('longitude');
            $table->integer('requested_radius_meters');
            $table->integer('approved_radius_meters')->nullable();
            $table->text('reason');
            $table->string('request_photo');
            $table->enum('approval_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'duty_date', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outside_duties');
    }
};
