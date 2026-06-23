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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('owner_id');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('company_domain')->nullable()->after('email_verified_at');
        });

        // Copy existing domain data to company_domain
        DB::statement('UPDATE organizations SET company_domain = domain WHERE domain IS NOT NULL');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('domain');
        });

        // Add 'enterprise' to subscription_billing_orders plan column enum
        DB::statement("ALTER TABLE `subscription_billing_orders` MODIFY COLUMN `plan` ENUM('starter', 'pro', 'enterprise') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('slug');
        });

        DB::statement('UPDATE organizations SET domain = company_domain WHERE company_domain IS NOT NULL');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['email', 'email_verified_at', 'company_domain']);
        });

        DB::statement("ALTER TABLE `subscription_billing_orders` MODIFY COLUMN `plan` ENUM('starter', 'pro') NOT NULL");
    }
};
