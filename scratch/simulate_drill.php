<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\PracticeQuestion;
use App\Models\DailyDrill;
use App\Models\DailyDrillAnswer;
use App\Services\GamificationService;
use Carbon\Carbon;

echo "=== SIMULATING DAILY DRILL SUBMISSION ===\n";

$user = User::find(4); // Mentee Jokowi
$today = Carbon::now()->toDateString();

// Clear existing drill for today for testing
DailyDrill::where('user_id', $user->id)->where('drill_date', $today)->delete();

$questions = PracticeQuestion::with('options')->limit(5)->get();

if ($questions->isEmpty()) {
    echo "❌ Belum ada bank soal PracticeQuestion di database! Jalankan seeder soal dulu.\n";
    exit(0);
}

echo "Generating 5 Questions...\n";

$answersData = [];
foreach ($questions as $q) {
    $correctOpt = $q->options->where('is_correct', true)->first();
    $answersData[] = [
        'question_id' => $q->id,
        'selected_option_id' => $correctOpt ? $correctOpt->id : $q->options->first()?->id,
    ];
}

// Simulasikan pengerjaan drill
$totalQuestions = count($answersData);
$correctAnswersCount = 0;
$totalScore = 0;

$dailyDrill = DailyDrill::create([
    'user_id' => $user->id,
    'drill_date' => $today,
    'total_questions' => $totalQuestions,
    'difficulty_feedback' => 'good',
    'feedback_note' => 'Soal latihan sangat bagus!',
]);

foreach ($answersData as $ans) {
    $q = PracticeQuestion::find($ans['question_id']);
    $opt = $q->options->where('id', $ans['selected_option_id'])->first();
    $isCorrect = $opt && $opt->is_correct;

    if ($isCorrect) {
        $correctAnswersCount++;
        $totalScore += $q->score_weight;
    }

    DailyDrillAnswer::create([
        'daily_drill_id' => $dailyDrill->id,
        'practice_question_id' => $q->id,
        'selected_option_id' => $ans['selected_option_id'],
        'is_correct' => $isCorrect,
    ]);
}

$xpEarned = ($correctAnswersCount * 10) + 20;

$dailyDrill->update([
    'correct_answers' => $correctAnswersCount,
    'total_score' => $totalScore,
    'xp_earned' => $xpEarned,
]);

$gamificationResult = GamificationService::addXpAndCheckBadges($user, $xpEarned);

echo "🎉 SIMULASI SELESAI!\n";
echo "Drill ID: {$dailyDrill->id}\n";
echo "User: {$user->name}\n";
echo "Jawaban Benar: {$correctAnswersCount} / {$totalQuestions}\n";
echo "Total Skor: {$totalScore}\n";
echo "XP Didapatkan: +{$xpEarned} XP\n";
