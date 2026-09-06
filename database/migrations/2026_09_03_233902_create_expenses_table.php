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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 32)->unique();
            $table->date('expense_date')->index();

            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();

            // Who was paid: a registered supplier, or just a name for the
            // corner shop that sold you the tea.
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payee')->nullable();

            // A supplier bill can point at the goods receipt it paid for.
            $table->foreignId('stock_entry_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->string('invoice_number', 64)->nullable();

            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->string('payment_method', 20)->default('cash');
            $table->boolean('is_paid')->default(true)->index();
            $table->date('paid_on')->nullable();

            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expense_date', 'expense_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
