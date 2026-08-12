<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserMilestone;
use App\Models\DocumentVault;
use App\Models\DiagnosticAssessment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIMentorChatService
{
    /**
     * Mengirimkan pesan chat ke Gemini AI Chatbot dengan menyertakan konteks lengkap user.
     *
     * @param User $user
     * @param string $userMessage
     * @return array
     */
    public function chat(User $user, string $userMessage): array
    {
        // 1. KUMPULKAN KONTEKS AKADEMIK & PROFILE USER
        $readinessScore = (int) ($user->readiness_score ?? 0);
        $primaryTarget  = $user->primary_scholarship_target ?? 'Belum ditentukan';
        $gpa            = $user->gpa ?? 'Belum diisi';
        $undergradMajor = $user->undergraduate_major ?? 'Belum diisi';
        $targetMajor    = $user->target_major ?? 'Belum diisi';
        $isPremium      = $user->is_premium ? 'Ya (Premium)' : 'Tidak (Free)';

        // 2. KUMPULKAN DATA MILESTONE & PROGRES USER
        $milestones = UserMilestone::where('user_id', $user->id)->get();
        $totalMilestones = $milestones->count();

        $completedTasks = $milestones->where('status', 'completed')->pluck('task_name')->toArray();
        $inProgressTasks = $milestones->where('status', 'in_progress')->pluck('task_name')->toArray();
        $pendingTasks   = $milestones->where('status', 'pending')->pluck('task_name')->take(5)->toArray();

        $completedCount  = count($completedTasks);
        $inProgressCount = count($inProgressTasks);
        $pendingCount    = $milestones->where('status', 'pending')->count();

        $completedTasksStr  = empty($completedTasks) ? 'Belum ada' : implode(', ', array_slice($completedTasks, 0, 5));
        $inProgressTasksStr = empty($inProgressTasks) ? 'Tidak ada task aktif' : implode(', ', $inProgressTasks);
        $pendingTasksStr    = empty($pendingTasks) ? 'Tidak ada task menanti' : implode(', ', $pendingTasks);

        // 3. KUMPULKAN DATA BRANKAS DOKUMEN (DOCUMENT VAULT)
        $uploadedDocs = DocumentVault::where('user_id', $user->id)->pluck('file_type')->unique()->toArray();
        $allRequiredDocs = ['cv', 'transcript', 'certificate', 'essay', 'loa'];
        $missingDocs = array_diff($allRequiredDocs, $uploadedDocs);

        $uploadedDocsStr = empty($uploadedDocs) ? 'Belum ada dokumen di Vault' : implode(', ', array_map('strtoupper', $uploadedDocs));
        $missingDocsStr  = empty($missingDocs) ? 'Dokumen utama sudah lengkap!' : implode(', ', array_map('strtoupper', $missingDocs));

        // 4. KUMPULKAN HASIL ASESMEN DIAGNOSTIK AI (ASSESSMENT 2)
        $assessment2 = DiagnosticAssessment::with('recommendedScholarship')
            ->where('user_id', $user->id)
            ->where('assessment_type', 'assessment_2')
            ->first();

        $diagnosticReason = $assessment2->reason ?? 'Belum ada analisis mendalam';
        $recommendedScholarshipName = $assessment2 && $assessment2->recommendedScholarship 
            ? $assessment2->recommendedScholarship->name 
            : 'Belum ada rekomendasi';

        // 5. SUSUN PROMPT SISTEM ENRICHED CONTEXT UNTUK GEMINI AI
        $systemPrompt = "Anda adalah AI Mentor Beasiswa pribadi berpengalaman di platform ALLY. Karakter Anda adalah seorang konsultan profesional yang ramah, taktis, empatik, kritis, dan sangat mengenal perkembangan mentee Anda.

[DATA LENGKAP PROFIL & KEMAJUAN MENTEE]
- Nama Mentee: {$user->name} ({$user->email})
- Status Keanggotaan: {$isPremium}
- IPK / Jurusan Asal: {$gpa} / {$undergradMajor}
- Jurusan Target: {$targetMajor}
- Target Beasiswa Utama: {$primaryTarget}
- Skor Readiness Kesiapan: {$readinessScore}% (Maksimal 100%)
- Total Poin XP: {$user->xp_points} XP

[STATUS MILESTONE & PERJALANAN SCHOLARSHIP]
- Total Milestone: {$totalMilestones} (Selesai: {$completedCount}, Sedang Berjalan: {$inProgressCount}, Menunggu: {$pendingCount})
- Task yang Sudah Selesai: [{$completedTasksStr}]
- Task Aktif / Sedang Dikerjakan Saat Ini: [{$inProgressTasksStr}]
- Task yang Akan Datang: [{$pendingTasksStr}]

[STATUS BRANKAS DOKUMEN (DOCUMENT VAULT)]
- Dokumen yang Sudah Diunggah: [{$uploadedDocsStr}]
- Dokumen yang Masih Belum Diunggah/Kurang: [{$missingDocsStr}]

[HASIL ANALISIS DIAGNOSTIK AI]
- Saran/Catatan AI Assessment: {$diagnosticReason}
- Rekomendasi Matcher Beasiswa AI: {$recommendedScholarshipName}

[TUGAS ANDA SEBAGAI AI MENTOR]
1. Jawab pertanyaan mentee secara ramah, profesional, taktis, dan personal berdasarkan data profil dan progres di atas.
2. Berikan dorongan atau masukan yang sesuai dengan task yang sedang ia kerjakan saat ini.
3. Jika ada berkas penting yang masih belum diunggah ([{$missingDocsStr}]), ingatkan mentee secara natural.
4. Gunakan bahasa Indonesia yang hangat, sopan, dan mudah dipahami. Gunakan format markdown ringan (seperti bold atau list).

[PERTANYAAN MENTEE]
{$userMessage}";

        // 6. EXECUTE GEMINI API HTTP REQUEST
        $geminiApiKey = env('GEMINI_API_KEY');
        $aiReply = null;

        if ($geminiApiKey) {
            try {
                // Endpoint Google Gemini API (v1beta gemini-1.5-flash)
                $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . trim($geminiApiKey);

                $payload = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemPrompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 1200,
                    ]
                ];

                Log::info("Kirim Chat ke Gemini API URL: {$apiUrl}");

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($apiUrl, $payload);

                if ($response->successful()) {
                    $resData = $response->json();
                    $aiReply = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;
                } else {
                    Log::error("Gemini API Error Status {$response->status()}: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("Gemini API Exception: " . $e->getMessage());
            }
        }

        // 7. FALLBACK JIKA GEMINI UNREACHABLE ATAU NETWORK ISSUE
        if (!$aiReply) {
            $aiReply = "Halo **{$user->name}**! Saya mentor AI Anda di ALLY. Saat ini skor kesiapan Anda berada di angka **{$readinessScore}%** dengan target beasiswa **{$primaryTarget}**.\n\n" .
                "Fokus utama Anda saat ini adalah menyelesaikan task aktif: **{$inProgressTasksStr}**." .
                ($missingDocsStr !== 'Dokumen utama sudah lengkap!' ? " Jangan lupa juga mengunggah berkas **{$missingDocsStr}** ke Document Vault ya!" : "") .
                "\n\nAda yang ingin Anda diskusikan lebih lanjut?";
        }

        return [
            'user_message' => $userMessage,
            'ai_response'  => $aiReply,
            'context_summary' => [
                'user_name'                  => $user->name,
                'readiness_score'            => $readinessScore,
                'primary_scholarship_target' => $primaryTarget,
                'in_progress_tasks'          => $inProgressTasks,
                'uploaded_documents'         => $uploadedDocs,
                'missing_documents'          => array_values($missingDocs),
            ]
        ];
    }
}