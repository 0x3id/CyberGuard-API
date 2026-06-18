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
        Schema::table('organization_subscriptions', function (Blueprint $table) {
            $table->enum('payment_method', ['paymob', 'stripe'])->default('paymob')->after('status');
            
            // Paymob-specific fields
            $table->string('paymob_order_id')->nullable()->after('payment_method');
            $table->string('paymob_transaction_id')->nullable()->after('paymob_order_id');
            $table->string('merchant_reference')->nullable()->unique()->after('paymob_transaction_id');
            $table->json('last_paymob_payload')->nullable()->after('merchant_reference');
            
            // Payment tracking
            $table->timestamp('paid_at')->nullable()->after('last_paymob_payload');
            $table->string('failure_reason')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'paymob_order_id',
                'paymob_transaction_id',
                'merchant_reference',
                'last_paymob_payload',
                'paid_at',
                'failure_reason',
            ]);
        });
    }
};
