<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PracticeExam;
use App\Models\PracticeQuestion;
use App\Models\PracticeOption;

echo "=== SEEDING SAMPLE PRACTICE QUESTIONS & DRILLS ===\n";

$exam = PracticeExam::create([
    'title' => 'TOEFL iBT Reading & Grammar Master',
    'type' => 'toefl',
    'duration_minutes' => 15,
    'passing_score' => 60,
    'description' => 'Soal latihan standar TOEFL untuk menguji pemahaman tata bahasa dan membaca.',
    'is_active' => true,
]);

$questionsData = [
    [
        'question' => 'Select the word that best completes the sentence: "The committee ____ to approve the scholarship budget tomorrow."',
        'section' => 'reading',
        'explanation' => 'Kata kerja "is expected" tepat digunakan untuk kalimat pasif singular.',
        'options' => [
            ['text' => 'is expected', 'is_correct' => true],
            ['text' => 'are expecting', 'is_correct' => false],
            ['text' => 'expecting', 'is_correct' => false],
            ['text' => 'have expected', 'is_correct' => false],
        ]
    ],
    [
        'question' => 'Identify the synonym of the underlined word: "The candidate presented a **compelling** argument during the LPDP interview."',
        'section' => 'reading',
        'explanation' => '"Compelling" berarti sangat meyakinkan / persuasive.',
        'options' => [
            ['text' => 'Weak', 'is_correct' => false],
            ['text' => 'Persuasive', 'is_correct' => true],
            ['text' => 'Confusing', 'is_correct' => false],
            ['text' => 'Doubtful', 'is_correct' => false],
        ]
    ],
    [
        'question' => 'Choose the correct form: "Neither the mentor nor the mentees ____ aware of the schedule change yesterday."',
        'section' => 'reading',
        'explanation' => 'Pada konstruksi "Neither... nor...", kata kerja mengikuti subjek terdekat (mentees -> plural -> were).',
        'options' => [
            ['text' => 'was', 'is_correct' => false],
            ['text' => 'were', 'is_correct' => true],
            ['text' => 'is', 'is_correct' => false],
            ['text' => 'are', 'is_correct' => false],
        ]
    ],
    [
        'question' => 'Fill in the blank: "If I ____ more time, I would have submitted a stronger motivation letter."',
        'section' => 'reading',
        'explanation' => 'Conditional sentence Type 3 menggunakan "had had" untuk penyesalan di masa lalu.',
        'options' => [
            ['text' => 'have', 'is_correct' => false],
            ['text' => 'had had', 'is_correct' => true],
            ['text' => 'will have', 'is_correct' => false],
            ['text' => 'have had', 'is_correct' => false],
        ]
    ],
    [
        'question' => 'Select the most appropriate connector: "The applicant had a lower GPA; ____, her leadership experience won her the award."',
        'section' => 'reading',
        'explanation' => '"However" menunjukkan kontras/pertentangan yang tepat.',
        'options' => [
            ['text' => 'However', 'is_correct' => true],
            ['text' => 'Therefore', 'is_correct' => false],
            ['text' => 'Furthermore', 'is_correct' => false],
            ['text' => 'Because', 'is_correct' => false],
        ]
    ]
];

foreach ($questionsData as $qData) {
    $q = PracticeQuestion::create([
        'practice_exam_id' => $exam->id,
        'question_text' => $qData['question'],
        'question_type' => 'multiple_choice',
        'section' => $qData['section'],
        'explanation' => $qData['explanation'],
        'score_weight' => 20,
    ]);

    foreach ($qData['options'] as $opt) {
        PracticeOption::create([
            'practice_question_id' => $q->id,
            'option_text' => $opt['text'],
            'is_correct' => $opt['is_correct'],
        ]);
    }
}

echo "✅ 5 Sample Practice Questions berhasil dibuat di database!\n";
