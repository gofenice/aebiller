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
        // A single row: the rules of the loyalty programme, edited by the owner.
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->string('program_name', 60)->default('Fathima Rewards');

            // Points earned for every 1 unit of currency on the bill, before the tier multiplier.
            $table->decimal('points_per_currency', 8, 2)->default(1);
            // What one point takes off a bill when it is redeemed.
            $table->decimal('point_value', 8, 4)->default(0.01);
            $table->unsignedInteger('min_redeem_points')->default(100);
            $table->decimal('max_redeem_percent', 5, 2)->default(50);

            // 0 means points never expire.
            $table->unsignedSmallInteger('points_expiry_months')->default(12);
            $table->unsignedInteger('welcome_bonus')->default(50);
            $table->unsignedInteger('birthday_bonus')->default(100);

            // Tiers are decided by spend over this many months.
            $table->unsignedSmallInteger('tier_window_months')->default(12);
            $table->text('card_terms')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
    }
};
