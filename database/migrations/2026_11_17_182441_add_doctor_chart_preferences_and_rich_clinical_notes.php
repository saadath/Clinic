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
        Schema::table('doctors', function (Blueprint $table): void {
            $table->string('chart_language', 2)->default('fa');
        });
        Schema::table('medical_encounters', function (Blueprint $table): void {
            $table->longText('note_html')->nullable();
            $table->string('note_language', 2)->default('fa');
        });
        Schema::create('patient_chronic_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->longText('body_html');
            $table->string('language', 2);
            $table->timestamp('created_at');
            $table->index(['patient_id', 'doctor_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_chronic_summaries');
        Schema::table('medical_encounters', fn (Blueprint $table) => $table->dropColumn(['note_html', 'note_language']));
        Schema::table('doctors', fn (Blueprint $table) => $table->dropColumn('chart_language'));
    }
};
