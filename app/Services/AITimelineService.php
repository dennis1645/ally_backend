<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AITimelineService
{
    public function generate($data)
    {
        // Pastikan port dan IP sesuai dengan konfigurasi server/WSL kamu
        $endpoint = env('AI_TIMELINE_ENDPOINT', 'http://10.255.255.254:11434/api/generate');

        $prompt = $this->buildPrompt($data);

        $ollamaPayload = [
            'model'  => 'qwen2.5:latest',
            'prompt' => $prompt,
            'stream' => false,
            'format' => 'json',
            'options' => [
                'temperature' => 0.4, // Cukup rendah agar JSON & tanggal akurat
                'top_p' => 0.9
            ]
        ];

        try {
            $response = Http::timeout(120)->post($endpoint, $ollamaPayload);

            if ($response->successful()) {
                $result = $response->json();
                $aiText = $result['response'] ?? '';
                
                // OPTIMASI 1: Bersihkan markdown jika ada
                $aiText = preg_replace('/^```json\s*(.*?)\s*```$/ms', '$1', $aiText);
                
                // OPTIMASI 2: Amankan JSON dengan mencari kurung kurawal pertama dan terakhir
                $start = strpos($aiText, '{');
                $end = strrpos($aiText, '}');
                
                if ($start !== false && $end !== false) {
                    $jsonString = substr($aiText, $start, $end - $start + 1);
                    $parsedJson = json_decode($jsonString, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $parsedJson;
                    }
                }

                // Jika gagal di-parse
                Log::error('AI JSON Decode Error: ' . json_last_error_msg() . ' | Raw Text: ' . $aiText);
                return null;
            }

            Log::error('AI Endpoint Error HTTP: ' . $response->status() . ' - ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('AI Service Exception: ' . $e->getMessage());
            return null;
        }
    }

    private function buildPrompt($data)
    {
        $modeKilat = $data['is_crash_course'] 
            ? 'YA (Sisa waktu sangat mepet! Buat jadwal harian/mingguan yang sangat ketat dan langsung ke poin utama)' 
            : 'TIDAK (Waktu masih cukup. Buat jadwal ideal, bertahap, dan konsisten)';

        // Jika user belum upload apa-apa, berikan instruksi tegas ke AI
        $dokumenUser = empty(trim($data['uploaded_docs'])) 
            ? 'KOSONG (Belum ada dokumen satupun yang diunggah)' 
            : $data['uploaded_docs'];

        return "
        Anda adalah AI Mentor Beasiswa yang suportif, detail, ahli strategi, dan empatik.
        Tugas Anda adalah merancang timeline persiapan beasiswa yang sangat personal.

        [KONTEKS MENTEE]
        - Target Beasiswa: {$data['scholarship_name']}
        - Waktu Tersedia: {$data['current_date']} s/d {$data['deadline_date']} (Sisa: {$data['days_remaining']} hari)
        - Dokumen di Vault Mentee Saat Ini: [{$dokumenUser}]
        - Status Terdesak: {$modeKilat}

        [INSTRUKSI ANALISIS KESENJANGAN (GAP ANALYSIS)]
        1. Syarat umum beasiswa biasanya meliputi: CV/Resume, Ijazah, Transkrip Nilai, KTP/Paspor, Sertifikat Bahasa (IELTS/TOEFL), Motivation Letter/Esai, dan Surat Rekomendasi.
        2. BANDINGKAN syarat di atas dengan 'Dokumen di Vault Mentee Saat Ini'. 
        3. Dokumen krusial yang BELUM ADA wajib Anda jadikan tugas (milestone) dengan instruksi cara mendapatkannya/membuatnya.

        [ATURAN PENULISAN OUTPUT]
        1. Buat 5 hingga 8 langkah persiapan. PENTING: Langkah paling TERAKHIR WAJIB berisi instruksi untuk \"Pendaftaran / Submit Dokumen Final\". AI tidak boleh berhenti sebelum tahap pendaftaran selesai.
        2. ATURAN SESI MENTERI/PREMIUM: Di antara langkah-langkah tersebut (misalnya saat memasuki tahap bedah esai atau finalisasi dokumen sebelum submit), WAJIB sisipkan 1 langkah khusus yang mewajibkan mentee untuk melakukan \"Booking Sesi Konsultasi 1-on-1 dengan Human Mentor\". Tandai langkah mentor ini dengan `is_premium: true`. Langkah gratis lainnya diatur `is_premium: false`.
        3. 'task_name': Gunakan format penamaan fase. Contoh: 'Fase 1: Pembuatan Akun', 'Fase 3: Sesi Review Bersama Mentor (Premium)'. JANGAN gunakan kata 'Minggu' jika jarak waktu target_deadline antar tugas diperkirakan lebih dari 7 hari. Gunakan kata 'Fase' atau 'Tahap'.
        4. 'description': WAJIB gunakan gaya bahasa CHAT MANUSIAWI (Gunakan sapaan seperti 'Halo!', 'Pejuang Beasiswa', dll). Tunjukkan empati, beri semangat, tegur dengan ramah jika dokumen mereka masih kosong, dan berikan tips taktis. Khusus untuk langkah mentor, arahkan mereka untuk memilih jadwal dan melakukan booking sesi.
        5. 'target_deadline': HARUS format YYYY-MM-DD. Tanggal harus logis, berurutan, terdistribusi merata, dan WAJIB berada di dalam rentang {$data['current_date']} hingga {$data['deadline_date']}. Khusus untuk langkah TERAKHIR (Pendaftaran), set tanggalnya sama dengan atau maksimal H-2 dari deadline ({$data['deadline_date']}).
        6. 'is_mandatory': true atau false.
        7. 'is_premium': true (hanya untuk langkah sesi bersama mentor) atau false (untuk langkah reguler/gratis).
        8. 'xp_reward': Berikan nilai integer antara 50 hingga 100 berdasarkan tingkat kesulitan tugas. Langkah pendaftaran terakhir bisa diberi XP maksimal (100).

        [FORMAT WAJIB - HANYA KELUARKAN JSON MURNI TANPA TEKS LAIN]
        {
          \"milestones\": [
            {
              \"task_name\": \"string\",
              \"description\": \"string\",
              \"is_mandatory\": boolean,
              \"is_premium\": boolean,
              \"target_deadline\": \"YYYY-MM-DD\",
              \"xp_reward\": integer
            }
          ]
        }
        ";
    }
}