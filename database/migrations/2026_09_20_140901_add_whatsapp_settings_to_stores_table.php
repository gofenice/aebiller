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
            // Each shop sends from its own WhatsApp Business number, so the
            // credentials belong to the store, not the platform.
            $table->string('whatsapp_phone_number_id', 40)->nullable()->after('phone_country_code');
            $table->text('whatsapp_token')->nullable()->after('whatsapp_phone_number_id');
            $table->string('whatsapp_bill_template', 80)->nullable()->after('whatsapp_token');
            $table->string('whatsapp_otp_template', 80)->nullable()->after('whatsapp_bill_template');
            $table->string('whatsapp_language', 12)->nullable()->after('whatsapp_otp_template');
            $table->boolean('whatsapp_auto_send_bill')->default(true)->after('whatsapp_language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_phone_number_id',
                'whatsapp_token',
                'whatsapp_bill_template',
                'whatsapp_otp_template',
                'whatsapp_language',
                'whatsapp_auto_send_bill',
            ]);
        });
    }
};
