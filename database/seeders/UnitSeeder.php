<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Units a supermarket sells in — pieces and packs for packet goods,
     * weight and volume for loose produce.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'Piece', 'code' => 'pc', 'allows_decimal' => false],
            ['name' => 'Packet', 'code' => 'pkt', 'allows_decimal' => false],
            ['name' => 'Box', 'code' => 'box', 'allows_decimal' => false],
            ['name' => 'Bottle', 'code' => 'btl', 'allows_decimal' => false],
            ['name' => 'Dozen', 'code' => 'dzn', 'allows_decimal' => false],
            ['name' => 'Bundle', 'code' => 'bdl', 'allows_decimal' => false],
            ['name' => 'Kilogram', 'code' => 'kg', 'allows_decimal' => true],
            ['name' => 'Gram', 'code' => 'g', 'allows_decimal' => true],
            ['name' => 'Litre', 'code' => 'l', 'allows_decimal' => true],
            ['name' => 'Millilitre', 'code' => 'ml', 'allows_decimal' => true],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(['code' => $unit['code']], [...$unit, 'is_active' => true]);
        }
    }
}
