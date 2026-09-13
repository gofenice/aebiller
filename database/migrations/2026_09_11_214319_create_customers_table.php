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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            // The key in the QR code on the back of the card.
            $table->uuid()->unique();
            $table->string('name')->index();
            // Stored as digits only (0551234567) so a typed number always matches.
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('address')->nullable();

            $table->foreignId('loyalty_tier_id')->nullable()->constrained()->nullOnDelete();

            // Running figures kept in step by LoyaltyService; the ledger is the source of truth.
            $table->integer('points_balance')->default(0);
            $table->decimal('lifetime_spend', 14, 2)->default(0);
            $table->unsignedInteger('visits')->default(0);
            $table->timestamp('last_visit_at')->nullable();

            $table->boolean('marketing_opt_in')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
