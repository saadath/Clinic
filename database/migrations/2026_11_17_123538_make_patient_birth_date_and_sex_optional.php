<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->date('birth_date')->nullable()->change();
            $table->string('sex', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('patients')->whereNull('birth_date')->orWhereNull('sex')->exists()) {
            throw new RuntimeException('Cannot require patient birth date and sex while optional values are empty.');
        }
        Schema::table('patients', function (Blueprint $table): void {
            $table->date('birth_date')->nullable(false)->change();
            $table->string('sex', 20)->nullable(false)->change();
        });
    }
};
