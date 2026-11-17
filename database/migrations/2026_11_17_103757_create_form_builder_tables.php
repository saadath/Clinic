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
        Schema::create('form_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('owner_doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('form_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->json('schema_json');
            $table->char('schema_hash', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['form_template_id', 'version_number']);
        });
        Schema::create('form_template_shares', function (Blueprint $table): void {
            $table->foreignId('form_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['form_template_id', 'recipient_doctor_id']);
        });
        Schema::create('doctor_form_defaults', function (Blueprint $table): void {
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->string('form_purpose', 50);
            $table->foreignId('form_template_id')->constrained()->restrictOnDelete();
            $table->primary(['doctor_id', 'form_purpose']);
        });
        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('form_version_id')->constrained()->restrictOnDelete();
            $table->longText('values_json');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('doctor_form_defaults');
        Schema::dropIfExists('form_template_shares');
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('form_templates');
    }
};
