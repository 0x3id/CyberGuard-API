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
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('project_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();
            $table->foreignUuid('target_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            $table->foreignUuid('generated_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->string('title');
            $table->enum('type', ['project', 'target', 'phishing']);
            $table->enum('format', ['pdf', 'json', 'html']);
            $table->text('file_url')->nullable();

            // { "total_findings": 12, "critical": 2, "risk_score": 78.5 }
            $table->json('summary')->nullable();

            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
