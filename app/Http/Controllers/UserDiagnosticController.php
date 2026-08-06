<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticOption;
use App\Models\DiagnosticAssessment;
use App\Services\GamificationService;
use App\Services\AIDiagnosticService; // Import AI Service
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserDiagnosticController extends Controller
{
    protected $aiDiagnosticService;

    // Inject AI Service melalui Constructor
    public function __construct(AIDiagnosticService $aiDiagnosticService)
    {
        $this->aiDiagnosticService = $aiDiagnosticService;
    }

    /**
     * 1. Menampilkan soal kepada User berdasarkan assessment_type dengan sistem Pagination (5 soal per halaman)
     */
    public function getQuestions(Request $request)
    {
        // Tentukan tipe asesmen melalui query parameter (default: initial_diagnostic)
        $assessmentType = $request->query('assessment_type', 'initial_diagnostic');

        $questions = DiagnosticQuestion::where('is_active', true)
            ->where('assessment_type', $assessmentType)
            ->with(['options' => function($query) {
                // Sembunyikan score_weight, weakness_tag, dan strength_tag dari frontend
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
     * 2. Submit jawaban asesmen (Mendukung Guest / Onboarding & Authenticated / Initial Diagnostic)
     */
    public function submitAssessment(Request $request)
    {
        // PENTING: Panggil guard Sanctum secara eksplisit untuk membaca token di rute publik
        $user = Auth::guard('sanctum')->user(); 

        // Aturan validasi dinamis berdasarkan status login dan jenis asesmen
        $rules = [
            'assessment_type' => 'required|string|in:onboarding,initial_diagnostic',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:diagnostic_questions,id',
            'answers.*.option_id' => 'required|exists:diagnostic_options,id',
        ];

        // Jika user belum login (guest), wajib menyertakan guest_token
        if (!$user) {
            $rules['guest_token'] = 'required|string';
        } else {
            // Jika user sudah login, data akademik bersifat opsional
            $rules['gpa'] = 'nullable|numeric|between:0.00,4.00';
            $rules['undergraduate_major'] = 'nullable|string|max:255';
            $rules['target_major'] = 'nullable|string|max:255';
            $rules['primary_scholarship_target'] = 'nullable|string|max:255';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

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

            if (!$option || $option->diagnostic_question_id !== $question->id) {
                continue;
            }

            $overallScore += $option->score_weight;

            $category = strtolower($question->category);
            if (array_key_exists($category, $scores)) {
                $scores[$category] += $option->score_weight;
            }

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

        // -------------------------------------------------------------
        // BLOK EKSEKUSI AI DIAGNOSTIC SERVICE
        // -------------------------------------------------------------
        
        // Siapkan data user untuk AI (jika ada)
        $userDataForAI = [
            'gpa' => $request->gpa ?? null,
            'undergraduate_major' => $request->undergraduate_major ?? null,
            'target_major' => $request->target_major ?? null,
            'scholarship_target' => $request->primary_scholarship_target ?? null,
        ];

        // Gabungkan skor keseluruhan dengan skor per kategori
        $scoresForAI = array_merge(['overall' => $overallScore], $scores);
        $tagsForAI = ['weaknesses' => $weaknesses, 'strengths' => $strengths];

        // Panggil service AI
        $aiResult = $this->aiDiagnosticService->generateAnalysis(
            $scoresForAI, 
            $userDataForAI, 
            $tagsForAI, 
            $request->assessment_type
        );

        // Fallback jika API AI gagal, timeout, atau limit habis
        if (!$aiResult) {
            $aiResult = [
                'weaknesses_mapping' => $weaknesses,
                'strengths_mapping' => $strengths,
                'system_recommendation' => "Terjadi kendala saat menganalisis profilmu menggunakan AI. Namun berdasarkan skor dasar $overallScore, kamu memiliki potensi yang sangat baik. Mari mulai kembangkan langkahmu bersama mentor kami."
            ];
        }

        DB::beginTransaction();
        try {
            // Siapkan payload data untuk tabel diagnostic_assessments
            $assessmentData = [
                'assessment_type' => $request->assessment_type,
                'overall_score' => $overallScore,
                'academic_score' => $scores['academic'],
                'goals_score' => $scores['goals'],
                'leadership_experience_score' => $scores['leadership_experience'],
                'language_score' => $scores['language'],
                'application_readiness_score' => $scores['application_readiness'],
                // Encode hasil array dari AI ke format JSON untuk disimpan ke database
                'weaknesses_mapping' => json_encode($aiResult['weaknesses_mapping']),
                'strengths_mapping' => json_encode($aiResult['strengths_mapping']),
                'system_recommendation' => $aiResult['system_recommendation']
            ];

            // Simpan berdasarkan kepemilikan user atau guest_token
            if ($user) {
                $assessmentData['user_id'] = $user->id;
                $assessmentData['guest_token'] = null;

                $assessment = DiagnosticAssessment::updateOrCreate(
                    [
                        'user_id' => $user->id, 
                        'assessment_type' => $request->assessment_type
                    ],
                    $assessmentData
                );
            } else {
                $assessmentData['user_id'] = null;
                $assessmentData['guest_token'] = $request->guest_token;

                $assessment = DiagnosticAssessment::updateOrCreate(
                    [
                        'guest_token' => $request->guest_token, 
                        'assessment_type' => $request->assessment_type
                    ],
                    $assessmentData
                );
            }

            $gamificationData = null;

            // Update profil dan jalankan gamifikasi HANYA jika user sudah login
            if ($user) {
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

                $user->update($userUpdateData);

                // Berikan reward XP gamifikasi untuk penyelesaian asesmen
                $xpReward = 50; 
                $gamificationData = GamificationService::addXpAndCheckBadges($user, $xpReward);
            }

            DB::commit();

            // Decode kembali JSON agar output di response API berbentuk array (lebih mudah di-parsing Frontend)
            $assessment->weaknesses_mapping = json_decode($assessment->weaknesses_mapping);
            $assessment->strengths_mapping = json_decode($assessment->strengths_mapping);

            return response()->json([
                'status' => 'success',
                'message' => 'Assessment submitted and analyzed by AI successfully.',
                'data' => [
                    'assessment' => $assessment,
                    'user_profile' => $user ? $user->fresh()->makeHidden(['password']) : null,
                    'gamification' => $gamificationData 
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to process assessment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 3. Menampilkan hasil asesmen milik User (Mendukung User Login atau Guest via guest_token)
     */
    public function getMyAssessment(Request $request)
    {
        // PENTING: Gunakan guard Sanctum untuk cek sesi secara mandiri
        $user = Auth::guard('sanctum')->user();
        $assessmentType = $request->query('assessment_type', 'initial_diagnostic');
        $guestToken = $request->query('guest_token'); // Tangkap langsung dari query

        $assessment = null;

        // Validasi awal: Jika tidak ada token login dan tidak ada guest_token, tolak akses.
        if (!$user && !$guestToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Please login or provide a valid guest_token.'
            ], 401);
        }

        // PRIORITAS 1: Jika request secara eksplisit membawa `guest_token`, cari menggunakan token itu
        if ($guestToken) {
            $assessment = DiagnosticAssessment::where('guest_token', $guestToken)
                ->where('assessment_type', $assessmentType)
                ->first();
        }

        // PRIORITAS 2: Jika tidak ketemu (atau tidak ada guest_token), tapi user sedang login, cari berdasar ID
        if (!$assessment && $user) {
            $assessment = DiagnosticAssessment::where('user_id', $user->id)
                ->where('assessment_type', $assessmentType)
                ->first();
        }

        // Jika dicari pakai dua cara di atas tapi tetap tidak ada di database
        if (!$assessment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Assessment result not found.'
            ], 404);
        }

        // Decode JSON mapping saat diambil 
        $assessment->weaknesses_mapping = is_string($assessment->weaknesses_mapping) 
            ? json_decode($assessment->weaknesses_mapping) 
            : $assessment->weaknesses_mapping;
            
        $assessment->strengths_mapping = is_string($assessment->strengths_mapping) 
            ? json_decode($assessment->strengths_mapping) 
            : $assessment->strengths_mapping;

        return response()->json([
            'status' => 'success',
            'data' => $assessment
        ]);
    }
}