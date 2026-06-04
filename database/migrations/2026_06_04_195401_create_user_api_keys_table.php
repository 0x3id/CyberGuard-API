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
        Schema::create('user_api_keys', function (Blueprint $table) {
            $table->uuid();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            //virustotal, shodan, ai_assistant, etc.
            $table->string('service'); 
            
            $table->text('key')->nullable(); 
            
            $table->timestamps();

            $table->unique(['user_id', 'service']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_api_keys');
    }
};
