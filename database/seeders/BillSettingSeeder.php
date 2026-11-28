<?php

namespace Database\Seeders;
use App\Models\BillSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BillSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BillSetting::create([
            'logo_path'         => null,
            'company_name'       => 'Natural Cosmetics',
            'address'            => 'No:5, Shopping complex, kochchikade.',
            'mobile_1'           => '0123456789',
            'mobile_2'           => null,
            'email'              => 'info@naturalcosmetics.lk',
            'website_url'        => 'www.naturalcosmetics.lk',
            'footer_description' => 'Thank you for your business!',
            'print_size'         => '80mm', // default value
        ]);
    }
}
