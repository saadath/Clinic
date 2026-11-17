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
        Schema::create('doctor_schedule_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('local_start');
            $table->time('local_end');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedSmallInteger('slot_minutes');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['doctor_id', 'weekday', 'effective_from']);
        });
        Schema::create('schedule_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->date('local_date');
            $table->string('kind', 20);
            $table->time('local_start')->nullable();
            $table->time('local_end')->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['doctor_id', 'local_date']);
        });
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 30)->default('scheduled');
            $table->unsignedInteger('revision')->default(1);
            $table->string('visit_type', 100)->default('consultation');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('override_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('override_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['doctor_id', 'starts_at', 'ends_at', 'status'], 'appointment_availability');
        });
        Schema::create('appointment_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            $table->dateTime('occurred_at');
        });
        Schema::create('queue_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->date('local_date');
            $table->unsignedInteger('queue_number');
            $table->dateTime('checked_in_at');
            $table->dateTime('called_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unique(['doctor_id', 'local_date', 'queue_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
        Schema::dropIfExists('appointment_status_events');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('schedule_exceptions');
        Schema::dropIfExists('doctor_schedule_rules');
    }
};
