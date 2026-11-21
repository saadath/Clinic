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
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->foreignId('medical_encounter_id')->nullable()->after('form_version_id')->constrained()->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('created_by');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->restrictOnDelete();
            $table->index(['medical_encounter_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex(['medical_encounter_id', 'created_at']);
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['confirmed_at']);
            $table->dropConstrainedForeignId('medical_encounter_id');
        });
    }
};
