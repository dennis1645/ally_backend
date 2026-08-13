<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\AIMentorChatService;

echo "=== TESTING AI MENTOR CHAT SERVICE ===\n";

$user = User::find(4); // Mentee Jokowi
$service = new AIMentorChatService();

$result = $service->chat($user, "Bagaimana cara terbaik mempersiapkan esai LPDP untuk jurusan Public Policy?");

echo "USER MESSAGE:\n" . $result['user_message'] . "\n\n";
echo "AI RESPONSE:\n" . $result['ai_response'] . "\n";
