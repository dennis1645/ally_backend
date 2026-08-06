<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIDiagnosticService
{
    /**
     * @param array $scores (Berisi array skor kategori dan overall_score)
     * @param array $userData (IPK, jurusan, target, dll - jika ada)
     * @param array $tags (Kumpulan weakness dan strength dari opsi yang dipilih)
     * @param string $assessmentType ('onboarding' atau 'initial_diagnostic')
     * @return array|null
     */
    public function generateAnalysis(array $scores, array $userData, array $tags, string $assessmentType)
    {
        // Asumsi nilai maksimal untuk keseluruhan asesmen
        $maxOverallScore = 100; 

        $prompt = $this->buildPrompt($scores, $maxOverallScore, $userData, $tags, $assessmentType);

        try {
            // Mengambil API Key dari .env
            $apiKey = env('GEMINI_API_KEY');
            
            // PERBAIKAN: Endpoint Google menggunakan Gemini 3.5 Flash
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key={$apiKey}";

            // Request ke Google Gemini API
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => 'Anda adalah AI Mentor Beasiswa yang ahli. Anda hanya boleh membalas dengan format JSON murni.']
                    ]
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    // Fitur keren Gemini: Memaksa output keluar sebagai JSON
                    'responseMimeType' => 'application/json', 
                ]
            ]);

            if ($response->successful()) {
                // Cara parsing response JSON dari struktur Google Gemini
                $content = $response->json('candidates.0.content.parts.0.text');
                
                // Karena kita sudah pakai responseMimeType JSON, biasanya output sudah bersih
                // Tapi kita tetap antisipasi kalau ada markdown yang terbawa
                $content = preg_replace('/```json|```/', '', $content);
                
                return json_decode(trim($content), true);
            }

            Log::error('AI Diagnostic API Error: ', $response->json());
            return null;

        } catch (\Exception $e) {
            Log::error('AI Diagnostic Service Exception: ' . $e->getMessage());
            return null;
        }
    }

    private function buildPrompt(array $scores, int $maxOverallScore, array $userData, array $tags, string $assessmentType): string
    {
        $userDataStr = json_encode($userData);
        $scoresStr = json_encode($scores);
        $tagsStr = json_encode($tags);

        $instruction = "";
        if ($assessmentType === 'onboarding') {
            $instruction = "Tipe asesmen ini adalah 'onboarding' (user belum mendaftar/login). 
            Buat 'system_recommendation' (sekitar 2 paragraf pendek) yang SANGAT PERSUASIF. 
            Sebutkan skor kesiapan mereka dalam format 'Angka Asli (Persentase%)', contoh: 'Skor kesiapanmu 45 (45%)'.
            Validasi keinginan mereka, lalu ajak dan yakinkan mereka bahwa platform/mentor kami adalah tempat yang tepat untuk membantu mereka meningkatkan skor dan memenangkan beasiswa. Gunakan nada yang ramah dan suportif.";
        } else {
            $instruction = "Tipe asesmen ini adalah 'initial_diagnostic' (user sudah login dan menjadi member). 
            Buat 'system_recommendation' yang lebih MENDALAM dan ACTIONABLE (3-4 paragraf). 
            Sebutkan skor mereka dalam format 'Angka Asli (Persentase%)'. 
            Berikan langkah-langkah konkret apa yang harus mereka perbaiki berdasarkan 'weaknesses' yang mereka miliki, dan bagaimana cara memaksimalkan 'strengths' mereka.";
        }

        return <<<PROMPT
Analisis profil calon pelamar beasiswa ini berdasarkan data berikut:

Data User: $userDataStr
Skor Mentah (Raw Scores): $scoresStr
Skor Maksimal Keseluruhan: $maxOverallScore
Tags yang didapat dari jawaban: $tagsStr

Instruksi Khusus:
$instruction

Gabungkan tags yang relevan, atau tambahkan tags kelemahan/kekuatan baru jika kamu melihat pola dari data tersebut.

KEMBALIKAN OUTPUT STRICT DALAM FORMAT JSON BERIKUT (Gunakan Bahasa Indonesia):
{
    "weaknesses_mapping": ["kelemahan1", "kelemahan2", "kelemahan3"],
    "strengths_mapping": ["kekuatan1", "kekuatan2"],
    "system_recommendation": "Teks rekomendasi Anda di sini sesuai instruksi..."
}
PROMPT;
    }
}