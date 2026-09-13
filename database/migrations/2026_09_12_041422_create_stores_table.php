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
        // One row per shop paying for the system.
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // The subdomain: fathima → fathima.pgbiller.com.
            $table->string('slug', 40)->unique();
            $table->string('legal_name')->nullable();

            $table->string('status', 20)->default('active')->index();
            $table->string('suspension_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();

            // Who the platform deals with, as opposed to the shop's own staff.
            $table->string('owner_name')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('owner_phone', 20)->nullable();

            // The store's own settings, which its screens and receipts use.
            $table->string('currency_code', 3)->default('SAR');
            $table->string('currency_symbol', 8)->default('SAR ');
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->string('vat_number', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('expiry_alert_days')->default(30);
            $table->json('tax_rates')->nullable();

            $table->date('trial_ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
