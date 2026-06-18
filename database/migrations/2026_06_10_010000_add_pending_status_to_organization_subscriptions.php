<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `organization_subscriptions` MODIFY COLUMN `status` ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `organization_subscriptions` MODIFY COLUMN `status` ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active'");
    }
};
