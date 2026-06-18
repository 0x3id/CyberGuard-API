<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `organization_subscriptions` MODIFY COLUMN `status` ENUM('pending','active','expired','cancelled','failed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('organization_subscriptions')
            ->where('status', 'failed')
            ->update(['status' => 'cancelled']);

        DB::statement("ALTER TABLE `organization_subscriptions` MODIFY COLUMN `status` ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
