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
            $table->foreignId('plan_id')->nullable()->after('status')->constrained()->nullOnDelete();
            // Set only when this store pays something other than its plan price.
            $table->decimal('monthly_fee', 12, 2)->nullable()->after('plan_id');
            $table->string('billing_currency', 3)->nullable()->after('monthly_fee');

            $table->unsignedTinyInteger('billing_day')->default(1)->after('billing_currency');
            // Days after the due date before the store is closed.
            $table->unsignedTinyInteger('grace_days')->default(7)->after('billing_day');
            $table->boolean('auto_suspend')->default(true)->after('grace_days');

            $table->date('billing_starts_on')->nullable()->after('auto_suspend');
            $table->date('next_invoice_on')->nullable()->index()->after('billing_starts_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn([
                'monthly_fee', 'billing_currency', 'billing_day', 'grace_days',
                'auto_suspend', 'billing_starts_on', 'next_invoice_on',
            ]);
        });
    }
};
