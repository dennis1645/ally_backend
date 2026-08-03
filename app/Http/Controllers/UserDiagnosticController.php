<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticOption;
use App\Models\DiagnosticAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserDiagnosticController extends Controller
{
    // 1. Menampilkan soal kepada User
    public function getQuestions()
    {
        // Hanya ambil soal yang aktif dan sembunyikan bobot skor/tag dari opsi jawaban
        $questions = DiagnosticQuestion::where('is_active', true)
            ->with(['options' => function($query) {
                // Hanya pilih kolom yang perlu dilihat user, jangan kirim score_weight!
                $query->select('id', 'diagnostic_question_id', 'option_text');
            }])
            ->orderBy('order_number', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $questions
        ]);
    }

    // 2. Submit jawaban dari User dan kalkulasi skor
    public function submitAssessment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:diagnostic_questions,id',
            'answers.*.option_id' => 'required|exists:diagnostic_options,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        // Inisialisasi variabel perhitungan
        $overallScore = 0;
        $scores = [
            'academic' => 0,
            'language' => 0,
            'experience' => 0,
            'document' => 0
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

            // Tambahkan ke total skor
            $overallScore += $option->score_weight;

            // Tambahkan ke skor kategori spesifik (jika kategorinya sesuai)
            $category = strtolower($question->category);
            if (array_key_exists($category, $scores)) {
                $scores[$category] += $option->score_weight;
            }

            // Kumpulkan tag kelemahan & kekuatan (abaikan jika null)
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

        // Buat sistem rekomendasi sederhana berdasarkan total skor (Bisa disesuaikan nanti)
        $recommendation = "Persiapanmu sudah sangat matang, pertahankan!";
        if ($overallScore < 40) {
            $recommendation = "Kamu perlu fokus meningkatkan profil dasar kamu seperti bahasa dan pengalaman organisasi.";
        } elseif ($overallScore >= 40 && $overallScore < 70) {
            $recommendation = "Profilmu cukup baik, namun masih ada ruang untuk ditingkatkan sebelum mendaftar beasiswa targetmu.";
        }

        DB::beginTransaction();
        try {
            // Simpan atau update hasil asesmen (satu user = satu hasil asesmen aktif)
            $assessment = DiagnosticAssessment::updateOrCreate(
                ['user_id' => $user->id], // Cari berdasarkan user_id
                [
                    'overall_score' => $overallScore,
                    'academic_score' => $scores['academic'],
                    'language_score' => $scores['language'],
                    'experience_score' => $scores['experience'],
                    'document_score' => $scores['document'],
                    'weaknesses_mapping' => $weaknesses,
                    'strengths_mapping' => $strengths,
                    'system_recommendation' => $recommendation
                ]
            );

            // Update readiness_score di tabel users
            $user->update(['readiness_score' => $overallScore]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Assessment submitted successfully.',
                'data' => $assessment
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to process assessment: ' . $e->getMessage()], 500);
        }
    }

    // 3. Menampilkan hasil asesmen milik User yang sedang login
    public function getMyAssessment()
    {
        $assessment = DiagnosticAssessment::where('user_id', Auth::id())->first();

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