<?php

namespace App\Services;

use App\Models\User;
use App\Models\Badge;

class GamificationService
{
    /**
     * Tambahkan XP ke user dan cek apakah dia berhak dapat badge baru.
     */
    public static function addXpAndCheckBadges(User $user, int $xpEarned)
    {
        // 1. Tambahkan XP ke xp_points user (Diperbaiki dari total_xp)
        $user->xp_points += $xpEarned;
        $user->save();

        // 2. Ambil ID badge yang sudah dimiliki user agar tidak dobel
        $ownedBadgeIds = $user->badges()->pluck('badges.id')->toArray();

        // 3. Cari badge yang syarat XP-nya sudah terpenuhi, TAPI belum dimiliki user (Diperbaiki dari total_xp)
        $newBadges = Badge::where('required_xp', '<=', $user->xp_points)
                          ->whereNotIn('id', $ownedBadgeIds)
                          ->get();

        $awardedBadges = [];

        // 4. Berikan badge baru (jika ada)
        if ($newBadges->isNotEmpty()) {
            foreach ($newBadges as $badge) {
                // Attach ke tabel pivot user_badges
                $user->badges()->attach($badge->id, ['earned_at' => now()]);
                $awardedBadges[] = $badge;
            }
        }

        // Kembalikan data untuk keperluan response API (agar bisa muncul pop-up di frontend)
        return [
            'current_xp' => $user->xp_points, // Diperbaiki dari total_xp
            'xp_added' => $xpEarned,
            'new_badges_awarded' => $awardedBadges // Frontend bisa baca ini untuk nampilin animasi!
        ];
    }

    /**
     * Hitung ulang dan update skor readiness user berdasarkan penyelesaian task & valley.
     * Skor bertambah naik terus hingga maksimal 100%. Jika > 100 dibulatkan/dibatasi ke 100.
     */
    public static function updateReadinessScore(User $user)
    {
        $totalScholarshipTasks = \App\Models\UserMilestone::where('user_id', $user->id)
            ->whereNotNull('scholarship_id')
            ->count();

        if ($totalScholarshipTasks > 0) {
            $completedTasks = \App\Models\UserMilestone::where('user_id', $user->id)
                ->whereNotNull('scholarship_id')
                ->where('status', 'completed')
                ->count();

            // Hitung persentase tugas yang sudah selesai
            $completionPercent = (int) round(($completedTasks / $totalScholarshipTasks) * 100);

            // Nilai readiness bertambah secara progresif (minimal tidak pernah turun dari readiness_score yang ada)
            $newScore = max((int) $user->readiness_score, $completionPercent);

            // Dibatasi maksimal 100
            $finalScore = min(100, $newScore);

            $user->update(['readiness_score' => $finalScore]);
            return $finalScore;
        } else {
            // Jika belum ada timeline beasiswa, tambah 5 poin per penyelesaian task dasar (maksimal 100)
            $finalScore = min(100, (int) $user->readiness_score + 5);
            $user->update(['readiness_score' => $finalScore]);
            return $finalScore;
        }
    }

    /**
     * Alias method untuk recalculateReadinessScore agar kompatibel dengan semua service.
     */
    public static function recalculateReadinessScore(User $user)
    {
        return static::updateReadinessScore($user);
    }
}