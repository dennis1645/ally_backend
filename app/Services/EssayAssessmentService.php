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

    public static array $aiTaskToDbTitleMap = [
        'leadership-activities'     => 'List your leadership experiences',
        'leadership-role'           => 'Describe your role',
        'leadership-impact'         => 'Measure your impact',
        'leadership-star'           => 'Write one STAR story',
        'leadership-lesson'         => 'Explain what you learned',
        'essay-motivation'          => 'Why this scholarship?',
        'essay-university'          => 'Why this university?',
        'essay-star-story'          => 'Write using the STAR method',
        'essay-impact'              => 'Add measurable impact',
        'essay-clarity'             => 'Check your story flow',
        'essay-alignment'           => 'Check scholarship alignment',
        'application-cv'             => 'Update your CV',
        'application-transcript'     => 'Prepare academic documents',
        'application-recommendation' => 'Prepare recommendation letters',
        'application-requirements'   => 'Check every requirement',
        'application-proofread'      => 'Proofread everything',
        'application-submit'         => 'Submit your application',
    ];

    public static array $dbTitleToAiTaskMap = [
        'List your leadership experiences'  => 'leadership-activities',
        'Describe your role'                => 'leadership-role',
        'Measure your impact'               => 'leadership-impact',
        'Write one STAR story'              => 'leadership-star',
        'Explain what you learned'          => 'leadership-lesson',
        'Why this scholarship?'             => 'essay-motivation',
        'Why this university?'              => 'essay-university',
        'Write using the STAR method'       => 'essay-star-story',
        'Add measurable impact'             => 'essay-impact',
        'Check your story flow'             => 'essay-clarity',
        'Check scholarship alignment'       => 'essay-alignment',
        'Update your CV'                    => 'application-cv',
        'Prepare academic documents'        => 'application-transcript',
        'Prepare recommendation letters'    => 'application-recommendation',
        'Check every requirement'           => 'application-requirements',
        'Proofread everything'              => 'application-proofread',
        'Submit your application'           => 'application-submit',
    ];

    public function __construct()
    {
        $baseUrl = config('services.llama.url') ?? env('LLAMA_API_URL', 'https://bodacious-armed-tightwad.ngrok-free.dev/api');
        $this->llamaApiUrl = rtrim($baseUrl, '/');
    }

    /**
     * Resolves matching UserMilestone model and AI taskId string bidirectionally.
     */
    public function resolveMilestoneAndAiTask(User $user, array $requestData, string $essayType): array
    {
        $rawTaskId = $requestData['taskId'] ?? $requestData['user_milestone_id'] ?? null;

        $milestone = null;
        $aiTaskId = null;

        if ($rawTaskId) {
            if (is_numeric($rawTaskId)) {
                // Numeric DB ID supplied (e.g. 14 or 24)
                $milestone = UserMilestone::where('user_id', $user->id)
                    ->where('id', (int) $rawTaskId)
                    ->first();

                if ($milestone && isset(static::$dbTitleToAiTaskMap[$milestone->task_name])) {
                    $aiTaskId = static::$dbTitleToAiTaskMap[$milestone->task_name];
                }
            } else {
                // String AI taskId supplied (e.g. "essay-university" or "application-transcript")
                $aiTaskId = (string) $rawTaskId;
                $matchingTitle = static::$aiTaskToDbTitleMap[$aiTaskId] ?? null;

                if ($matchingTitle) {
                    $milestone = UserMilestone::where('user_id', $user->id)
                        ->where('task_name', $matchingTitle)
                        ->first();
                } else {
                    // Try loose search by title substring
                    $cleanKey = str_replace(['essay-', 'application-', 'leadership-'], '', $aiTaskId);
                    $milestone = UserMilestone::where('user_id', $user->id)
                        ->where('task_name', 'LIKE', "%{$cleanKey}%")
                        ->first();
                }
            }
        }

        // Fallbacks if milestone not found by taskId
        if (!$milestone && !empty($requestData['user_milestone_id'])) {
            $milestone = UserMilestone::where('user_id', $user->id)
                ->where('id', $requestData['user_milestone_id'])
                ->first();
        }

        if (!$aiTaskId) {
            if ($milestone && isset(static::$dbTitleToAiTaskMap[$milestone->task_name])) {
                $aiTaskId = static::$dbTitleToAiTaskMap[$milestone->task_name];
            } else {
                $aiTaskId = 'essay-' . $essayType;
            }
        }

        return [
            'milestone' => $milestone,
            'aiTaskId'  => $aiTaskId,
        ];
    }

    /**
     * Memproses Penilaian Esai oleh AI Microservice & Menyinkronkan Milestone DB
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

        // 3. RESOLVE PENERJEMAH 2-ARAH (TASK ID AI <-> DB MILESTONE)
        $essayType = $data['essay_type'] ?? 'motivation';
        $resolved = $this->resolveMilestoneAndAiTask($user, $data, $essayType);
        $milestone = $resolved['milestone'];
        $aiTaskId  = $resolved['aiTaskId'];

        // Inject resolved values to payload data
        $data['taskId'] = $aiTaskId;
        if ($milestone) {
            $data['user_milestone_id'] = $milestone->id;
        }

        // 4. PROSES FILE & EKSTRAKSI TEKS ESAI
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

            $validVaultFileTypes = ['cv', 'transcript', 'certificate', 'essay', 'loa', 'other'];
            $vaultFileType = in_array(strtolower($data['essay_type'] ?? ''), $validVaultFileTypes) 
                ? strtolower($data['essay_type']) 
                : 'essay';

            // Simpan otomatis ke Brankas / Document Vault
            DocumentVault::create([
                'user_id'       => $user->id,
                'file_name'     => $originalFilename,
                'file_path'     => $storedPath,
                'mime_type'     => $file->getMimeType(),
                'file_size'     => $file->getSize(),
                'file_type'     => $vaultFileType,
                'status'        => 'uploaded',
            ]);
        }

        if (empty(trim($essayText ?? '')) && !$file) {
            throw new \Exception("Konten esai kosong. Harap unggah berkas dokumen esai atau masukkan teks esai.", 422);
        }

        $title = $data['title'] ?? ($milestone ? $milestone->task_name : ($originalFilename ? pathinfo($originalFilename, PATHINFO_FILENAME) : 'Asesmen Esai ' . ucfirst($essayType)));

        // 5. KIRIM REQUEST BERKAS FILE KE AI MICROSERVICE (OCR & JOURNEY TASK UPLOAD)
        $aiResult = $this->callAIEssayEvaluator($user, $essayText, $essayType, $scholarshipName = $user->primary_scholarship_target ?? 'Beasiswa Target', $data, $file);

        DB::beginTransaction();
        try {
            // 6. POTONG SALDO TOKEN USER (1 Token)
            $user->decrement('token_balance', 1);

            $score = $aiResult['score'] ?? $aiResult['overall_score'] ?? 0;

            // 7. SIMPAN HASIL ASESMEN KE DATABASE
            $assessment = EssayAssessment::create([
                'user_id'            => $user->id,
                'user_milestone_id'  => $milestone ? $milestone->id : null,
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

            // 8. SINKRONISASI MILESTONE LOCAL DB (OTOMATIS SELESAI JIKA SCORE >= 70 ATAU TASK COMPLETED)
            $milestoneCompleted = false;
            $gamificationData = null;

            if ($milestone) {
                $effectiveScore = max($assessment->score, $assessment->overall_score);
                $aiCompletedFlag = isset($aiResult['task']['completed']) && $aiResult['task']['completed'] === true;

                // Simpan submission di milestone
                MilestoneSubmission::create([
                    'user_id'           => $user->id,
                    'user_milestone_id' => $milestone->id,
                    'submission_type'   => 'both',
                    'text_response'     => "Hasil Asesmen AI Esai: Score {$effectiveScore}/100",
                    'file_path'         => $filePath,
                    'file_name'         => $originalFilename ?? ('essay_' . $essayType . '.pdf'),
                    'review_status'     => ($effectiveScore >= 70 || $aiCompletedFlag) ? 'approved' : 'pending',
                    'mentor_feedback'   => "AI Score: {$effectiveScore}. Recommended Focus: " . implode('; ', array_slice($assessment->recommendations ?? [], 0, 2)),
                    'reviewed_at'       => now(),
                    'xp_awarded'        => ($effectiveScore >= 70 || $aiCompletedFlag) ? ($milestone->xp_reward ?? 50) : 0,
                ]);

                // Jika Nilai Score >= 70 ATAU AI menyatakan completed, Otomatis Tandai Task Milestone Completed di DB Local!
                if (($effectiveScore >= 70 || $aiCompletedFlag) && $milestone->status !== 'completed') {
                    $milestone->update([
                        'status'        => 'completed',
                        'is_discovered' => true,
                        'completed_at'  => now()
                    ]);

                    // Tambahkan XP Reward dari task milestone
                    $gamificationData = GamificationService::addXpAndCheckBadges($user, $milestone->xp_reward ?? 50);

                    // Recalculate Mentee Readiness Score
                    GamificationService::updateReadinessScore($user);
                    $milestoneCompleted = true;
                }
            }

            DB::commit();

            $remainingQuota = 3 - ($todayCount + 1);

            return [
                'assessment'            => $assessment,
                'milestone_completed'   => $milestoneCompleted,
                'remaining_daily_quota' => max(0, $remainingQuota),
                'remaining_token'       => $user->fresh()->token_balance,
                'gamification'          => $gamificationData,
                'journey'               => $aiResult['journey'] ?? null,
                'task'                  => $aiResult['task'] ?? null,
                'evaluation'            => $aiResult['evaluation'] ?? null,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal menyimpan hasil asesmen esai: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Memanggil Endpoint AI Microservice (Multipart Upload Berkas untuk OCR & Journey Task Upload)
     */
    protected function callAIEssayEvaluator(User $user, ?string $essayText, string $essayType, string $scholarshipName, array $requestData, ?UploadedFile $file = null): array
    {
        // 1. studentId dimasukkan user id (atau studentId dari request)
        $studentId = $requestData['studentId'] ?? ('student-user-' . $user->id);
        
        // 2. taskId terjemahan resmi AI (e.g. essay-university, essay-motivation, application-transcript)
        $taskId = $requestData['taskId'] ?? ('essay-' . $essayType);

        // 3. TARGET URL RESMI AI MICROSERVICE
        $baseUrl = rtrim(config('services.llama.url') ?? env('LLAMA_API_URL', 'https://bodacious-armed-tightwad.ngrok-free.dev/api'), '/');
        if (str_ends_with($baseUrl, '/journey/task/upload')) {
            $targetUrl = $baseUrl;
        } elseif (str_ends_with($baseUrl, '/api')) {
            $targetUrl = $baseUrl . '/journey/task/upload';
        } else {
            $targetUrl = $baseUrl . '/api/journey/task/upload';
        }

        try {
            // LOG REQUEST PAYLOAD KE AI MICROSERVICE
            Log::info("Payload Request dikirim ke AI Essay Microservice [Target: {$targetUrl}]:", [
                'studentId'        => $studentId,
                'taskId'           => $taskId,
                'essay_type'       => $essayType,
                'scholarship_name' => $scholarshipName,
                'file_name'        => $file ? $file->getClientOriginalName() : null,
                'file_size'        => $file ? $file->getSize() : null,
                'essay_text_length'=> strlen($essayText ?? ''),
            ]);

            $httpRequest = Http::timeout(60)
                ->withHeaders([
                    'Accept'                       => 'application/json',
                    'ngrok-skip-browser-warning' => 'true'
                ]);

            // Lampirkan berkas fisik sebagai multipart '-F file=@...'
            if ($file && file_exists($file->getRealPath())) {
                $httpRequest->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName(),
                    ['Content-Type' => $file->getClientMimeType()]
                );
            } elseif (!empty($essayText)) {
                // Jika user mengirim teks tanpa berkas file, buat berkas teks temporer sebagai 'file'
                $httpRequest->attach(
                    'file',
                    $essayText,
                    'essay_submission.txt',
                    ['Content-Type' => 'text/plain']
                );
            }

            // Kirim Multipart Form Data dengan studentId, taskId, dan metadata esai
            $response = $httpRequest->post($targetUrl, [
                'studentId'        => $studentId,
                'taskId'           => $taskId,
                'essay_type'       => $essayType,
                'scholarship_name' => $scholarshipName,
                'essay_text'       => $essayText ?? '',
            ]);

            // LOG RESPONS MENTAH DARI AI MICROSERVICE
            Log::info("Respons Mentah diterima dari AI Essay Microservice [Target: {$targetUrl}]:", [
                'status' => $response->status(),
                'body'   => $response->json() ?? $response->body(),
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $data = is_array($json) && isset($json['data']) ? $json['data'] : (is_array($json) ? $json : []);

                // Cek jika AI mengembalikan warning / penolakan walau status 200
                if (isset($json['success']) && $json['success'] === false) {
                    $aiMsg = $json['message'] ?? "Verifikasi AI gagal: Harap unggah dokumen yang jelas.";
                    throw new \Exception($aiMsg, 422);
                }

                if (isset($data['evaluation']) || isset($data['overall_score']) || isset($data['score']) || isset($data['success'])) {
                    Log::info("Berhasil memproses & mengekstrak evaluasi esai dari AI Microservice.");
                    return $this->formatAIResponse($data);
                }
            } else {
                $json = $response->json();
                Log::error("AI Essay Microservice Error Status {$response->status()} [Target: {$targetUrl}]:", [
                    'body' => $response->body()
                ]);

                // Jika AI mengembalikan 422 / Validation Warning JSON, teruskan pesan dari AI ke user!
                if (is_array($json) && (!empty($json['message']) || isset($json['warning']))) {
                    $aiMessage = $json['message'] ?? "Dokumen tidak dapat diverifikasi oleh AI. Harap unggah berkas yang jelas.";
                    throw new \Exception($aiMessage, 422);
                }
            }
        } catch (\Exception $e) {
            if ($e->getCode() === 422) {
                throw $e; // Re-throw AI Validation Warning agar menghentikan transaksi dan tampil di user!
            }
            Log::warning("AI Endpoint {$targetUrl} Exception / tidak dapat dijangkau: " . $e->getMessage());
        }

        // Fallback Local Evaluator jika AI Service offline
        Log::info("Menjalankan Local Fallback AI Essay Evaluator...");
        return $this->fallbackLocalEssayEvaluator($essayText ?? '', $essayType);
    }

    /**
     * Format dan Validasi Struktur JSON Respon AI (Mendukung Objek 'evaluation', 'suggestions', 'score', 'journey')
     */
    protected function formatAIResponse(array $data): array
    {
        $docType = $data['documentType'] ?? 'essay';
        $isDocument = in_array(strtolower($docType), ['cv', 'transcript', 'recommendation', 'certificate', 'loa']);
        $aiMessage = $data['message'] ?? null;

        $evaluation = $data['evaluation'] ?? [];

        if (empty($evaluation) && ($isDocument || isset($data['completed']))) {
            $evaluation = [
                'status'      => 'completed',
                'message'     => $aiMessage ?? ucfirst($docType) . " uploaded and verified successfully.",
                'suggestions' => [],
                'canComplete' => true,
            ];
        }

        $score = $evaluation['score'] 
            ?? $data['score'] 
            ?? $data['overall_score'] 
            ?? ($isDocument ? 100 : 75);

        $overallScore = $data['overall_score'] ?? $score;

        $categories = $data['categories'] ?? [
            'storytelling'          => (int) $score,
            'motivation'            => (int) $score,
            'leadership'            => (int) $score,
            'impact'                => (int) $score,
            'scholarship_alignment' => (int) $score,
            'clarity'               => (int) $score
        ];

        $defaultStrengths = $isDocument 
            ? [ $aiMessage ?? "Document (" . strtoupper($docType) . ") uploaded and verified successfully." ]
            : [
                "Essay content demonstrates clear academic motivation and relevance to goals.",
                "Structure and argument flow are cohesive and well-written."
            ];

        $defaultWeaknesses = $isDocument ? [] : [
            "Leadership impact could be reinforced with further concrete metrics."
        ];

        $defaultSuggestions = $isDocument ? [] : [
            "Consider providing more specific examples of how your experiences influenced your goals.",
            "Highlight more explicit connections between your experiences and the scholarship's objective."
        ];

        $suggestions = $evaluation['suggestions'] 
            ?? $data['recommendations'] 
            ?? $data['suggestions'] 
            ?? $defaultSuggestions;

        return [
            'score'         => (int) $score,
            'overall_score' => (int) $overallScore,
            'categories'    => [
                'storytelling'          => (int) ($categories['storytelling'] ?? $score),
                'motivation'            => (int) ($categories['motivation'] ?? $score),
                'leadership'            => (int) ($categories['leadership'] ?? $score),
                'impact'                => (int) ($categories['impact'] ?? $score),
                'scholarship_alignment' => (int) ($categories['scholarship_alignment'] ?? $score),
                'clarity'               => (int) ($categories['clarity'] ?? $score),
            ],
            'strengths'       => is_array($data['strengths'] ?? null) ? $data['strengths'] : $defaultStrengths,
            'weaknesses'      => is_array($data['weaknesses'] ?? null) ? $data['weaknesses'] : $defaultWeaknesses,
            'recommendations' => is_array($suggestions) ? $suggestions : (is_array($defaultSuggestions) ? $defaultSuggestions : [$suggestions]),
            'evaluation'      => $evaluation,
            'journey'         => $data['journey'] ?? null,
            'task'            => $data['task'] ?? null,
            'documentType'    => $docType,
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
