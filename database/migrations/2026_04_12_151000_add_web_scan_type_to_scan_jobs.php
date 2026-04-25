<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add "web" option to scan_type enum
        DB::statement("ALTER TABLE `scan_jobs` MODIFY `scan_type` ENUM('auto','targeted','web') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `scan_jobs` MODIFY `scan_type` ENUM('auto','targeted') NOT NULL");
    }
};
