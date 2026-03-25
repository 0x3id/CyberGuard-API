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
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Polymorphic ownership
            // owner_type = 'App\Models\User' OR 'App\Models\Organization'
            // owner_id   = UUID of User or Organization
            $table->uuidMorphs('owner');

            // "Create Project"
            $table->foreignUuid('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'archived', 'completed'])
                  ->default('active');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->integer('max_collaborators')->default(3);

            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
