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
        Schema::create('project_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('project_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignUuid('invited_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Email of who invite he (signin or no)
            $table->string('email')->nullable();

            // E.X: cyberguard.io/invite/abc123xyz456
            $table->string('token', 100)->unique();

            $table->enum('role', ['editor', 'viewer'])
                  ->default('editor');

            // pending
            // accepted
            // expired
            $table->enum('status', ['pending', 'accepted', 'expired'])
                  ->default('pending');


            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_invitations');
    }
};
