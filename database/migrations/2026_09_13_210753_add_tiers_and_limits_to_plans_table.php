<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A plan now offers both a monthly and a yearly price rather than being one
     * or the other, and caps what a shop on it may hold. A null cap is no cap.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Yearly costs twelve months less this, so there is a single price
            // to maintain rather than two that can drift apart.
            $table->decimal('yearly_discount_percent', 5, 2)->default(0)->after('monthly_price');

            // Razorpay pins amount, currency and interval to its own plan, so
            // the yearly one cannot share the monthly one's id.
            $table->string('razorpay_yearly_plan_id', 64)->nullable()->after('razorpay_plan_id');

            // Hidden from the pricing table: handed out from the platform only.
            $table->boolean('is_public')->default(true)->after('is_active');
            // Never invoiced at all.
            $table->boolean('is_free')->default(false)->after('is_public');

            $table->unsignedInteger('max_products')->nullable()->after('is_free');
            $table->unsignedInteger('max_monthly_bills')->nullable()->after('max_products');
            $table->unsignedInteger('max_users')->nullable()->after('max_monthly_bills');
            $table->unsignedInteger('max_customers')->nullable()->after('max_users');
        });

        Schema::table('plan_prices', function (Blueprint $table) {
            $table->string('razorpay_yearly_plan_id', 64)->nullable()->after('razorpay_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'yearly_discount_percent', 'razorpay_yearly_plan_id', 'is_public', 'is_free',
                'max_products', 'max_monthly_bills', 'max_users', 'max_customers',
            ]);
        });

        Schema::table('plan_prices', function (Blueprint $table) {
            $table->dropColumn('razorpay_yearly_plan_id');
        });
    }
};
