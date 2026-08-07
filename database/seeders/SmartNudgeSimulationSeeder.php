<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserMilestone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash; // Wajib di-import untuk Hash password

class SmartNudgeSimulationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $today = Carbon::now()->startOfDay();

        // 1. Buat/Update User Simulasi sesuai data yang kamu berikan
        $user = User::updateOrCreate(
            ['email' => 'jokowi.user@gmail.com'],
            [
                // ID dihilangkan agar MySQL bebas mengatur Auto Increment dan tidak bentrok
                'name' => 'Jokowi dodo',
                'phone_number' => '084444444444',
                'password' => Hash::make('password123'), // Perbaikan: Menggunakan Hash::make
                'email_verified_at' => now(),
                'role' => 'user',
                'status' => 'active',
                'current_streak' => 5,
                'is_streak_frozen' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Hapus milestone lama milik user ini (opsional, agar bersih saat seeding ulang)
        UserMilestone::where('user_id', $user->id)->delete();

        // 2. Buat Dummy Milestone untuk menguji 4 Kondisi Nudge Berbeda

        // Kondisi A: Hari H (Deadline tepat hari ini)
        UserMilestone::create([
            'user_id' => $user->id,
            'task_name' => 'Finalisasi Dokumen Pendaftaran Beasiswa (Hari H)',
            'target_deadline' => $today->copy(),
            'status' => 'pending',
        ]);

        // Kondisi B: H-1 (Deadline besok)
        UserMilestone::create([
            'user_id' => $user->id,
            'task_name' => 'Review Essay Motivasi Bersama Mentor (H-1)',
            'target_deadline' => $today->copy()->addDays(1),
            'status' => 'pending',
        ]);

        // Kondisi C: H-3 (Deadline 3 hari lagi)
        UserMilestone::create([
            'user_id' => $user->id,
            'task_name' => 'Simulasi Tes IELTS / Bahasa Inggris (H-3)',
            'target_deadline' => $today->copy()->addDays(3),
            'status' => 'pending',
        ]);

        // Kondisi D: H+1 / Overdue (Deadline kemarin - untuk menguji Streak Freeze)
        UserMilestone::create([
            'user_id' => $user->id,
            'task_name' => 'Upload Sertifikat Pendukung (Overdue H+1)',
            'target_deadline' => $today->copy()->subDays(1),
            'status' => 'pending',
        ]);

        $this->command->info("✅ Seeder Smart Nudge berhasil dijalankan untuk user: {$user->name}");
        $this->command->info("👉 Disiapkan 4 milestone (H-3, H-1, Hari H, dan H+1) untuk memicu semua email dan Freeze Streak.");
    }
}