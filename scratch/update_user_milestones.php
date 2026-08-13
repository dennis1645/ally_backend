<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\UserMilestone;
use Illuminate\Support\Facades\DB;

echo "=== Updating user_milestones user_id from 9 to 4 ===\n";

$affectedRows = DB::table('user_milestones')
    ->where('user_id', 9)
    ->update(['user_id' => 4]);

echo "✅ Berhasil memperbarui {$affectedRows} data milestone dari user_id = 9 menjadi user_id = 4!\n";

$totalMilestonesUser4 = DB::table('user_milestones')->where('user_id', 4)->count();
echo "📊 Total milestone milik Mentee Jokowi (user_id = 4) sekarang: {$totalMilestonesUser4} milestone.\n";
