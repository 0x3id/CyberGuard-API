<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_billing_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('plan', ['starter', 'pro']);
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('EGP');

            $table->enum('status', ['pending', 'paid', 'failed', 'cancelled'])->default('pending');

            $table->string('merchant_reference', 64)->unique();

            $table->unsignedBigInteger('paymob_order_id')->nullable()->index();
            $table->unsignedBigInteger('paymob_transaction_id')->nullable()->index();

            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('last_paymob_payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_billing_orders');
    }
};
