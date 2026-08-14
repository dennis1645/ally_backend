<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\DailyDrill;

echo "=== TESTING ENRICHED SINGLE HISTORY ENDPOINT ===\n";

$user = User::find(4); // Mentee Jokowi

$history = DailyDrill::with(['answers.question', 'answers.selectedOption'])
                     ->where('user_id', $user->id)
                     ->orderBy('created_at', 'desc')
                     ->get();

$totalDrills = $history->count();
$totalXp = (int) $history->sum('xp_earned');
$avgScore = $totalDrills > 0 ? round($history->avg('total_score'), 1) : 0;

$response = [
    'status' => 'success',
    'message' => 'User drill history retrieved successfully.',
    'data' => [
        'summary_statistics' => [
            'total_drills_completed' => $totalDrills,
            'total_xp_earned' => $totalXp,
            'average_score' => $avgScore,
        ],
        'history' => $history
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
