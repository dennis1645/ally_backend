<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticOption;
use App\Models\DiagnosticAssessment;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserDiagnosticController extends Controller
{
    /**
     * 1. Menampilkan soal kepada User dengan sistem Pagination (5 soal per halaman)
     */
    public function getQuestions()
    {
        // Ambil soal yang aktif, diurutkan, dan dipaginasi 5 per page
        $questions = DiagnosticQuestion::where('is_active', true)
            ->with(['options' => function($query) {
                // Hanya pilih kolom yang perlu dilihat user di frontend
                // Sembunyikan score_weight, weakness_tag, dan strength_tag!
                $query->select('id', 'diagnostic_question_id', 'option_text');
            }])
            ->orderBy('order_number', 'asc')
            ->paginate(5);

        return response()->json([
            'status' => 'success',
            'data' => $questions
        ]);
    }

    /**
     * 2. Submit jawaban asesmen & Opsional Update Profil Akademik (Task 1.4 & Task 1.5)
     */
    public function submitAssessment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:diagnostic_questions,id',
            'answers.*.option_id' => 'required|exists:diagnostic_options,id',
            
            // Opsional: Data akademik & target beasiswa bisa langsung dikirim di sini
            'gpa' => 'nullable|numeric|between:0.00,4.00',
            'undergraduate_major' => 'nullable|string|max:255',
            'target_major' => 'nullable|string|max:255',
            'primary_scholarship_target' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Inisialisasi variabel perhitungan skor
        $overallScore = 0;
        $scores = [
            'academic' => 0,
            'goals' => 0,
            'leadership_experience' => 0,
            'language' => 0,
            'application_readiness' => 0
        ];
        
        $weaknesses = [];
        $strengths = [];

        // Proses setiap jawaban
        foreach ($request->answers as $answer) {
            $question = DiagnosticQuestion::find($answer['question_id']);
            $option = DiagnosticOption::find($answer['option_id']);

            // Pastikan opsi yang dipilih benar-benar milik pertanyaan tersebut
            if ($option->diagnostic_question_id !== $question->id) {
                continue;
            }

            // Tambahkan ke total skor keseluruhan
            $overallScore += $option->score_weight;

            // Tambahkan ke skor kategori spesifik
            $category = strtolower($question->category);
            if (array_key_exists($category, $scores)) {
                $scores[$category] += $option->score_weight;
            }

            // Kumpulkan tag kelemahan & kekuatan
            if (!empty($option->weakness_tag)) {
                $weaknesses[] = $option->weakness_tag;
            }
            if (!empty($option->strength_tag)) {
                $strengths[] = $option->strength_tag;
            }
        }

        // Bersihkan duplikasi tag
        $weaknesses = array_values(array_unique($weaknesses));
        $strengths = array_values(array_unique($strengths));

        // Buat sistem rekomendasi
        $recommendation = "You have an outstanding profile. You are highly ready to apply for Master's scholarships!";
        if ($overallScore < 40) {
            $recommendation = "Your foundational profile needs improvement. Focus on language skills, leadership, and defining your academic goals.";
        } elseif ($overallScore >= 40 && $overallScore < 75) {
            $recommendation = "You have a solid foundation, but some specific areas require strengthening before you submit competitive applications.";
        }

        DB::beginTransaction();
        try {
            // 1. Simpan atau update hasil asesmen
            $assessment = DiagnosticAssessment::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'overall_score' => $overallScore,
                    'academic_score' => $scores['academic'],
                    'goals_score' => $scores['goals'],
                    'leadership_experience_score' => $scores['leadership_experience'],
                    'language_score' => $scores['language'],
                    'application_readiness_score' => $scores['application_readiness'],
                    'weaknesses_mapping' => $weaknesses,
                    'strengths_mapping' => $strengths,
                    'system_recommendation' => $recommendation
                ]
            );

            // 2. Siapkan data update untuk profil user (Readiness score + Data Akademik opsional)
            $userUpdateData = [
                'readiness_score' => $overallScore
            ];

            if ($request->filled('gpa')) {
                $userUpdateData['gpa'] = $request->gpa;
            }
            if ($request->filled('undergraduate_major')) {
                $userUpdateData['undergraduate_major'] = $request->undergraduate_major;
            }
            if ($request->filled('target_major')) {
                $userUpdateData['target_major'] = $request->target_major;
            }
            if ($request->filled('primary_scholarship_target')) {
                $userUpdateData['primary_scholarship_target'] = $request->primary_scholarship_target;
            }

            // Update profil user
            $user->update($userUpdateData);

            // 3. Eksekusi sistem Gamifikasi (Menggunakan method statis yang benar: addXpAndCheckBadges)
            $xpReward = 50; // Contoh reward XP untuk penyelesaian asesmen awal
            $gamificationData = GamificationService::addXpAndCheckBadges($user, $xpReward);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Assessment and academic profile submitted successfully.',
                'data' => [
                    'assessment' => $assessment,
                    'user_profile' => $user->fresh()->makeHidden(['password']),
                    'gamification' => $gamificationData 
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to process assessment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 3. Menampilkan hasil asesmen milik User yang sedang login
     */
    public function getMyAssessment(Request $request)
    {
        $assessment = DiagnosticAssessment::where('user_id', $request->user()->id)->first();

        if (!$assessment) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have not completed the initial assessment yet.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $assessment
        ]);
    }
}