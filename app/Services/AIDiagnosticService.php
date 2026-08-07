<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIDiagnosticService
{
    /**
     * @param array $userAnswers (Kumpulan jawaban mentah yang dipilih user)
     * @param array $userData (Data profil user, misal: IPK, jurusan target - jika ada)
     * @param string $assessmentType ('onboarding', 'initial_diagnostic', atau 'assessment_2')
     * @return array|null
     */
    public function generateAnalysis(array $userAnswers, array $userData, string $assessmentType)
    {
        try {
            // Ambil Base URL dari .env (Contoh: https://bodacious-armed-tightwad.ngrok-free.dev/api/)
            $baseUrl = env('LLAMA_API_URL');
            $apiKey  = env('LLAMA_API_KEY'); 

            // Tentukan Endpoint khusus berdasarkan jenis assessment
            $endpoint = match ($assessmentType) {
                'onboarding', 'initial_diagnostic' => 'assessment/readiness',
                'assessment_2', 'deep_diagnostic'  => 'assessment/deep', // Silakan ubah 'assessment/deep' sesuai endpoint asli AI Engineer-mu
                default                            => 'assessment/readiness', // Fallback default
            };

            // Gabungkan Base URL dan Endpoint dengan aman (menghindari double slash '//')
            $fullApiUrl = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

            $payload = [
                'assessment_type' => $assessmentType,
                'user_data'       => $userData,
                'answers'         => $userAnswers,
            ];

            // 1. Log payload dan URL target yang dikirim ke AI
            Log::info("Payload dikirim ke Llama API [Target: {$fullApiUrl}]:", $payload);

            $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'ngrok-skip-browser-warning' => 'true',
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(60)
            ->post($fullApiUrl, $payload); // Kirim ke URL yang sudah digabung

            if ($response->successful()) {
                $responseData = $response->json();

                // 2. LOG RESPON MENTAH DARI AI: Catat apa yang dikembalikan server AI
                Log::info('Respons Mentah diterima dari Llama API:', [
                    'status' => $response->status(),
                    'body'   => $responseData
                ]);

                return $responseData;
            }

            // Jika API Llama error (misal 500 Internal Server Error atau 404 Not Found)
            Log::error('Llama 3.2 API Error Status: ' . $response->status(), [
                'url'  => $fullApiUrl,
                'body' => $response->body()
            ]);
            
            return null;

        } catch (\Exception $e) {
            Log::error('AI Diagnostic Service Exception: ' . $e->getMessage());
            return null;
        }
    }
}