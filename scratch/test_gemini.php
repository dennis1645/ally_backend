<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$geminiApiKey = env('GEMINI_API_KEY');

echo "=== TESTING GEMINI FLASH LATEST ===\n";

$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . trim($geminiApiKey);

$response = Http::withHeaders(['Content-Type' => 'application/json'])
    ->timeout(15)
    ->post($apiUrl, [
        'contents' => [
            ['parts' => [['text' => 'Halo mentor!']]]
        ]
    ]);

echo "HTTP Status: " . $response->status() . "\n";
if ($response->successful()) {
    $resData = $response->json();
    $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;
    echo "Response Text:\n" . $text . "\n";
} else {
    echo "Error Body:\n" . $response->body() . "\n";
}
