<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'code' => 'SUP-0001',
                'name' => 'Al Rajhi Foodstuff Trading',
                'contact_person' => 'Khalid Al Otaibi',
                'phone' => '0551234567',
                'email' => 'orders@alrajhifoods.test',
                'vat_number' => '300012345600003',
                'address' => 'Al Sinaiyah District',
                'city' => 'Riyadh',
                'state' => 'Riyadh Province',
                'pincode' => '11564',
                'payment_terms_days' => 30,
            ],
            [
                'code' => 'SUP-0002',
                'name' => 'Wadi Green Farms',
                'contact_person' => 'Faisal Al Harbi',
                'phone' => '0553456789',
                'email' => 'supply@wadigreen.test',
                'address' => 'Wadi Al Dawasir Road',
                'city' => 'Al Kharj',
                'state' => 'Riyadh Province',
                'pincode' => '16273',
                'payment_terms_days' => 0,
            ],
            [
                'code' => 'SUP-0003',
                'name' => 'Jeddah Wholesale Market Co.',
                'contact_person' => 'Sami Al Ghamdi',
                'phone' => '0564567890',
                'email' => 'sales@jeddahwholesale.test',
                'vat_number' => '300098765400003',
                'address' => 'Al Balad',
                'city' => 'Jeddah',
                'state' => 'Makkah Province',
                'pincode' => '22233',
                'payment_terms_days' => 15,
            ],
            [
                'code' => 'SUP-0004',
                'name' => 'Almarai Distribution',
                'contact_person' => 'Nasser Al Qahtani',
                'phone' => '0567890123',
                'email' => 'trade@almarai-dist.test',
                'vat_number' => '300055566600003',
                'address' => 'Exit 18, Eastern Ring Road',
                'city' => 'Riyadh',
                'state' => 'Riyadh Province',
                'pincode' => '11492',
                'payment_terms_days' => 7,
            ],
            [
                'code' => 'SUP-0005',
                'name' => 'Qassim Dates & Nuts Est.',
                'contact_person' => 'Abdulaziz Al Shammari',
                'phone' => '0559871234',
                'email' => 'info@qassimdates.test',
                'vat_number' => '300077788800003',
                'city' => 'Buraydah',
                'state' => 'Al Qassim',
                'pincode' => '52371',
                'payment_terms_days' => 15,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(['code' => $supplier['code']], [...$supplier, 'is_active' => true]);
        }
    }
}
