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
        Schema::create('phishing_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('phishing_target_id')
                  ->constrained('phishing_targets')
                  ->cascadeOnDelete();
            $table->foreignUuid('campaign_id')
                  ->constrained('phishing_campaigns')
                  ->cascadeOnDelete();

            // sent      → Email sent
            // opened    → Employee open the email
            // clicked   → Employee clicked on URL
            // submitted → Employee Add data in form
            $table->enum('event_type', ['sent', 'opened', 'clicked', 'submitted']);

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->json('submitted_data')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['campaign_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phishing_events');
    }
};
