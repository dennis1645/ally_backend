<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\MentorProfile;
use App\Models\MentorAvailability;
use App\Models\ConsultationBooking;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

echo "=== Seeding Mentor Daniel & Mentee Jokowi Booking Data ===\n";

// 1. Cari atau buat Mentor Daniel
$mentorDaniel = User::where('email', 'mentor_daniel@gmail.com')->first();
if (!$mentorDaniel) {
    $mentorDaniel = User::create([
        'name' => 'Mentor Daniel',
        'email' => 'mentor_daniel@gmail.com',
        'phone_number' => '081299990001',
        'password' => Hash::make('P@ssw0rd123'),
        'role' => 'mentor',
        'status' => 'active',
        'headline' => 'LPDP & Chevening Senior Mentor',
        'bio' => 'Spesialis pembimbing esai kepemimpinan dan persiapan interview beasiswa LPDP & UK Chevening.',
        'session_rate' => 150000,
    ]);

    MentorProfile::create([
        'user_id' => $mentorDaniel->id,
        'university' => 'University of Oxford',
        'degree' => 'Master of Science (MSc)',
        'major' => 'Public Policy',
        'expertise' => 'Scholarship Essay & Interview Coaching',
        'bio' => 'Senior Mentor LPDP & Chevening Oxford Alumni.',
        'rating' => 4.95,
    ]);

    echo "✅ Mentor Daniel dibuat (ID: {$mentorDaniel->id}).\n";
} else {
    echo "ℹ️ Mentor Daniel sudah ada (ID: {$mentorDaniel->id}).\n";
}

// 2. Cari Mentee Jokowi (atau user_id = 4)
$menteeJokowi = User::where('email', 'jokowi.user@gmail.com')->first();
if (!$menteeJokowi) {
    $menteeJokowi = User::find(4);
}

if (!$menteeJokowi) {
    echo "❌ Mentee Jokowi tidak ditemukan!\n";
    exit(1);
}

echo "✅ Mentee Jokowi ditemukan (ID: {$menteeJokowi->id}, Name: {$menteeJokowi->name}).\n";

// Hubungkan Jokowi dengan Mentor Daniel
$menteeJokowi->update([
    'assigned_mentor_id' => $mentorDaniel->id,
    'is_premium' => true,
    'token_balance' => max($menteeJokowi->token_balance, 3),
]);
echo "✅ Assigned mentor Jokowi di-set ke Mentor Daniel (ID: {$mentorDaniel->id}).\n";

// 3. Buat Jadwal Availability Mentor Daniel untuk Besok
$tomorrow = Carbon::now()->addDay()->toDateString();
$availability = MentorAvailability::create([
    'mentor_id' => $mentorDaniel->id,
    'available_date' => $tomorrow,
    'start_time' => '14:00:00',
    'end_time' => '15:00:00',
    'is_booked' => true,
]);
echo "✅ MentorAvailability dibuat (ID: {$availability->id}, Date: {$tomorrow} 14:00 - 15:00).\n";

// 4. Buat ConsultationBooking antara Jokowi & Mentor Daniel
$booking = ConsultationBooking::create([
    'mentee_id' => $menteeJokowi->id,
    'mentor_id' => $mentorDaniel->id,
    'availability_id' => $availability->id,
    'token_cost' => 1,
    'mentor_earned_fee' => 150000,
    'session_status' => 'confirmed',
    'meeting_link' => 'https://meet.google.com/ally-daniel-jokowi',
    'reschedule_acknowledged' => true,
]);

echo "🎉 ConsultationBooking BERSATU Berhasil Disuntikkan ke Database!\n";
echo "   - Booking ID: {$booking->id}\n";
echo "   - Mentee: {$menteeJokowi->name} ({$menteeJokowi->email})\n";
echo "   - Mentor: {$mentorDaniel->name} ({$mentorDaniel->email})\n";
echo "   - Status: confirmed\n";
echo "   - Meeting Link: {$booking->meeting_link}\n";
echo "   - Tanggal: {$tomorrow} (14:00 - 15:00)\n";
