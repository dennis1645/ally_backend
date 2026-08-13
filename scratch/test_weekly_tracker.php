<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "=== TESTING WEEKLY STREAK TRACKER PAYLOAD ===\n";

$user = User::find(4); // Mentee Jokowi

$userData = $user->toArray();

echo "User Profile Payload Sample:\n";
echo "- Name: " . $userData['name'] . "\n";
echo "- Current Streak: " . $userData['current_streak'] . "\n";
echo "- Is Streak Frozen: " . ($userData['is_streak_frozen'] ? 'true ❄️' : 'false') . "\n\n";

echo "Weekly Streak Tracker (Mon - Sun):\n";
echo json_encode($userData['weekly_streak_tracker'], JSON_PRETTY_PRINT) . "\n";
