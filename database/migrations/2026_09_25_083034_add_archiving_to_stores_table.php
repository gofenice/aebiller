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
            // An archived store keeps its data but gives up its subdomain, so
            // the address it held can be used again straight away.
            $table->string('original_slug', 40)->nullable()->after('slug');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('original_slug');
        });
    }
};
