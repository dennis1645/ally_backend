<?php

use Illuminate\Support\Facades\Schedule;
use App\Models\ConsultationBooking;
use Illuminate\Support\Facades\Log;

// Task 2.3: Auto-Close Sesi Mentoring yang Lewat Batas Waktu
Schedule::call(function () {
    Log::info('Menjalankan pengecekan sesi konsultasi yang menggantung (Auto-close 3 Jam)...');

    // Tentukan waktu batas acuan (3 jam yang lalu dari waktu sekarang)
    $threeHoursAgo = now()->subHours(3);

    // Ambil booking berstatus 'confirmed' yang jadwal selesainya sudah terlewat lebih dari 3 jam
    $expiredBookings = ConsultationBooking::where('session_status', 'confirmed')
        ->whereHas('availability', function ($query) use ($threeHoursAgo) {
            $query->where('available_date', '<', $threeHoursAgo->toDateString())
                  ->orWhere(function ($q) use ($threeHoursAgo) {
                      $q->where('available_date', '=', $threeHoursAgo->toDateString())
                        ->where('end_time', '<=', $threeHoursAgo->toTimeString());
                  });
        })->get();

    foreach ($expiredBookings as $booking) {
        $booking->update(['session_status' => 'completed']);
        Log::info("Booking ID {$booking->id} otomatis di-close karena melewati batas waktu 3 jam setelah sesi selesai.");
    }
    
})->hourly(); // Scheduler tetap dicek setiap jam