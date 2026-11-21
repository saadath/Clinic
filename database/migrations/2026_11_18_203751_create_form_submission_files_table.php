<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submission_files', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('form_submission_id')->constrained()->restrictOnDelete();
            $table->string('field_key', 64);
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['form_submission_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_files');
    }
};
