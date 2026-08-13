<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "=== TESTING PROFILE STREAK FREEZE ATTRIBUTES ===\n";

$user = User::find(4); // Mentee Jokowi

// Simulasi set is_streak_frozen = true untuk pengujian
$user->update([
    'current_streak' => 5,
    'longest_streak' => 12,
    'is_streak_frozen' => true,
]);

$userData = $user->toArray();

echo "User Profile Payload Sample:\n";
echo "- Name: " . $userData['name'] . "\n";
echo "- Current Streak: " . $userData['current_streak'] . "\n";
echo "- Longest Streak: " . $userData['longest_streak'] . "\n";
echo "- Is Streak Frozen: " . ($userData['is_streak_frozen'] ? 'true (FROZEN ❄️)' : 'false') . "\n";
