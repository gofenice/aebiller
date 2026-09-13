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
        Schema::table('store_invoices', function (Blueprint $table) {
            // The Razorpay payment link a store is sent to, kept so the same
            // link is reused rather than a new one raised on every visit.
            $table->string('razorpay_payment_link_id', 64)->nullable()->after('notes');
            $table->string('razorpay_short_url')->nullable()->after('razorpay_payment_link_id');
            $table->string('razorpay_payment_id', 64)->nullable()->after('razorpay_short_url');

            // A lifetime invoice covers no end date.
            $table->date('period_end')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_invoices', function (Blueprint $table) {
            $table->dropColumn(['razorpay_payment_link_id', 'razorpay_short_url', 'razorpay_payment_id']);
        });
    }
};
