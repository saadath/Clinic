<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->char('national_id', 10)->unique();
            $table->string('mobile', 11)->index();
            $table->date('birth_date');
            $table->string('sex', 20);
            foreach (['email', 'occupation', 'marital_status', 'emergency_name', 'emergency_mobile', 'insurance_name', 'insurance_number', 'referral_source'] as $field) {
                $table->string($field)->nullable();
            }
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['last_name', 'first_name']);
        });
        Schema::create('patient_doctor', function (Blueprint $table): void {
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['patient_id', 'doctor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_doctor');
        Schema::dropIfExists('patients');
    }
};
