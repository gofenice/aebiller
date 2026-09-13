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
        Schema::table('stores', function (Blueprint $table) {
            // Charge only the days left when a shop joins mid-month.
            $table->boolean('prorate_first_invoice')->default(true)->after('invoice_lead_days');

            // A card left on file with Razorpay, charged each period.
            $table->boolean('auto_charge_enabled')->default(false)->after('prorate_first_invoice');
            $table->string('razorpay_subscription_id', 64)->nullable()->after('auto_charge_enabled');
            $table->string('razorpay_subscription_status', 30)->nullable()->after('razorpay_subscription_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            // Razorpay keeps its own copy of the plan; this is its id there.
            $table->string('razorpay_plan_id', 64)->nullable()->after('billing_period');
        });

        Schema::table('store_invoices', function (Blueprint $table) {
            $table->boolean('is_prorated')->default(false)->after('amount_paid');
            // So the overdue warning is sent once, not every night.
            $table->timestamp('overdue_notified_at')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'prorate_first_invoice', 'auto_charge_enabled',
                'razorpay_subscription_id', 'razorpay_subscription_status',
            ]);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('razorpay_plan_id');
        });

        Schema::table('store_invoices', function (Blueprint $table) {
            $table->dropColumn(['is_prorated', 'overdue_notified_at']);
        });
    }
};
