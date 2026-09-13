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
        // Money received from a store, entered by whoever banked it.
        Schema::create('store_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 3);
            $table->string('method', 20)->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->date('received_on')->index();
            $table->text('notes')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_payments');
    }
};
