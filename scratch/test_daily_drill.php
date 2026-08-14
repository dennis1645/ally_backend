<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\DailyDrill;

echo "=== CHECKING DAILY DRILLS IN DATABASE ===\n";

$drills = DailyDrill::with('user')->get();

echo "Total Daily Drills Recorded: " . $drills->count() . "\n\n";

foreach ($drills as $d) {
    echo "- Drill ID #{$d->id} | User: {$d->user->name} | Date: {$d->drill_date} | Correct: {$d->correct_answers}/{$d->total_questions} | Score: {$d->total_score} | XP Earned: {$d->xp_earned}\n";
}
