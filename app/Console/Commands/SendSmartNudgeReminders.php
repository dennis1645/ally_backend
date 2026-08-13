<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UserMilestone; 
use App\Models\ScholarshipApplication; 
use App\Models\ActionPlan; 
use App\Mail\SmartNudgeMail;
use Carbon\Carbon;

class SendSmartNudgeReminders extends Command
{
    protected $signature = 'nudge:send-reminders';
    protected $description = 'Mengirim email pengingat H-3, H-1, Hari H, dan H+1 serta mengatur pembekuan streak.';

    public function handle()
    {
        $today = Carbon::now()->startOfDay();
        
        $targetDates = [
            'H-3'   => $today->copy()->addDays(3)->toDateString(),
            'H-1'   => $today->copy()->addDays(1)->toDateString(),
            'Hari H'=> $today->toDateString(),
            'H+1'   => $today->copy()->subDays(1)->toDateString(),
        ];
        $datesList = array_values($targetDates);

        $this->info("=========================================");
        $this->info("Memulai proses Smart Nudge Reminders...");
        $this->info("Target Tanggal Pencarian: " . implode(', ', $datesList));
        $this->info("=========================================");

        // ==========================================
        // 1. Proses UserMilestone (Menggunakan whereDate yang lebih aman)
        // ==========================================
        $milestones = UserMilestone::where(function($q) {
                $q->whereNull('completed_at')->orWhere('status', 'pending');
            })
            ->where(function($q) use ($datesList) {
                foreach ($datesList as $date) {
                    $q->orWhereDate('target_date', $date);
                }
            })
            ->get();
        
        $this->info("🔍 [UserMilestone] Ditemukan: " . $milestones->count() . " data.");
        $this->processNotification($milestones, 'target_date', 'task_name', 'Milestone/Task', $targetDates);

        // ==========================================
        // 2. Proses Deadline Beasiswa
        // ==========================================
        if (class_exists(ScholarshipApplication::class)) {
            $scholarships = ScholarshipApplication::where('is_completed', false)
                ->where(function($q) use ($datesList) {
                    foreach ($datesList as $date) {
                        $q->orWhereDate('closing_date', $date);
                    }
                })
                ->get();
                
            $this->info("🔍 [Scholarship] Ditemukan: " . $scholarships->count() . " data.");
            $this->processNotification($scholarships, 'closing_date', 'title', 'Pendaftaran Beasiswa', $targetDates);
        }

        // ==========================================
        // 3. Proses Action Plans (Tugas Mentor)
        // ==========================================
        if (class_exists(ActionPlan::class)) {
            $actionPlans = ActionPlan::where('is_completed', false)
                ->where(function($q) use ($datesList) {
                    foreach ($datesList as $date) {
                        $q->orWhereDate('deadline', $date);
                    }
                })
                ->get();
                
            $this->info("🔍 [ActionPlan] Ditemukan: " . $actionPlans->count() . " data.");
            $this->processNotification($actionPlans, 'deadline', 'task_description', 'Tugas Mentor', $targetDates);
        }

        $this->info("=========================================");
        $this->info("✅ Proses selesai.");
    }

    /**
     * Reusable logic untuk mengirim notifikasi dan freeze streak
     */
    private function processNotification($items, $dateColumn, $titleColumn, $itemType, $targetDates)
    {
        if ($items->isEmpty()) {
            return;
        }

        foreach ($items as $item) {
            $userId = $item->mentee_id ?? $item->user_id; 
            $user = $item->user ?? $item->mentee ?? User::find($userId);
            
            if (!$user || empty($user->email)) {
                $this->warn("⚠️ Skipped {$itemType} ID {$item->id}: User tidak ditemukan atau email kosong.");
                continue;
            }

            $itemDate = Carbon::parse($item->$dateColumn)->toDateString();
            $context = array_search($itemDate, $targetDates); 

            // Logic Freeze Streak (Jika sudah Overdue / H+1)
            if ($context === 'H+1' && !$user->is_streak_frozen) {
                $user->is_streak_frozen = true;
                $user->save();
                
                Log::info("Streak dibekukan untuk User ID: {$user->id} karena {$itemType} overdue.");
                $this->info("❄️ STREAK DIBEKUKAN untuk {$user->email} (Overdue)");
            }

            try {
                $itemName = $item->$titleColumn ?? 'Tugas Terjadwal';

                Mail::to($user->email)->send(
                    new SmartNudgeMail($user, $context, $itemName, $itemType)
                );
                
                $this->info("📧 ✅ Email [{$context}] terkirim ke {$user->email} untuk tugas: {$itemName}");
            } catch (\Exception $e) {
                Log::error("Gagal mengirim email nudge ke {$user->email}. Error: " . $e->getMessage());
                $this->error("❌ Gagal kirim email ke {$user->email}: " . $e->getMessage());
            }
        }
    }
}