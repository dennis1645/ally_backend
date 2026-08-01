<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Password default yang memenuhi syarat sistem (Minimal 8 karakter, huruf besar & kecil, angka, dan simbol)
        $defaultPassword = Hash::make('P@ssw0rd123');

        // ==========================================
        // 1 AKUN ADMIN
        // ==========================================
        User::create([
            'name'              => 'Junaidi Admin',
            'email'             => 'juna.admin@gmail.com',
            'phone_number'      => '081111111111',
            'password'          => $defaultPassword,
            'role'              => 'admin',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        // ==========================================
        // 2 AKUN MENTOR
        // ==========================================
        User::create([
            'name'              => 'Dewi',
            'email'             => 'dewi.mentor@gmail.com',
            'phone_number'      => '082222222222',
            'password'          => $defaultPassword,
            'role'              => 'mentor',
            'status'            => 'active',
            'email_verified_at' => now(),
            'headline'          => 'Senior Academic Advisor & Tech Mentor',
            'bio'               => 'Berpengalaman dalam membimbing mahasiswa untuk mendapatkan beasiswa dan riset AI.',
        ]);

        User::create([
            'name'              => 'Khalisa',
            'email'             => 'khalisa.mentor@gmail.com',
            'phone_number'      => '083333333333',
            'password'          => $defaultPassword,
            'role'              => 'mentor',
            'status'            => 'active',
            'email_verified_at' => now(),
            'headline'          => 'Corporate Presentation Specialist',
            'bio'               => 'Fokus pada public speaking dan persiapan wawancara beasiswa.',
        ]);

        // ==========================================
        // 2 AKUN MENTEE / USER BIASA
        // ==========================================
        User::create([
            'name'              => 'Jokowi dodo',
            'email'             => 'jokowi.user@gmail.com',
            'phone_number'      => '084444444444',
            'password'          => $defaultPassword,
            'role'              => 'user',
            'status'            => 'active',
            'email_verified_at' => now(),
            'readiness_score'   => 45,
            'xp_points'         => 120,
            'is_premium'        => false,
        ]);

        User::create([
            'name'              => 'Ridho',
            'email'             => 'ridho.user@gmail.com',
            'phone_number'      => '085555555555',
            'password'          => $defaultPassword,
            'role'              => 'user',
            'status'            => 'active',
            'email_verified_at' => now(),
            'readiness_score'   => 80,
            'xp_points'         => 450,
            'is_premium'        => true, // Contoh akun mentee yang sudah bayar premium
        ]);
    }
}