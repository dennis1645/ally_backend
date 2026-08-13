<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\UserMilestone;

echo "=== CHECKING ACTION PLANS IN USER_MILESTONES ===\n";

$mentorMilestones = UserMilestone::where('user_id', 4)
    ->where(function($q) {
        $q->where('source', 'mentor')
          ->orWhere('task_name', 'LIKE', '%Action Plan%')
          ->orWhere('task_name', 'LIKE', '%Revisi Paragraf%')
          ->orWhere('task_name', 'LIKE', '%Surat Rekomendasi%');
    })
    ->get();

echo "Jumlah Action Plan Mentor di user_milestones: " . $mentorMilestones->count() . "\n\n";

foreach ($mentorMilestones as $m) {
    echo "- ID #{$m->id} | Parent ID: " . ($m->parent_id ?? 'null') . " | Task: {$m->task_name} | Source: {$m->source}\n";
}
