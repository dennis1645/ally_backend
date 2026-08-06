<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use App\Models\ConsultationBooking;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

// Task 1: Smart Nudge Reminders (Gamifikasi & Email Pengingat Milestone/Subtask)
Schedule::command('nudge:send-reminders')->dailyAt('07:00');


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