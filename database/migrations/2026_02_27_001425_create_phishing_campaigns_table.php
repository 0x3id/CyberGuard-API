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
        Schema::create('phishing_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('project_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignUuid('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->string('name');
            $table->enum('status', ['draft', 'active', 'completed', 'paused'])
                  ->default('draft');

            // Email Content
            $table->string('email_subject', 500);
            $table->text('email_body');
            $table->text('phishing_url')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();

            // Company Domain
            $table->string('authorized_domain');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phishing_campaigns');
    }
};
