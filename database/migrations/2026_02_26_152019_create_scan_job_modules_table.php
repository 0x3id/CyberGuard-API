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
        Schema::create('scan_job_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('scan_job_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignUuid('module_id')
                  ->constrained('scan_modules')
                  ->restrictOnDelete();

            $table->enum('status', ['pending', 'running', 'done', 'failed'])
                  ->default('pending');

            // كم millisecond أخد الـ Module
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_job_modules');
    }
};
