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
        Schema::create('phishing_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('campaign_id')
                  ->constrained('phishing_campaigns')
                  ->cascadeOnDelete();

            $table->string('employee_email');
            $table->string('employee_name')->nullable();
            $table->string('department', 100)->nullable();


            $table->string('tracking_token', 100)->unique();

            $table->timestamp('sent_at')->nullable();


            $table->integer('awareness_score')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phishing_targets');
    }
};
