<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\UserMilestone;
use App\Models\DocumentVault;

class AIMentorChatService
{
    public function chat($user, $userMessage)
    {
        $endpoint = env('AI_TIMELINE_ENDPOINT', 'http://10.255.255.254:11434/api/generate');

        // 1. Kumpulkan Konteks Progres User dari Database
        $milestones = UserMilestone::where('user_id', $user->id)->get();
        $completedTasks = $milestones->where('status', 'completed')->count();
        $totalTasks = $milestones->count();
        
        $pendingTasks = $milestones->where('status', '!=', 'completed')->pluck('task_name')->toArray();
        $pendingTasksStr = empty($pendingTasks) ? 'Tidak ada task aktif' : implode(', ', $pendingTasks);

        $uploadedDocs = DocumentVault::where('user_id', $user->id)->pluck('file_type')->unique()->toArray();
        $uploadedDocsStr = empty($uploadedDocs) ? 'Belum ada dokumen di Vault' : implode(', ', $uploadedDocs);

        // 2. Susun Prompt Personal untuk AI Mentor
        $prompt = "
        Anda adalah AI Mentor Beasiswa pribadi yang siaga 24/7. Anda suportif, empatik, kritis, taktis, dan ramah seperti seorang kakak tingkat atau konsultan profesional.
        
        [PROFIL & PROGRES MENTEE SAAT INI]
        - Nama Mentee: {$user->name}
        - Skor Readiness: " . ($user->readiness_score ?? 'Belum dihitung') . "
        - Total XP: {$user->xp_points} poin
        - Progres Milestone: {$completedTasks} dari {$totalTasks} task selesai.
        - Task Aktif/Belum Selesai: [{$pendingTasksStr}]
        - Dokumen di Vault: [{$uploadedDocsStr}]

        [TUGAS ANDA SEBAGAI MENTOR]
        1. Jawab pertanyaan mentee dengan hangat, relevan, dan solutif berdasarkan profil dan progres mereka di atas.
        2. Jika ada kekurangan dokumen (misal CV atau Transkrip belum di-upload), ingatkan mereka dengan ramah.
        3. Jika skor latihan/kesiapan mereka dirasa masih kurang berdasarkan data, berikan saran praktis peningkatannya.
        4. Ingatkan mereka secara natural untuk memanfaatkan jadwal konsultasi dengan Human Mentor jika persiapannya menemui jalan buntu.
        5. Jangan gunakan format JSON murni untuk chat bebas ini. Berikan jawaban teks biasa yang natural, rapi, dan mudah dibaca (boleh gunakan markdown ringan seperti bold atau list).

        [PERTANYAAN MENTEE]
        {$userMessage}
        ";

        $ollamaPayload = [
            'model'  => 'qwen2.5:latest',
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => 0.7, // Ditingkatkan sedikit agar respons chat lebih natural & variatif
                'top_p' => 0.9
            ]
        ];

        try {
            $response = Http::timeout(120)->post($endpoint, $ollamaPayload);

            if ($response->successful()) {
                $result = $response->json();
                return $result['response'] ?? 'Maaf, mentor AI sedang beristirahat sebentar. Coba lagi ya!';
            }

            Log::error('AI Chatbot Endpoint Error: ' . $response->body());
            return 'Maaf, terjadi kendala saat menghubungkan ke server AI mentor.';

        } catch (\Exception $e) {
            Log::error('AI Chatbot Exception: ' . $e->getMessage());
            return 'Maaf, layanan AI mentor sedang tidak aktif.';
        }
    }
}