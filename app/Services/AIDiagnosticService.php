<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIDiagnosticService
{
    /**
     * @param array $userAnswers (Kumpulan jawaban mentah yang dipilih user)
     * @param array $userData (Data profil user, misal: IPK, jurusan target - jika ada)
     * @param string $assessmentType ('onboarding' atau 'initial_diagnostic')
     * @return array|null
     */
    public function generateAnalysis(array $userAnswers, array $userData, string $assessmentType)
    {
        try {
            $apiUrl = env('LLAMA_API_URL');
            $apiKey = env('LLAMA_API_KEY'); 

            $payload = [
                'assessment_type' => $assessmentType,
                'user_data'       => $userData,
                'answers'         => $userAnswers,
            ];

            // 1. Log payload yang dikirim ke AI
            Log::info('Payload dikirim ke Llama API:', $payload);

            $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'ngrok-skip-browser-warning' => 'true',
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(60)
            ->post($apiUrl, $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                // 2. LOG RESPON MENTAH DARI AI: Catat apa yang dikembalikan server AI
                Log::info('Respons Mentah diterima dari Llama API:', [
                    'status' => $response->status(),
                    'body'   => $responseData
                ]);

                return $responseData;
            }

            // Jika API Llama error (misal 500 Internal Server Error)
            Log::error('Llama 3.2 API Error Status: ' . $response->status(), [
                'body' => $response->body()
            ]);
            
            return null;

        } catch (\Exception $e) {
            Log::error('AI Diagnostic Service Exception: ' . $e->getMessage());
            return null;
        }
    }
}