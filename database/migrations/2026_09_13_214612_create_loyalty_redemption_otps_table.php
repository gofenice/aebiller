<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one-time code that authorises a member's points being spent.
     *
     * Only the hash is kept, so a copy of this table is not a set of working
     * codes. The row records the exact number of points it was issued for:
     * a code approved for 100 points cannot settle a larger redemption.
     */
    public function up(): void
    {
        Schema::create('loyalty_redemption_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('points');
            $table->string('code_hash');
            $table->string('sent_to', 24)->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();

            // Wrong guesses, so a code cannot be worked out by trying.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            // Set when the owner approved without a code, because WhatsApp
            // could not deliver one.
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('override_reason', 200)->nullable();

            $table->timestamps();

            $table->index(['store_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemption_otps');
    }
};
