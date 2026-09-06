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
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 64)->nullable();
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('quantity', 14, 3)->default(0)->index();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->date('received_on')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'expires_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
