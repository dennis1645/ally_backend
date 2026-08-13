<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\AIMentorChatService;

echo "=== TESTING ENRICHED ENGLISH AI MENTOR CHAT SERVICE ===\n";

$user = User::find(4); // Mentee Jokowi
$service = new AIMentorChatService();

$result = $service->chat($user, "What should I focus on next for my LPDP application, and how do I handle my mentor's action plans?");

echo "USER MESSAGE:\n" . $result['user_message'] . "\n\n";
echo "AI RESPONSE:\n" . $result['ai_response'] . "\n";
