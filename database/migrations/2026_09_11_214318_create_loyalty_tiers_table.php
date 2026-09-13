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
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            // Spend over the tier window needed to reach this tier; the entry tier is 0.
            $table->decimal('min_spend', 14, 2)->default(0)->index();
            $table->decimal('earn_multiplier', 4, 2)->default(1);
            $table->string('card_theme', 20)->default('emerald');
            $table->string('perks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_tiers');
    }
};
