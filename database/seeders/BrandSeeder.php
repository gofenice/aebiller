<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Almarai', 'Nadec', 'Al Safi', 'Sadia', 'Americana', 'Goody', 'Al Rabie',
            'Sunbulah', 'Afia', 'Abu Kass', 'Al Kabeer', 'Nestlé', 'Lipton', 'Tide',
            'Signal', 'Lurpak',
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(['slug' => Str::slug($brand)], ['name' => $brand, 'is_active' => true]);
        }
    }
}
