<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shops type local mobile numbers; WhatsApp wants full international ones.
     * This is the dialling code that fills the gap, per shop, because a shop in
     * Riyadh and one in Kochi cannot share a default.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('phone_country_code', 5)->nullable()->after('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('phone_country_code');
        });
    }
};
