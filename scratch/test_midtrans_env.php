<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Midtrans\Config;

echo "=== TESTING MIDTRANS ENV READING ===\n";

$serverKey = config('midtrans.server_key') ?? env('MIDTRANS_SERVER_KEY');
$isProduction = config('midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false);

Config::$serverKey = $serverKey;
Config::$isProduction = $isProduction;

echo "Midtrans Server Key loaded from .env: " . (Config::$serverKey ? "YES (Prefix: " . substr(Config::$serverKey, 0, 14) . "...)" : "NO") . "\n";
echo "Midtrans Production Mode: " . (Config::$isProduction ? 'Production' : 'Sandbox') . "\n";
