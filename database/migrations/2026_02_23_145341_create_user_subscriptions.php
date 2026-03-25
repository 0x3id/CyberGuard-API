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
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            //User Subscriptions
            $table->enum('plan', ['free', 'starter', 'pro'])
                ->default('free');
            $table->enum('status', ['active', 'expired', 'cancelled'])
                ->default('active');

            //Rate limit
            $table->integer('max_projects')->default(3);
            $table->integer('max_targets')->default(10);
            $table->integer('max_scans_per_month')->default(10);

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
        Schema::dropIfExists('user_subscriptions');
    }
};
