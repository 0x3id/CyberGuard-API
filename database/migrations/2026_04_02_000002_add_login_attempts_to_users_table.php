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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('failed_login_attempts')->default(0)->after('two_factor_enabled');
            $table->timestamp('lockout_until')->nullable()->after('failed_login_attempts');
            $table->ipAddress('ip_address')->nullable()->after('lockout_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['failed_login_attempts', 'lockout_until', 'ip_address']);
        });
    }
};
