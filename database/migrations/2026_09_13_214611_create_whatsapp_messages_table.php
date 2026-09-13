<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every WhatsApp message the shop sent, successful or not — so a shop can
     * say whether a customer was sent their bill, and a redemption approved
     * without a code can be explained afterwards.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            $table->string('to', 24);
            $table->string('purpose', 20);
            $table->string('template', 100);
            $table->string('status', 20)->default('pending');
            $table->string('provider_message_id', 120)->nullable();
            $table->text('error')->nullable();

            // The bill or the member this was about.
            $table->nullableMorphs('related');

            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
