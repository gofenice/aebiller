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
        Schema::table('sales', function (Blueprint $table) {
            // What the customer still owes on this bill. Kept on the sale so
            // the overdue report is one indexed read, never a sum per bill.
            $table->decimal('amount_outstanding', 14, 2)->default(0)->after('change_due')->index();
            $table->timestamp('settled_at')->nullable()->after('amount_outstanding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['amount_outstanding']);
            $table->dropColumn(['amount_outstanding', 'settled_at']);
        });
    }
};
