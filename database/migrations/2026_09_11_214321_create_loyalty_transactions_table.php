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
        // The points ledger. Credits are positive, debits negative.
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->integer('points');
            $table->integer('balance_after');

            // On credits only: how much of this credit is still unspent, and
            // when that remainder lapses. Debits use up the soonest-expiring first.
            $table->unsignedInteger('points_remaining')->default(0);
            $table->timestamp('expires_at')->nullable()->index();

            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            // The spend that earned the points, or the discount the points bought.
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
