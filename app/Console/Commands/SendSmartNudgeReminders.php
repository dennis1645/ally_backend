<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Models\UserMilestone; // Memanggil model yang benar
use App\Models\ScholarshipApplication; // Asumsi dari kode sebelumnya
use App\Mail\SmartNudgeMail;
use Carbon\Carbon;

class SendSmartNudgeReminders extends Command
{
    protected $signature = 'nudge:send-reminders';
    protected $description = 'Mengirim email pengingat H-3, H-1, Hari H, dan H+1 serta mengatur pembekuan streak.';

    public function handle()
    {
        $today = Carbon::now()->startOfDay();

        // Pemetaan target tanggal
        $targetDates = [
            'H-3'   => $today->copy()->addDays(3)->toDateString(),
            'H-1'   => $today->copy()->addDays(1)->toDateString(),
            'Hari H'=> $today->toDateString(),
            'H+1'   => $today->copy()->subDays(1)->toDateString(),
        ];

        $this->info("Memulai proses Smart Nudge Reminders...");

        // ==========================================
        // 1. Proses UserMilestone (Task Utama & Subtask)
        // ==========================================
        $milestones = UserMilestone::whereNull('completed_at') // Cari yang belum selesai
            ->whereIn(DB::raw("DATE(target_deadline)"), array_values($targetDates))
            ->with('user')
            ->get();

        $this->processNotification($milestones, 'target_deadline', 'task_name', 'Milestone/Task', $targetDates);


        // ==========================================
        // 2. Proses Deadline Beasiswa
        // ==========================================
        // Pastikan model ini ada dan sesuaikan nama kolom status/selesainya
        if (class_exists(ScholarshipApplication::class)) {
            $scholarships = ScholarshipApplication::where('is_completed', false) 
                ->whereIn(DB::raw("DATE(closing_date)"), array_values($targetDates))
                ->with('user')
                ->get();

            $this->processNotification($scholarships, 'closing_date', 'title', 'Pendaftaran Beasiswa', $targetDates);
        }

        $this->info("Proses selesai.");
    }

    /**
     * Reusable logic untuk mengirim notifikasi dan freeze streak
     * Format dipindah agar lebih fleksibel menerima Collection hasil query
     */
    private function processNotification($items, $dateColumn, $titleColumn, $itemType, $targetDates)
    {
        foreach ($items as $item) {
            $user = $item->user;
            
            // Skip kalau data user tidak ada atau email kosong
            if (!$user || empty($user->email)) {
                continue;
            }

            $itemDate = Carbon::parse($item->$dateColumn)->toDateString();
            $context = array_search($itemDate, $targetDates); // Dapatkan key: 'H-3', 'H-1', dll.

            // Logic Freeze Streak (Jika sudah Overdue / H+1)
            // Asumsi di tabel User sudah ditambahkan kolom 'is_streak_frozen'
            if ($context === 'H+1' && !$user->is_streak_frozen) {
                $user->is_streak_frozen = true;
                $user->save();
                
                Log::info("Streak dibekukan untuk User ID: {$user->id} karena {$itemType} overdue.");
            }

            // Error Handling: Try-Catch agar cron tidak mati jika SMTP bermasalah
            try {
                // Ambil nama task (dinamis berdasarkan nama kolom)
                $itemName = $item->$titleColumn ?? 'Tugas Terjadwal';

                Mail::to($user->email)->send(
                    new SmartNudgeMail($user, $context, $itemName, $itemType)
                );
                
                $this->info("Email {$context} terkirim ke {$user->email} untuk {$itemType}");
            } catch (\Exception $e) {
                // Catat error tapi biarkan loop tetap berjalan untuk user/item lain
                Log::error("Gagal mengirim email nudge ke {$user->email}. Error: " . $e->getMessage());
            }
        }
    }
}