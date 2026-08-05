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
}