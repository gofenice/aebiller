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
        Schema::table('plans', function (Blueprint $table) {
            // Monthly, yearly, or a one-off lifetime price.
            $table->string('billing_period', 20)->default('monthly')->after('monthly_price');
        });

        Schema::table('stores', function (Blueprint $table) {
            // Set only when this store is on a different cycle from its plan.
            $table->string('billing_period', 20)->nullable()->after('billing_currency');
            // How many days before the renewal date the invoice is raised, so
            // the shop sees what is owed with time to pay it.
            $table->unsignedTinyInteger('invoice_lead_days')->default(7)->after('billing_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('billing_period');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['billing_period', 'invoice_lead_days']);
        });
    }
};
