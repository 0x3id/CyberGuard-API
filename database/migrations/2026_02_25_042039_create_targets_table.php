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
        Schema::create('targets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('project_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // domain  → "example.com"
            // ip      → "192.168.1.1"
            // network → "192.168.1.0/24"
            $table->enum('type', ['domain', 'ip', 'network']);
            $table->string('value', 500);
            $table->string('label')->nullable();

            // Company have this domain?
            $table->boolean('is_verified')->default(false);

            // Last Risk score
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->timestamp('last_scanned_at')->nullable();

            $table->timestamps();

            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
