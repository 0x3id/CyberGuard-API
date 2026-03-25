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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            // الـ Action بيحصل في سياق مين؟ User أو Organization
            $table->string('owner_type')->nullable();
            $table->uuid('owner_id')->nullable();

            // Example on Actions:
            // "project.created"    "scan.started"
            // "finding.resolved"   "user.invited"
            // "report.generated"   "collaborator.removed"
            $table->string('action', 100);

            // الـ Entity اللي حصل عليها الـ Action
            $table->string('entity_type', 100)->nullable();
            $table->uuid('entity_id')->nullable();

            // تفاصيل إضافية بـ JSON — مثلاً الـ old/new values
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();

            // audit_logs مش بتتحذف أبداً — بس بنضيف
            $table->timestamp('created_at');

            $table->index(['owner_type', 'owner_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
