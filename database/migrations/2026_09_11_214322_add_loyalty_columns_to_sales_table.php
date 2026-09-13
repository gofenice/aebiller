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
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('status')->constrained()->nullOnDelete();

            // Points redeemed act as a bill discount, so VAT is worked out after them.
            $table->decimal('loyalty_discount', 14, 2)->default(0)->after('bill_discount');

            $table->unsignedInteger('loyalty_points_earned')->default(0)->after('change_due');
            $table->unsignedInteger('loyalty_points_redeemed')->default(0)->after('loyalty_points_earned');
            // Printed on the receipt, so it shows the balance as it stood at the till.
            $table->integer('loyalty_balance_after')->nullable()->after('loyalty_points_redeemed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['loyalty_discount', 'loyalty_points_earned', 'loyalty_points_redeemed', 'loyalty_balance_after']);
        });
    }
};
