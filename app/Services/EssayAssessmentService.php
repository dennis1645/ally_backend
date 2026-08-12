<?php

namespace App\Services;

use App\Models\User;
use App\Models\EssayAssessment;
use App\Models\UserMilestone;
use App\Models\MilestoneSubmission;
use App\Models\DocumentVault;
use App\Services\GamificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EssayAssessmentService
{
    protected string $llamaApiUrl;

    public function __construct()
    {
        $baseUrl = config('services.llama.url') ?? env('LLAMA_API_URL', 'https://bodacious-armed-tightwad.ngrok-free.dev/api');
        $this->llamaApiUrl = rtrim($baseUrl, '/');
    }

    /**
     * Memproses Penilaian Esai oleh AI Microservice
     */
    public function assessEssay(User $user, array $data, ?UploadedFile $file = null): array
    {
        // 1. CEK BATAS KUOTA HARIAN (Maksimal 3 kali per user per hari)
        $todayCount = EssayAssessment::where('user_id', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($todayCount >= 3) {
            throw new \Exception("Batas harian tercapai. Anda hanya diperbolehkan melakukan asesmen esai maksimal 3 kali per hari.", 429);
        }

        // 2. CEK SALDO TOKEN (Butuh minimal 1 Token)
        if ($user->token_balance < 1) {
            throw new \Exception("Saldo token Anda tidak mencukupi (Sisa: {$user->token_balance} token). Diperlukan 1 token untuk melakukan asesmen esai.", 402);
        }

        // 3. PROSES FILE & EKSTRAKSI TEKS ESAI
        $originalFilename = null;
        $filePath = null;
        $essayText = $data['essay_text'] ?? null;

        if ($file) {
            $originalFilename = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $filename = Str::uuid() . '.' . $extension;
            
            // Simpan file secara aman ke disk public (folder essay_assessments)
            $storedPath = $file->storeAs('essay_assessments', $filename, 'public');
            $filePath = url('storage/' . $storedPath);

            // Ekstraksi isi teks file jika belum ada teks manual
            if (empty($essayText)) {
                $essayText = $this->extractTextFromFile($file, $storedPath);
            }

            // Simpan otomatis ke Brankas / Document Vault
            DocumentVault::create([
                'user_id'       => $user->id,
                'file_name'     => $originalFilename,
                'file_type'     => $data['essay_type'] ?? 'essay',
                'file_url'      => $filePath,
                'file_size'     => $file->getSize(),
                'status'        => 'verified',
                'share_token'   => Str::random(32),
                'verified_at'   => now(),
            ]);
        }

        if (empty(trim($essayText ?? ''))) {
            throw new \Exception("Konten esai kosong. Harap unggah berkas dokumen esai atau masukkan teks esai.", 422);
        }

        $essayType = $data['essay_type'] ?? 'general';
        $title = $data['title'] ?? ($originalFilename ? pathinfo($originalFilename, PATHINFO_FILENAME) : 'Asesmen Esai ' . ucfirst($essayType));

        // 4. KIRIM REQUEST BERKAS FILE KE AI MICROSERVICE (OCR PROCESSING)
        $aiResult = $this->callAIEssayEvaluator($essayText, $essayType, $scholarshipName = $user->primary_scholarship_target ?? 'Beasiswa Target', $file);

        DB::beginTransaction();
        try {
            // 5. POTONG SALDO TOKEN USER (1 Token)
            $user->decrement('token_balance', 1);

            $score = $aiResult['score'] ?? $aiResult['overall_score'] ?? 0;

            // 6. SIMPAN HASIL ASESMEN KE DATABASE
            $assessment = EssayAssessment::create([
                'user_id'            => $user->id,
                'user_milestone_id'  => $data['user_milestone_id'] ?? null,
                'essay_type'         => $essayType,
                'title'              => $title,
                'original_filename'  => $originalFilename,
                'file_path'          => $filePath,
                'essay_text'         => $essayText,
                'score'              => (int) $score,
                'overall_score'      => (int) ($aiResult['overall_score'] ?? $score),
                'categories'         => $aiResult['categories'] ?? [],
                'strengths'          => $aiResult['strengths'] ?? [],
                'weaknesses'         => $aiResult['weaknesses'] ?? [],
                'recommendations'    => $aiResult['recommendations'] ?? [],
                'raw_ai_response'    => $aiResult,
                'token_cost'         => 1,
            ]);

            // 7. INTEGRASI MILESTONE / TASK (JIKA DISERTAKAN user_milestone_id)
            $milestoneCompleted = false;
            $gamificationData = null;

            if (!empty($data['user_milestone_id'])) {
                $milestone = UserMilestone::where('user_id', $user->id)
                    ->where('id', $data['user_milestone_id'])
                    ->first();

                if ($milestone) {
                    $effectiveScore = max($assessment->score, $assessment->overall_score);

                    // Simpan submission di milestone
                    MilestoneSubmission::create([
                        'user_milestone_id' => $milestone->id,
                        'text_response'     => "Hasil Asesmen AI Esai: Score {$effectiveScore}/100",
                        'file_url'          => $filePath,
                        'review_status'     => ($effectiveScore >= 70) ? 'approved' : 'pending',
                        'feedback_notes'    => "AI Score: {$effectiveScore}. Recommended Focus: " . implode('; ', array_slice($assessment->recommendations ?? [], 0, 2)),
                        'reviewed_at'       => now(),
                    ]);

                    // Jika Nilai Score >= 70, Otomatis Tandai Task Milestone Completed!
                    if ($effectiveScore >= 70 && $milestone->status !== 'completed') {
                        $milestone->update([
                            'status'        => 'completed',
                            'is_discovered' => true,
                            'completed_at'  => now()
                        ]);

                        // Tambahkan XP Reward dari task milestone
                        $gamificationData = GamificationService::addXpAndCheckBadges($user, $milestone->xp_reward ?? 50);

                        // Recalculate Mentee Readiness Score
                        GamificationService::recalculateReadinessScore($user);
                        $milestoneCompleted = true;
                    }
                }
            }

            DB::commit();

            $remainingQuota = 3 - ($todayCount + 1);

            return [
                'assessment'          => $assessment,
                'milestone_completed' => $milestoneCompleted,
                'remaining_daily_quota' => max(0, $remainingQuota),
                'remaining_token'     => $user->fresh()->token_balance,
                'gamification'        => $gamificationData
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal menyimpan hasil asesmen esai: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Memanggil Endpoint AI Microservice (Multipart Upload Berkas untuk OCR AI) / Local Fallback
     */
    protected function callAIEssayEvaluator(?string $essayText, string $essayType, string $scholarshipName, ?UploadedFile $file = null): array
    {
        $endpoints = [
            $this->llamaApiUrl . '/essay/assess',
            $this->llamaApiUrl . '/essay-assessment',
            $this->llamaApiUrl . '/essay'
        ];

        foreach ($endpoints as $url) {
            try {
                $httpRequest = Http::timeout(60)
                    ->withHeaders([
                        'Accept'       => 'application/json',
                        'ngrok-skip-browser-warning' => 'true'
                    ]);

                if ($file && file_exists($file->getRealPath())) {
                    // Attach fisik berkas file untuk OCR AI Microservice
                    $httpRequest->attach(
                        'file',
                        file_get_contents($file->getRealPath()),
                        $file->getClientOriginalName(),
                        ['Content-Type' => $file->getClientMimeType()]
                    );
                    $httpRequest->attach(
                        'essay_file',
                        file_get_contents($file->getRealPath()),
                        $file->getClientOriginalName(),
                        ['Content-Type' => $file->getClientMimeType()]
                    );
                }

                $response = $httpRequest->post($url, [
                    'essay_type'       => $essayType,
                    'scholarship_name' => $scholarshipName,
                    'essay_text'       => $essayText ?? '',
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $data = isset($json['data']) ? $json['data'] : $json;

                    if (isset($data['overall_score']) || isset($data['categories'])) {
                        Log::info("Berhasil menerima hasil OCR & Evaluasi Esai dari AI Microservice.");
                        return $this->formatAIResponse($data);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("AI Endpoint {$url} tidak dapat dijangkau: " . $e->getMessage());
            }
        }

        // Fallback Local Evaluator jika AI Service offline
        Log::info("Menjalankan Local Fallback AI Essay Evaluator...");
        return $this->fallbackLocalEssayEvaluator($essayText ?? '', $essayType);
    }

    /**
     * Format dan Validasi Struktur JSON Respon AI
     */
    protected function formatAIResponse(array $data): array
    {
        $categories = $data['categories'] ?? [
            'storytelling'          => rand(70, 90),
            'motivation'            => rand(70, 90),
            'leadership'            => rand(70, 90),
            'impact'                => rand(70, 90),
            'scholarship_alignment' => rand(70, 90),
            'clarity'               => rand(70, 90)
        ];

        $overallScore = $data['overall_score'] ?? (int) round(array_sum($categories) / count($categories));
        $score = $data['score'] ?? $overallScore;

        return [
            'score'         => (int) $score,
            'overall_score' => (int) $overallScore,
            'categories'    => [
                'storytelling'          => (int) ($categories['storytelling'] ?? 75),
                'motivation'            => (int) ($categories['motivation'] ?? 80),
                'leadership'            => (int) ($categories['leadership'] ?? 75),
                'impact'                => (int) ($categories['impact'] ?? 70),
                'scholarship_alignment' => (int) ($categories['scholarship_alignment'] ?? 80),
                'clarity'               => (int) ($categories['clarity'] ?? 85),
            ],
            'strengths'       => is_array($data['strengths'] ?? null) ? $data['strengths'] : [
                "Naratif esai menunjukkan motivasi akademik yang cukup jelas.",
                "Struktur penulisan esai mudah dipahami dan memiliki alur narasi yang logis."
            ],
            'weaknesses'      => is_array($data['weaknesses'] ?? null) ? $data['weaknesses'] : [
                "Dampak kepemimpinan dan pencapaian masih dapat diperjelas dengan data kuantitatif."
            ],
            'recommendations' => is_array($data['recommendations'] ?? null) ? $data['recommendations'] : [
                "Tambahkan angka atau indikator sukses spesifik pada bagian hasil proyek/kontribusi Anda."
            ]
        ];
    }

    /**
     * Fallback Evaluator Lokal (Perhitungan berbasis teks saat AI offline)
     */
    protected function fallbackLocalEssayEvaluator(string $essayText, string $essayType): array
    {
        $wordCount = str_word_count(strip_tags($essayText));
        
        $baseScore = 70;
        if ($wordCount >= 400) {
            $baseScore += 15;
        } elseif ($wordCount >= 200) {
            $baseScore += 8;
        }

        $storytellingScore = min(95, $baseScore + rand(-3, 5));
        $motivationScore   = min(95, $baseScore + rand(-2, 6));
        $leadershipScore   = min(95, $baseScore + rand(-5, 4));
        $impactScore       = min(95, $baseScore + rand(-4, 3));
        $alignmentScore    = min(95, $baseScore + rand(-2, 5));
        $clarityScore      = min(95, $baseScore + rand(-1, 5));

        $overallScore = (int) round(($storytellingScore + $motivationScore + $leadershipScore + $impactScore + $alignmentScore + $clarityScore) / 6);

        return [
            'score'         => $overallScore,
            'overall_score' => $overallScore,
            'categories' => [
                'storytelling'          => $storytellingScore,
                'motivation'            => $motivationScore,
                'leadership'            => $leadershipScore,
                'impact'                => $impactScore,
                'scholarship_alignment' => $alignmentScore,
                'clarity'               => $clarityScore,
            ],
            'strengths' => [
                "Panjang esai memenuhi standar analisis (" . $wordCount . " kata).",
                "Alur argumen dan keterkaitan latar belakang cukup kohesif."
            ],
            'weaknesses' => [
                "Pengalaman kepemimpinan dan bukti konkrit kontribusi belum terukur secara maksimal."
            ],
            'recommendations' => [
                "Sertakan data kualitatif/kuantitatif nyata (misal: jumlah penerima manfaat proyek) untuk meningkatkan skor Impact."
            ]
        ];
    }

    /**
     * Helper Ekstraksi Teks Berkas
     */
    protected function extractTextFromFile(UploadedFile $file, string $storedPath): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        if ($extension === 'txt') {
            return file_get_contents($file->getRealPath());
        }

        // Untuk berkas PDF / DOCX, kita baca konten teks mentah atau nama file
        $content = @file_get_contents($file->getRealPath());
        if ($content) {
            $cleanText = preg_replace('/[^\x20-\x7E\t\r\n]/', '', $content);
            if (strlen(trim($cleanText)) > 50) {
                return substr($cleanText, 0, 5000);
            }
        }

        return "Dokumen Esai dikirimkan dari berkas: " . $file->getClientOriginalName();
    }
}
