<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AITimelineService
{
    /**
     * @param array $payload (Data mentah JSON sesuai spesifikasi AI Engineer)
     * @return array|null
     */
    public function generate(array $payload)
    {
        try {
            $baseUrl = env('LLAMA_API_URL');
            $apiKey  = env('LLAMA_API_KEY'); 

            // SESUAIKAN DENGAN INSTRUKSI AI ENGINEER: POST /api/journey
            $endpoint = 'journey'; 

            $fullApiUrl = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

            Log::info("Payload dikirim ke Llama API (Journey) [Target: {$fullApiUrl}]:", $payload);

            $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'ngrok-skip-browser-warning' => 'true',
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(120)
            ->post($fullApiUrl, $payload); 

            if ($response->successful()) {
                $responseData = $response->json();

                Log::info('Respons Mentah diterima dari Llama API (Journey):', [
                    'status' => $response->status(),
                    'body'   => $responseData
                ]);

                return $responseData;
            }

            Log::error('Llama API Journey Error Status: ' . $response->status(), [
                'url'  => $fullApiUrl,
                'body' => $response->body()
            ]);
            
            return null;

        } catch (\Exception $e) {
            Log::error('AI Timeline Service Exception: ' . $e->getMessage());
            return null;
        }
    }
}