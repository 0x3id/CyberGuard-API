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
        Schema::create('risk_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('target_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignUuid('scan_job_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Total degree 100
            $table->decimal('overall_score', 5, 2);


            $table->integer('critical_count')->default(0);
            $table->integer('high_count')->default(0);
            $table->integer('medium_count')->default(0);
            $table->integer('low_count')->default(0);

            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['target_id', 'calculated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_scores');
    }
};
