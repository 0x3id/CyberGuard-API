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
        Schema::create('organization_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')
                  ->constrained()
                  ->cascadeOnDelete();

            //Organization Subscriptions
            $table->enum('plan', ['starter', 'pro', 'enterprise'])
                  ->default('starter');
            $table->enum('status', ['active', 'expired', 'cancelled'])
                  ->default('active');

            //Package limits
            $table->integer('max_projects')->default(10);
            $table->integer('max_targets')->default(50);
            $table->integer('max_members')->default(5);
            $table->integer('max_scans_per_month')->default(100);

            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_subscriptions');
    }
};
