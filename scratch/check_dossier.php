<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ConsultationBooking;
use App\Models\User;
use App\Models\UserMilestone;

echo "=== INSPECTING CONSULTATION BOOKINGS & DOSSIER ===\n";

$bookings = ConsultationBooking::with('mentee')->get();
foreach ($bookings as $b) {
    $milestoneCount = UserMilestone::where('user_id', $b->mentee_id)->count();
    $menteeName = $b->mentee ? $b->mentee->name : 'N/A';
    echo "Booking ID #{$b->id}: Mentee ID {$b->mentee_id} ({$menteeName}) | Milestones Count: {$milestoneCount}\n";
}

echo "\nChecking User ID = 4 (Jokowi dodo):\n";
$jokowiMilestones = UserMilestone::where('user_id', 4)->count();
echo "Jokowi (user_id = 4) total milestones in DB: {$jokowiMilestones}\n";
