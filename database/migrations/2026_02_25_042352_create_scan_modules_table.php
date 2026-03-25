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
        Schema::create('scan_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');           // "SQL Injection Scanner"
            $table->string('slug')->unique(); // "sqli"

            // web     → SQLi, XSS, CSRF
            // network → Port Scan, Banner Grabbing
            // recon   → Subdomain Enum, Whois
            $table->enum('category', ['web', 'network', 'recon']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_modules');
    }
};
