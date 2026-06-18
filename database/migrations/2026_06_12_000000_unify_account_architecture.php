<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('user_subscriptions', 'max_collaborate_in_projects')) {
                $table->integer('max_collaborate_in_projects')->default(3)->after('max_projects');
            }
            if (! Schema::hasColumn('user_subscriptions', 'max_targets_per_project')) {
                $table->integer('max_targets_per_project')->default(3)->after('max_collaborate_in_projects');
            }
            if (! Schema::hasColumn('user_subscriptions', 'scans_used_this_month')) {
                $table->integer('scans_used_this_month')->default(0)->after('max_scans_per_month');
            }
        });

        if (Schema::hasColumn('user_subscriptions', 'max_targets')) {
            DB::statement('UPDATE user_subscriptions SET max_targets_per_project = max_targets WHERE max_targets_per_project = 3');
        }

        DB::statement("ALTER TABLE `organization_subscriptions` MODIFY COLUMN `status` ENUM('pending','pending_email_verification','active','expired','cancelled','failed') NOT NULL DEFAULT 'pending'");

        Schema::table('subscription_billing_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_billing_orders', 'billable_type')) {
                $table->string('billable_type')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('subscription_billing_orders', 'billable_id')) {
                $table->uuid('billable_id')->nullable()->after('billable_type');
            }
            if (! Schema::hasColumn('subscription_billing_orders', 'workspace_type')) {
                $table->enum('workspace_type', ['user', 'organization'])->default('user')->after('billable_id');
            }
            if (! Schema::hasColumn('subscription_billing_orders', 'pending_corporate_email')) {
                $table->string('pending_corporate_email')->nullable()->after('last_paymob_payload');
            }
            if (! Schema::hasColumn('subscription_billing_orders', 'corporate_email_verified_at')) {
                $table->timestamp('corporate_email_verified_at')->nullable()->after('pending_corporate_email');
            }
            if (! Schema::hasColumn('subscription_billing_orders', 'corporate_verification_sent_at')) {
                $table->timestamp('corporate_verification_sent_at')->nullable()->after('corporate_email_verified_at');
            }

            $table->index(['billable_type', 'billable_id'], 'billing_orders_billable_index');
        });

        DB::table('subscription_billing_orders')
            ->whereNull('billable_type')
            ->update([
                'billable_type' => User::class,
                'billable_id' => DB::raw('user_id'),
                'workspace_type' => 'user',
            ]);

        Schema::table('organization_subscriptions', function (Blueprint $table) {
            foreach ([
                'payment_method',
                'paymob_order_id',
                'paymob_transaction_id',
                'merchant_reference',
                'last_paymob_payload',
                'paid_at',
                'failure_reason',
            ] as $column) {
                if (Schema::hasColumn('organization_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('targets', function (Blueprint $table) {
            if (! Schema::hasColumn('targets', 'ownership_verification_token')) {
                $table->text('ownership_verification_token')->nullable()->after('is_verified');
            }
            if (! Schema::hasColumn('targets', 'dns_verified_at')) {
                $table->timestamp('dns_verified_at')->nullable()->after('ownership_verification_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            if (Schema::hasColumn('targets', 'dns_verified_at')) {
                $table->dropColumn('dns_verified_at');
            }
            if (Schema::hasColumn('targets', 'ownership_verification_token')) {
                $table->dropColumn('ownership_verification_token');
            }
        });

        Schema::table('organization_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_subscriptions', 'payment_method')) {
                $table->enum('payment_method', ['paymob', 'stripe'])->default('paymob')->after('status');
            }
            if (! Schema::hasColumn('organization_subscriptions', 'paymob_order_id')) {
                $table->string('paymob_order_id')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('organization_subscriptions', 'paymob_transaction_id')) {
                $table->string('paymob_transaction_id')->nullable()->after('paymob_order_id');
            }
            if (! Schema::hasColumn('organization_subscriptions', 'merchant_reference')) {
                $table->string('merchant_reference')->nullable()->unique()->after('paymob_transaction_id');
            }
            if (! Schema::hasColumn('organization_subscriptions', 'last_paymob_payload')) {
                $table->json('last_paymob_payload')->nullable()->after('merchant_reference');
            }
            if (! Schema::hasColumn('organization_subscriptions', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('last_paymob_payload');
            }
            if (! Schema::hasColumn('organization_subscriptions', 'failure_reason')) {
                $table->string('failure_reason')->nullable()->after('paid_at');
            }
        });

        DB::table('organization_subscriptions')
            ->where('status', 'pending_email_verification')
            ->update(['status' => 'pending']);

        DB::statement("ALTER TABLE `organization_subscriptions` MODIFY COLUMN `status` ENUM('pending','active','expired','cancelled','failed') NOT NULL DEFAULT 'pending'");

        Schema::table('subscription_billing_orders', function (Blueprint $table) {
            $table->dropIndex('billing_orders_billable_index');

            foreach ([
                'corporate_verification_sent_at',
                'corporate_email_verified_at',
                'pending_corporate_email',
                'workspace_type',
                'billable_id',
                'billable_type',
            ] as $column) {
                if (Schema::hasColumn('subscription_billing_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            foreach (['scans_used_this_month', 'max_targets_per_project', 'max_collaborate_in_projects'] as $column) {
                if (Schema::hasColumn('user_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
