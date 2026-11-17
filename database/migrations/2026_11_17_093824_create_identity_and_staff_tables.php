<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->boolean('system')->default(false);
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('mobile', 16)->nullable()->unique();
        });
        Schema::create('doctors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('medical_council_number', 50)->unique();
            $table->string('specialty');
            $table->string('subspecialty')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('visit_duration_minutes')->default(20);
            $table->unsignedBigInteger('visit_price_rial')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('doctor_staff_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_user_id')->constrained('users')->restrictOnDelete();
            $table->string('assignment_type', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['staff_user_id', 'doctor_id', 'starts_at', 'ends_at'], 'assignment_access_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_staff_assignments');
        Schema::dropIfExists('doctors');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['mobile']);
            $table->dropColumn('mobile');
        });
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
