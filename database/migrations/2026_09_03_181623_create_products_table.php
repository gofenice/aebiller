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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('name');
            $table->string('short_name', 60)->nullable();

            $table->string('type', 20)->default('packaged')->index();

            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();

            // Packaged goods: 500 g pouch, 1 L bottle, 12 pieces per case, etc.
            $table->decimal('pack_size', 10, 3)->nullable();
            $table->foreignId('pack_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->unsignedInteger('units_per_case')->nullable();

            // Taxation
            $table->string('hs_code', 20)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('price_includes_tax')->default(true);

            // Pricing
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);

            // Stock control
            $table->decimal('opening_stock', 14, 3)->default(0);
            $table->decimal('current_stock', 14, 3)->default(0)->index();
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->decimal('max_stock_level', 14, 3)->nullable();

            // Loose goods (vegetables, fruits, grains sold by weight)
            $table->boolean('is_weighable')->default(false);
            $table->decimal('tare_weight', 10, 3)->nullable();
            $table->decimal('min_sale_quantity', 10, 3)->nullable();
            $table->decimal('wastage_percent', 5, 2)->default(0);

            // Batch / expiry tracking
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->unsignedSmallInteger('shelf_life_days')->nullable();

            $table->string('storage_type', 20)->default('ambient');
            $table->string('rack_location', 60)->nullable();

            $table->string('image_path')->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
