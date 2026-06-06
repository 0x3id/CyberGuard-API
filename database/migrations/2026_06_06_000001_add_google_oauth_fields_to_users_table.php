<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add Google OAuth 2.0 fields to the users table.
 *
 * Changes applied:
 *   - google_id     : Unique Google sub-identifier (nullable, unique index).
 *   - auth_provider : 'local' | 'google' — tracks how the account was created.
 *   - password      : Made nullable so Google-only users have no password.
 *
 * NOTE: avatar_url already exists in the base users migration — no action needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Google's unique user identifier — the "sub" claim in Google ID tokens
            $table->string('google_id')->nullable()->unique()->after('email');

            // Tracks authentication provider: 'local' (password) or 'google' (OAuth)
            $table->string('auth_provider')->default('local')->after('google_id');

            // Allow NULL passwords — Google OAuth users authenticate without one
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'auth_provider']);
            // Restore password column to NOT NULL
            $table->string('password')->nullable(false)->change();
        });
    }
};
