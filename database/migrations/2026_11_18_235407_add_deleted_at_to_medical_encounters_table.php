<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_encounters', fn (Blueprint $table) => $table->softDeletes());
    }

    /**
     * Rolling back makes previously hidden encounters visible again.
     */
    public function down(): void
    {
        Schema::table('medical_encounters', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
