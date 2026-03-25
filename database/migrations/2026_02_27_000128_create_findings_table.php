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
        Schema::create('findings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('scan_job_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignUuid('target_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('title', 500);
            $table->text('description')->nullable();


            $table->enum('severity', ['critical', 'high', 'medium', 'low', 'info']);

           /*
            CVSS
            cvss_score  → 9.8
            cvss_vector → CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H
           */
            $table->decimal('cvss_score', 4, 1)->nullable();
            $table->string('cvss_vector', 200)->nullable();
            $table->string('cve_id', 50)->nullable(); // "CVE-2024-1234"

            $table->text('remediation')->nullable();

            // open
            // in_progress
            // resolved
            // false_positive
            $table->enum('status', ['open', 'in_progress', 'resolved', 'false_positive'])
                  ->default('open');

            $table->text('affected_url')->nullable();
            $table->text('proof')->nullable();
            $table->timestamps();

            $table->index(['target_id', 'severity', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
