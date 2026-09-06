<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * A typical Saudi supermarket aisle plan, two levels deep.
     */
    public function run(): void
    {
        $tree = [
            'Fruits & Vegetables' => ['Fresh Vegetables', 'Fresh Fruits', 'Herbs & Leaves', 'Imported Produce'],
            'Dates & Nuts' => ['Dates', 'Nuts & Seeds', 'Dried Fruits'],
            'Rice & Grains' => ['Basmati Rice', 'Flour', 'Pulses & Beans', 'Sugar & Salt'],
            'Dairy & Eggs' => ['Fresh Milk', 'Laban & Yoghurt', 'Cheese & Butter', 'Eggs'],
            'Bakery' => ['Arabic Bread', 'Sliced Bread & Buns', 'Cakes & Biscuits'],
            'Meat & Poultry' => ['Fresh Chicken', 'Frozen Poultry', 'Fresh Meat'],
            'Beverages' => ['Tea & Coffee', 'Juices', 'Soft Drinks', 'Water'],
            'Snacks & Packaged Food' => ['Chips & Savoury', 'Chocolates & Sweets', 'Pasta & Noodles', 'Sauces & Spreads'],
            'Oils & Spices' => ['Cooking Oils', 'Whole Spices', 'Ground Spices'],
            'Frozen & Chilled' => ['Frozen Vegetables', 'Ice Cream', 'Ready Meals'],
            'Household' => ['Cleaning Supplies', 'Detergents', 'Kitchen Essentials'],
            'Personal Care' => ['Soaps & Body Wash', 'Hair Care', 'Oral Care'],
        ];

        $sort = 0;

        foreach ($tree as $parentName => $children) {
            $parent = Category::updateOrCreate(
                ['slug' => Str::slug($parentName)],
                ['name' => $parentName, 'is_active' => true, 'sort_order' => $sort += 10],
            );

            $childSort = 0;

            foreach ($children as $childName) {
                Category::updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'is_active' => true,
                        'sort_order' => $childSort += 10,
                    ],
                );
            }
        }
    }
}
