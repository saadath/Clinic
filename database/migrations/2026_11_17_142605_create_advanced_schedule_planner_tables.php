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
        Schema::create('doctor_schedule_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('base_json');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('doctor_schedule_weeks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('doctor_schedule_templates')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
        });
        Schema::create('doctor_schedule_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('week_id')->constrained('doctor_schedule_weeks')->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->date('local_date');
            $table->unsignedInteger('revision')->default(1);
            $table->json('base_json');
            $table->json('final_json');
            $table->string('change_kind')->default('normal');
            $table->unique(['doctor_id', 'local_date']);
        });
        Schema::create('doctor_schedule_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('day_id')->constrained('doctor_schedule_days')->restrictOnDelete();
            $table->string('shift_key', 50);
            $table->time('local_start');
            $table->time('local_end');
            $table->unsignedSmallInteger('slot_minutes');
            $table->unsignedSmallInteger('capacity');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
        });
        Schema::create('schedule_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doctor_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('local_date')->index();
            $table->string('kind', 30);
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
        });
        Schema::create('schedule_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doctor_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('local_date')->nullable();
            $table->string('action');
            $table->json('before_json')->nullable();
            $table->json('after_json');
            $table->string('reason', 500);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
        });
        Schema::create('appointment_schedule_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('change_log_id')->constrained('schedule_change_logs')->restrictOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 40)->default('review_required');
            $table->string('reason', 500);
            $table->json('previous_booking');
            $table->dateTime('detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->index(['resolved_at', 'status']);
        });
        Schema::create('appointment_reschedule_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conflict_id')->constrained('appointment_schedule_conflicts')->restrictOnDelete();
            $table->dateTime('proposed_start');
            $table->dateTime('proposed_end');
            $table->string('status', 30)->default('proposed');
            $table->text('patient_response')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->dateTime('confirmed_at')->nullable();
        });
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conflict_id')->constrained('appointment_schedule_conflicts')->restrictOnDelete();
            $table->string('channel', 30);
            $table->string('status', 30);
            $table->text('message');
            $table->text('result')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['notification_logs', 'appointment_reschedule_requests', 'appointment_schedule_conflicts', 'schedule_change_logs', 'schedule_closures', 'doctor_schedule_shifts', 'doctor_schedule_days', 'doctor_schedule_weeks', 'doctor_schedule_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
