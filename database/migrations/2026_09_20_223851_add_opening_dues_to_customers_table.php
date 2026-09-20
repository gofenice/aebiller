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
        Schema::table('customers', function (Blueprint $table) {
            // What a customer already owed the shop when it started using the
            // app — carried over from the shop's own book.
            $table->decimal('opening_due', 14, 2)->default(0)->after('lifetime_spend');
            $table->decimal('opening_due_outstanding', 14, 2)->default(0)->after('opening_due')->index();
            $table->date('opening_due_on')->nullable()->after('opening_due_outstanding');
            $table->string('opening_due_note')->nullable()->after('opening_due_on');
        });

        Schema::table('credit_payments', function (Blueprint $table) {
            // A payment can settle a bill or the carried-over balance, so it
            // no longer has to belong to a sale.
            $table->foreignId('sale_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['opening_due_outstanding']);
            $table->dropColumn(['opening_due', 'opening_due_outstanding', 'opening_due_on', 'opening_due_note']);
        });
    }
};
