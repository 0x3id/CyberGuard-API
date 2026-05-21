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
        Schema::create('scan_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('target_id')
                    ->constrained()
                    ->cascadeOnDelete();
            $table->foreignUuid('project_id')
                    ->constrained()
                    ->cascadeOnDelete();

            // Who Start scan
            $table->foreignUuid('triggered_by')
                    ->constrained('users')
                    ->restrictOnDelete();

            // auto
            // targeted
            // web
            $table->enum('scan_type', ['auto', 'targeted', 'web']);

            // pending → running → completed / failed / cancelled
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])
                    ->default('pending');

            // Docker Container ID
            $table->string('container_id')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_log')->nullable();
            $table->timestamps();

            $table->index(['target_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_jobs');
    }
};
