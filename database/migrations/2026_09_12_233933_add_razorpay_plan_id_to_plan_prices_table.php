<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Razorpay fixes the amount and the currency on the plan it holds, so a
     * plan sold in several currencies needs one of theirs per currency. The
     * base currency's stays on the plan itself.
     */
    public function up(): void
    {
        Schema::table('plan_prices', function (Blueprint $table) {
            $table->string('razorpay_plan_id', 64)->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_prices', function (Blueprint $table) {
            $table->dropColumn('razorpay_plan_id');
        });
    }
};
