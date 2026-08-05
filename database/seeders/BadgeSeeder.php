<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Badge;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Daftar badge dengan tingkatan XP yang berbeda
        $badges = [
            [
                'name' => 'Scholar Starter',
                'description' => 'Langkah pertama menuju beasiswa impian. Diberikan saat kamu memulai perjalanan belajarmu!',
                'icon_url' => 'https://ui-avatars.com/api/?name=Starter&background=EBF4FF&color=4299E1&bold=true',
                'required_xp' => 0,
            ],
            [
                'name' => 'Curious Mind',
                'description' => 'Kamu mulai rajin mengerjakan daily drill dan mendapatkan pengetahuan baru.',
                'icon_url' => 'https://ui-avatars.com/api/?name=Curious&background=E6FFFA&color=319795&bold=true',
                'required_xp' => 100, // Butuh 100 XP
            ],
            [
                'name' => 'Persistent Learner',
                'description' => 'Konsistensi adalah kunci! Kamu telah membuktikan dedikasimu dalam belajar dan mengerjakan task.',
                'icon_url' => 'https://ui-avatars.com/api/?name=Learner&background=FEFCBF&color=D69E2E&bold=true',
                'required_xp' => 500, // Butuh 500 XP
            ],
            [
                'name' => 'Test Challenger',
                'description' => 'Telah menaklukkan banyak soal simulasi bahasa Inggris (TOEFL/IELTS) dengan skor gemilang.',
                'icon_url' => 'https://ui-avatars.com/api/?name=Challenger&background=FEEBC8&color=DD6B20&bold=true',
                'required_xp' => 1200, // Butuh 1200 XP
            ],
            [
                'name' => 'Mentor\'s Pride',
                'description' => 'Menyelesaikan banyak Action Plan dari mentor dan menunjukkan progres kesiapan yang luar biasa.',
                'icon_url' => 'https://ui-avatars.com/api/?name=Pride&background=E9D8FD&color=805AD5&bold=true',
                'required_xp' => 3000, // Butuh 3000 XP
            ],
            [
                'name' => 'Master Scholar',
                'description' => 'Pencapaian tertinggi! Kesiapanmu sudah maksimal untuk meraih beasiswa impian. Go get it!',
                'icon_url' => 'https://ui-avatars.com/api/?name=Master&background=FED7D7&color=E53E3E&bold=true',
                'required_xp' => 5000, // Butuh 5000 XP
            ],
        ];

        foreach ($badges as $badge) {
            // Menggunakan updateOrCreate agar jika di-seed ulang, datanya tidak double (duplikat)
            Badge::updateOrCreate(
                ['name' => $badge['name']], // Cari berdasarkan nama
                $badge // Data yang akan di-insert/update
            );
        }
    }
}