<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShopItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $items = [
            // 1. Paket Langganan Premium (Yearly)
            [
                'name' => 'Premium 1 Tahun',
                'description' => 'Akses eksklusif ke semua fitur premium, materi tingkat lanjut, dan bonus 7 Token Mentor.',
                'item_type' => 'subscription',
                'price_rupiah' => 150000.00, // Menyesuaikan nama kolom
                'price_xp' => 0,
                'token_reward' => 7,  // Menyesuaikan nama kolom
                'duration_days' => 365, // Aktif selama 1 tahun
                'stock_quantity' => 9999,
                'image_url' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // 2. Paket Eceran - 1 Token
            [
                'name' => '1 Token Mentor',
                'description' => 'Gunakan token ini untuk mem-booking 1 sesi konsultasi 1-on-1 dengan mentor pilihanmu.',
                'item_type' => 'token_package', // Menyesuaikan enum migration
                'price_rupiah' => 25000.00, 
                'price_xp' => 0,
                'token_reward' => 1,
                'duration_days' => null, 
                'stock_quantity' => 9999,
                'image_url' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // 3. Paket Bundel - 3 Token (Lebih hemat)
            [
                'name' => 'Bundel 3 Token Mentor',
                'description' => 'Lebih hemat! Dapatkan 3 Token Mentor untuk mempercepat persiapan beasiswamu.',
                'item_type' => 'token_package',
                'price_rupiah' => 65000.00, 
                'price_xp' => 0,
                'token_reward' => 3,
                'duration_days' => null,
                'stock_quantity' => 9999,
                'image_url' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // 4. Paket Bundel - 5 Token (Paling hemat)
            [
                'name' => 'Bundel 5 Token Mentor',
                'description' => 'Paket paling hemat! Dapatkan 5 Token Mentor untuk konsultasi intensif.',
                'item_type' => 'token_package',
                'price_rupiah' => 100000.00, 
                'price_xp' => 0,
                'token_reward' => 5,
                'duration_days' => null,
                'stock_quantity' => 9999,
                'image_url' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('shop_items')->insert($items);
    }
}