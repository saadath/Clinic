<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_encounters', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'created_at']);
            $table->index(['doctor_id', 'created_at']);
        });
        Schema::create('medical_record_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medical_encounter_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 30);
            $table->json('metadata_json')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['medical_encounter_id', 'occurred_at']);
        });
        Schema::create('medical_record_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medical_encounter_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('created_at');
            $table->index(['medical_encounter_id', 'created_at']);
        });
        Schema::create('medical_record_files', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('medical_encounter_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->timestamp('created_at');
            $table->index(['medical_encounter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_files');
        Schema::dropIfExists('medical_record_amendments');
        Schema::dropIfExists('medical_record_events');
        Schema::dropIfExists('medical_encounters');
    }
};
