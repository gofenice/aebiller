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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('invoice_no', 32)->unique();
            $table->string('status', 20)->default('completed')->index();

            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_vat_number', 20)->nullable();

            // Every money column is in the store currency; item prices are
            // VAT inclusive when the product is priced that way.
            $table->decimal('items_gross', 14, 2)->default(0);
            $table->decimal('line_discount_total', 14, 2)->default(0);
            $table->decimal('bill_discount', 14, 2)->default(0);
            $table->decimal('subtotal_excl_vat', 14, 2)->default(0);
            $table->decimal('vat_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('cost_total', 14, 2)->default(0);

            $table->string('payment_method', 20)->default('cash');
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('change_due', 14, 2)->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sold_at')->index();

            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
