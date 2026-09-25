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
        // Settings the platform itself runs on. There is only ever one row.
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();

            // How the platform charges its shops.
            $table->string('razorpay_key_id', 60)->nullable();
            $table->text('razorpay_key_secret')->nullable();
            $table->text('razorpay_webhook_secret')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
