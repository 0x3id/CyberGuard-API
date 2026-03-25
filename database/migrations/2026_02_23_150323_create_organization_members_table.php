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
        Schema::create('organization_members', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // owner → Create ORG and added auto
            // admin  → Add Projects and Member
            // member → He works on projects
            // viewer → View only
            $table->enum('role', ['owner', 'admin', 'member', 'viewer'])
                  ->default('member');

            $table->timestamp('joined_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_members');
    }
};
