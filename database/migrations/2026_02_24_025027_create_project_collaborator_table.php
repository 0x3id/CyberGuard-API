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
        Schema::create('project_collaborator', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('project_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Who Send invitation
            $table->foreignUuid('invited_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            // owner  → Add auto when create it
            // editor → Add Targets and run Scans view Findings
            // viewer → view only
            $table->enum('role', ['owner', 'editor', 'viewer'])
                  ->default('editor');

            // pending
            // accepted
            // rejected
            $table->enum('status', ['pending', 'accepted', 'rejected'])
                  ->default('pending');

            $table->timestamp('invited_at');
            $table->timestamp('accepted_at')->nullable();

            // User add only onetime for project
            $table->unique(['project_id', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_collaborator');
    }
};
