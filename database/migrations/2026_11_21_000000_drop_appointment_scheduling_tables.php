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
        Schema::table('medical_encounters', function (Blueprint $table): void {
            if (Schema::hasColumn('medical_encounters', 'appointment_id')) {
                $table->dropForeign(['appointment_id']);
                $table->dropColumn('appointment_id');
            }
        });

        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('appointment_reschedule_requests');
        Schema::dropIfExists('appointment_schedule_conflicts');
        Schema::dropIfExists('schedule_change_logs');
        Schema::dropIfExists('schedule_closures');
        Schema::dropIfExists('doctor_schedule_shifts');
        Schema::dropIfExists('doctor_schedule_days');
        Schema::dropIfExists('doctor_schedule_weeks');
        Schema::dropIfExists('doctor_schedule_templates');
        Schema::dropIfExists('queue_entries');
        Schema::dropIfExists('appointment_status_events');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('schedule_exceptions');
        Schema::dropIfExists('doctor_schedule_rules');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dropped tables cannot be restored automatically.
    }
};
