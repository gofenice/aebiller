<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\StorageType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * A starter catalogue covering both product shapes a supermarket carries:
     * fixed packets and loose produce sold by weight. Prices are in SAR.
     */
    public function run(): void
    {
        $units = Unit::pluck('id', 'code');
        $categories = Category::pluck('id', 'slug');
        $brands = Brand::pluck('id', 'name');
        $suppliers = Supplier::pluck('id', 'code');
        $owner = User::where('email', 'superadmin@fathimasupermarket.test')->value('id');

        foreach ($this->packagedProducts() as $row) {
            $this->save($row, ProductType::Packaged, $units, $categories, $brands, $suppliers, $owner);
        }

        foreach ($this->looseProducts() as $row) {
            $this->save($row, ProductType::Loose, $units, $categories, $brands, $suppliers, $owner);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function save(
        array $row,
        ProductType $type,
        mixed $units,
        mixed $categories,
        mixed $brands,
        mixed $suppliers,
        ?int $owner,
    ): void {
        Product::updateOrCreate(['sku' => $row['sku']], [
            'name' => $row['name'],
            'barcode' => $row['barcode'] ?? null,
            'type' => $type,
            'category_id' => $categories[$row['category']],
            'brand_id' => isset($row['brand']) ? $brands[$row['brand']] : null,
            'supplier_id' => $suppliers[$row['supplier']],
            'unit_id' => $units[$row['unit']],
            'pack_size' => $row['pack_size'] ?? null,
            'pack_unit_id' => isset($row['pack_unit']) ? $units[$row['pack_unit']] : null,
            'units_per_case' => $row['units_per_case'] ?? null,
            'hs_code' => $row['hs'] ?? null,
            'tax_rate' => $row['tax'] ?? 15,
            'price_includes_tax' => true,
            'cost_price' => $row['cost'],
            'selling_price' => $row['price'],
            'reorder_level' => $row['reorder'],
            'max_stock_level' => $row['max'] ?? null,
            'is_weighable' => $type === ProductType::Loose,
            'min_sale_quantity' => $row['min_sale'] ?? null,
            'wastage_percent' => $row['wastage'] ?? 0,
            'track_batches' => $row['batches'] ?? false,
            'track_expiry' => $row['expiry'] ?? false,
            'shelf_life_days' => $row['shelf_life'] ?? null,
            'storage_type' => $row['storage'] ?? StorageType::Ambient,
            'rack_location' => $row['rack'] ?? null,
            'is_active' => true,
            'created_by' => $owner,
            'updated_by' => $owner,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function packagedProducts(): array
    {
        return [
            ['sku' => 'PKT-00001', 'barcode' => '6281007021234', 'name' => 'Almarai Fresh Milk', 'category' => 'fresh-milk', 'brand' => 'Almarai', 'supplier' => 'SUP-0004', 'unit' => 'btl', 'pack_size' => 2, 'pack_unit' => 'l', 'units_per_case' => 6, 'hs' => '0401', 'cost' => 7.75, 'price' => 9.50, 'reorder' => 40, 'max' => 300, 'batches' => true, 'expiry' => true, 'shelf_life' => 7, 'storage' => StorageType::Chilled, 'rack' => 'Chiller 1'],
            ['sku' => 'PKT-00002', 'barcode' => '6281007025676', 'name' => 'Almarai Laban', 'category' => 'laban-yoghurt', 'brand' => 'Almarai', 'supplier' => 'SUP-0004', 'unit' => 'btl', 'pack_size' => 1, 'pack_unit' => 'l', 'units_per_case' => 12, 'hs' => '0403', 'cost' => 4.40, 'price' => 5.50, 'reorder' => 36, 'max' => 240, 'batches' => true, 'expiry' => true, 'shelf_life' => 10, 'storage' => StorageType::Chilled, 'rack' => 'Chiller 1'],
            ['sku' => 'PKT-00003', 'barcode' => '6281031012345', 'name' => 'Nadec Long Life Milk', 'category' => 'fresh-milk', 'brand' => 'Nadec', 'supplier' => 'SUP-0001', 'unit' => 'btl', 'pack_size' => 1, 'pack_unit' => 'l', 'units_per_case' => 12, 'hs' => '0401', 'cost' => 4.10, 'price' => 5.25, 'reorder' => 48, 'max' => 360, 'expiry' => true, 'shelf_life' => 180, 'rack' => 'Aisle 3 · Rack C1'],
            ['sku' => 'PKT-00004', 'barcode' => '5740900403123', 'name' => 'Lurpak Unsalted Butter', 'category' => 'cheese-butter', 'brand' => 'Lurpak', 'supplier' => 'SUP-0003', 'unit' => 'pkt', 'pack_size' => 200, 'pack_unit' => 'g', 'units_per_case' => 24, 'hs' => '0405', 'cost' => 14.20, 'price' => 17.50, 'reorder' => 12, 'max' => 80, 'batches' => true, 'expiry' => true, 'shelf_life' => 150, 'storage' => StorageType::Chilled, 'rack' => 'Chiller 2'],
            ['sku' => 'PKT-00005', 'barcode' => '6291003041235', 'name' => 'Abu Kass Basmati Rice', 'category' => 'basmati-rice', 'brand' => 'Abu Kass', 'supplier' => 'SUP-0001', 'unit' => 'pkt', 'pack_size' => 10, 'pack_unit' => 'kg', 'units_per_case' => 4, 'hs' => '1006', 'cost' => 54.00, 'price' => 67.00, 'reorder' => 15, 'max' => 120, 'rack' => 'Aisle 1 · Rack A1'],
            ['sku' => 'PKT-00006', 'barcode' => '6281018001232', 'name' => 'Afia Sunflower Oil', 'category' => 'cooking-oils', 'brand' => 'Afia', 'supplier' => 'SUP-0001', 'unit' => 'btl', 'pack_size' => 1.5, 'pack_unit' => 'l', 'units_per_case' => 8, 'hs' => '1512', 'cost' => 15.50, 'price' => 19.00, 'reorder' => 24, 'max' => 160, 'expiry' => true, 'shelf_life' => 540, 'rack' => 'Aisle 2 · Rack B1'],
            ['sku' => 'PKT-00007', 'barcode' => '7891515012342', 'name' => 'Sadia Frozen Whole Chicken', 'category' => 'frozen-poultry', 'brand' => 'Sadia', 'supplier' => 'SUP-0003', 'unit' => 'pc', 'pack_size' => 1100, 'pack_unit' => 'g', 'units_per_case' => 10, 'hs' => '0207', 'cost' => 14.00, 'price' => 17.50, 'reorder' => 30, 'max' => 200, 'batches' => true, 'expiry' => true, 'shelf_life' => 365, 'storage' => StorageType::Frozen, 'rack' => 'Freezer 1'],
            ['sku' => 'PKT-00008', 'barcode' => '6281039012347', 'name' => 'Americana Frozen Fries', 'category' => 'frozen-vegetables', 'brand' => 'Americana', 'supplier' => 'SUP-0003', 'unit' => 'pkt', 'pack_size' => 2.5, 'pack_unit' => 'kg', 'units_per_case' => 6, 'hs' => '2004', 'cost' => 18.00, 'price' => 22.50, 'reorder' => 15, 'max' => 100, 'expiry' => true, 'shelf_life' => 540, 'storage' => StorageType::Frozen, 'rack' => 'Freezer 2'],
            ['sku' => 'PKT-00009', 'barcode' => '6281006001237', 'name' => 'Sunbulah Puff Pastry', 'category' => 'ready-meals', 'brand' => 'Sunbulah', 'supplier' => 'SUP-0003', 'unit' => 'pkt', 'pack_size' => 400, 'pack_unit' => 'g', 'units_per_case' => 12, 'hs' => '1901', 'cost' => 10.25, 'price' => 13.00, 'reorder' => 12, 'max' => 72, 'expiry' => true, 'shelf_life' => 365, 'storage' => StorageType::Frozen, 'rack' => 'Freezer 2'],
            ['sku' => 'PKT-00010', 'barcode' => '6281100112341', 'name' => 'Al Rabie Orange Juice', 'category' => 'juices', 'brand' => 'Al Rabie', 'supplier' => 'SUP-0001', 'unit' => 'btl', 'pack_size' => 1, 'pack_unit' => 'l', 'units_per_case' => 12, 'hs' => '2009', 'cost' => 5.60, 'price' => 7.00, 'reorder' => 36, 'max' => 240, 'expiry' => true, 'shelf_life' => 270, 'rack' => 'Aisle 5 · Rack E1'],
            ['sku' => 'PKT-00011', 'barcode' => '6001240012345', 'name' => 'Lipton Yellow Label Tea Bags', 'category' => 'tea-coffee', 'brand' => 'Lipton', 'supplier' => 'SUP-0001', 'unit' => 'box', 'pack_size' => 100, 'pack_unit' => 'pc', 'units_per_case' => 24, 'hs' => '0902', 'cost' => 11.20, 'price' => 14.00, 'reorder' => 18, 'max' => 120, 'expiry' => true, 'shelf_life' => 730, 'rack' => 'Aisle 5 · Rack E2'],
            ['sku' => 'PKT-00012', 'barcode' => '6281045012348', 'name' => 'Goody Peanut Butter', 'category' => 'sauces-spreads', 'brand' => 'Goody', 'supplier' => 'SUP-0001', 'unit' => 'btl', 'pack_size' => 510, 'pack_unit' => 'g', 'units_per_case' => 12, 'hs' => '2008', 'cost' => 12.80, 'price' => 16.00, 'reorder' => 10, 'max' => 60, 'expiry' => true, 'shelf_life' => 540, 'rack' => 'Aisle 4 · Rack D3'],
            ['sku' => 'PKT-00013', 'barcode' => '6281007090124', 'name' => 'Almarai Arabic Bread Large', 'category' => 'arabic-bread', 'brand' => 'Almarai', 'supplier' => 'SUP-0004', 'unit' => 'pkt', 'pack_size' => 6, 'pack_unit' => 'pc', 'hs' => '1905', 'cost' => 2.00, 'price' => 2.75, 'reorder' => 40, 'max' => 200, 'batches' => true, 'expiry' => true, 'shelf_life' => 4, 'rack' => 'Bakery counter'],
            ['sku' => 'PKT-00014', 'barcode' => '4084500012349', 'name' => 'Tide Automatic Detergent', 'category' => 'detergents', 'brand' => 'Tide', 'supplier' => 'SUP-0003', 'unit' => 'pkt', 'pack_size' => 2.5, 'pack_unit' => 'kg', 'units_per_case' => 6, 'hs' => '3402', 'cost' => 22.50, 'price' => 28.00, 'reorder' => 12, 'max' => 80, 'rack' => 'Aisle 7 · Rack G1'],
            ['sku' => 'PKT-00015', 'barcode' => '6221048012341', 'name' => 'Signal Cavity Protection Toothpaste', 'category' => 'oral-care', 'brand' => 'Signal', 'supplier' => 'SUP-0003', 'unit' => 'pc', 'pack_size' => 120, 'pack_unit' => 'ml', 'units_per_case' => 36, 'hs' => '3306', 'cost' => 9.40, 'price' => 12.00, 'reorder' => 18, 'max' => 120, 'expiry' => true, 'shelf_life' => 730, 'rack' => 'Aisle 8 · Rack H2'],
            ['sku' => 'PKT-00016', 'barcode' => '6281007033459', 'name' => 'Almarai Fresh Eggs Medium', 'category' => 'eggs', 'brand' => 'Almarai', 'supplier' => 'SUP-0004', 'unit' => 'box', 'pack_size' => 30, 'pack_unit' => 'pc', 'hs' => '0407', 'cost' => 13.50, 'price' => 17.00, 'reorder' => 20, 'max' => 150, 'batches' => true, 'expiry' => true, 'shelf_life' => 21, 'storage' => StorageType::Chilled, 'rack' => 'Chiller 3'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function looseProducts(): array
    {
        return [
            ['sku' => 'LSE-00001', 'name' => 'Tomato (Local)', 'category' => 'fresh-vegetables', 'supplier' => 'SUP-0002', 'unit' => 'kg', 'hs' => '0702', 'cost' => 3.60, 'price' => 5.50, 'reorder' => 15, 'max' => 120, 'min_sale' => 0.25, 'wastage' => 8, 'expiry' => true, 'shelf_life' => 5, 'storage' => StorageType::Chilled, 'rack' => 'Produce table 1'],
            ['sku' => 'LSE-00002', 'name' => 'Cucumber (Local)', 'category' => 'fresh-vegetables', 'supplier' => 'SUP-0002', 'unit' => 'kg', 'hs' => '0707', 'cost' => 3.00, 'price' => 4.50, 'reorder' => 12, 'max' => 100, 'min_sale' => 0.25, 'wastage' => 7, 'expiry' => true, 'shelf_life' => 6, 'storage' => StorageType::Chilled, 'rack' => 'Produce table 1'],
            ['sku' => 'LSE-00003', 'name' => 'Potato', 'category' => 'fresh-vegetables', 'supplier' => 'SUP-0002', 'unit' => 'kg', 'hs' => '0701', 'cost' => 2.60, 'price' => 4.00, 'reorder' => 25, 'max' => 250, 'min_sale' => 0.5, 'wastage' => 4, 'rack' => 'Produce table 2'],
            ['sku' => 'LSE-00004', 'name' => 'Onion (Yellow)', 'category' => 'fresh-vegetables', 'supplier' => 'SUP-0002', 'unit' => 'kg', 'hs' => '0703', 'cost' => 2.90, 'price' => 4.50, 'reorder' => 25, 'max' => 250, 'min_sale' => 0.5, 'wastage' => 5, 'rack' => 'Produce table 2'],
            ['sku' => 'LSE-00005', 'name' => 'Green Capsicum', 'category' => 'fresh-vegetables', 'supplier' => 'SUP-0002', 'unit' => 'kg', 'hs' => '0709', 'cost' => 6.50, 'price' => 9.50, 'reorder' => 6, 'max' => 45, 'min_sale' => 0.25, 'wastage' => 9, 'expiry' => true, 'shelf_life' => 6, 'storage' => StorageType::Chilled, 'rack' => 'Produce table 3'],
            ['sku' => 'LSE-00006', 'name' => 'Banana (Philippines)', 'category' => 'fresh-fruits', 'supplier' => 'SUP-0002', 'unit' => 'kg', 'hs' => '0803', 'cost' => 4.80, 'price' => 7.00, 'reorder' => 12, 'max' => 100, 'min_sale' => 0.5, 'wastage' => 9, 'expiry' => true, 'shelf_life' => 5, 'rack' => 'Fruit rack 1'],
            ['sku' => 'LSE-00007', 'name' => 'Apple Red (Imported)', 'category' => 'imported-produce', 'supplier' => 'SUP-0002', 'unit' => 'kg', 'hs' => '0808', 'cost' => 8.20, 'price' => 11.50, 'reorder' => 10, 'max' => 80, 'min_sale' => 0.25, 'wastage' => 5, 'expiry' => true, 'shelf_life' => 14, 'storage' => StorageType::Chilled, 'rack' => 'Fruit rack 2'],
            ['sku' => 'LSE-00008', 'name' => 'Orange (Valencia)', 'category' => 'fresh-fruits', 'supplier' => 'SUP-0002', 'unit' => 'kg', 'hs' => '0805', 'cost' => 4.40, 'price' => 6.50, 'reorder' => 12, 'max' => 90, 'min_sale' => 0.5, 'wastage' => 6, 'expiry' => true, 'shelf_life' => 12, 'rack' => 'Fruit rack 1'],
            ['sku' => 'LSE-00009', 'name' => 'Parsley (Baqdounis)', 'category' => 'herbs-leaves', 'supplier' => 'SUP-0002', 'unit' => 'kg', 'hs' => '0709', 'cost' => 5.50, 'price' => 8.50, 'reorder' => 2, 'max' => 15, 'min_sale' => 0.1, 'wastage' => 15, 'expiry' => true, 'shelf_life' => 3, 'storage' => StorageType::Chilled, 'rack' => 'Produce table 3'],
            ['sku' => 'LSE-00010', 'name' => 'Sukkari Dates', 'category' => 'dates', 'supplier' => 'SUP-0005', 'unit' => 'kg', 'hs' => '0804', 'cost' => 24.00, 'price' => 32.00, 'reorder' => 15, 'max' => 120, 'min_sale' => 0.25, 'wastage' => 2, 'expiry' => true, 'shelf_life' => 180, 'rack' => 'Dates counter 1'],
            ['sku' => 'LSE-00011', 'name' => 'Ajwa Dates (Madinah)', 'category' => 'dates', 'supplier' => 'SUP-0005', 'unit' => 'kg', 'hs' => '0804', 'cost' => 68.00, 'price' => 90.00, 'reorder' => 5, 'max' => 40, 'min_sale' => 0.25, 'wastage' => 1, 'expiry' => true, 'shelf_life' => 240, 'rack' => 'Dates counter 1'],
            ['sku' => 'LSE-00012', 'name' => 'Almonds (Raw)', 'category' => 'nuts-seeds', 'supplier' => 'SUP-0005', 'unit' => 'kg', 'hs' => '0802', 'cost' => 34.00, 'price' => 45.00, 'reorder' => 5, 'max' => 40, 'min_sale' => 0.1, 'wastage' => 1, 'expiry' => true, 'shelf_life' => 240, 'rack' => 'Nuts counter'],
            ['sku' => 'LSE-00013', 'name' => 'Cashew Nuts (W240)', 'category' => 'nuts-seeds', 'supplier' => 'SUP-0005', 'unit' => 'kg', 'hs' => '0801', 'cost' => 42.00, 'price' => 55.00, 'reorder' => 4, 'max' => 30, 'min_sale' => 0.1, 'wastage' => 1, 'expiry' => true, 'shelf_life' => 180, 'rack' => 'Nuts counter'],
            ['sku' => 'LSE-00014', 'name' => 'Basmati Rice (Loose)', 'category' => 'basmati-rice', 'supplier' => 'SUP-0001', 'unit' => 'kg', 'hs' => '1006', 'cost' => 6.80, 'price' => 9.00, 'reorder' => 50, 'max' => 500, 'min_sale' => 1, 'wastage' => 1, 'rack' => 'Aisle 1 · Bin 1'],
            ['sku' => 'LSE-00015', 'name' => 'White Sugar (Loose)', 'category' => 'sugar-salt', 'supplier' => 'SUP-0001', 'unit' => 'kg', 'hs' => '1701', 'cost' => 3.10, 'price' => 4.25, 'reorder' => 30, 'max' => 250, 'min_sale' => 0.5, 'wastage' => 1, 'rack' => 'Aisle 1 · Bin 4'],
            ['sku' => 'LSE-00016', 'name' => 'Chickpeas (Loose)', 'category' => 'pulses-beans', 'supplier' => 'SUP-0001', 'unit' => 'kg', 'hs' => '0713', 'cost' => 5.20, 'price' => 7.50, 'reorder' => 20, 'max' => 150, 'min_sale' => 0.25, 'wastage' => 2, 'rack' => 'Aisle 1 · Bin 3'],
        ];
    }
}
