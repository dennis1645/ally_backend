<?php

namespace App\Services;

use App\Models\User;
use App\Models\Scholarship;
use App\Models\MentorProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MentorMatchingService
{
    protected string $llamaApiUrl;

    public function __construct()
    {
        $baseUrl = config('services.llama.url') ?? env('LLAMA_API_URL', 'https://bodacious-armed-tightwad.ngrok-free.dev/api');
        $this->llamaApiUrl = rtrim($baseUrl, '/');
    }

    /**
     * Lakukan matching mentor ke AI Microservice / Local Fallback
     */
    public function matchMentors(?User $user, array $customPayload = []): array
    {
        // 1. Siapkan payload terstruktur sesuai format request AI
        $payload = $this->buildPayload($user, $customPayload);

        $endpointUrl = $this->llamaApiUrl . '/mentor/match';

        Log::info('Mengirim permintaan Mentor Matching ke AI Endpoint:', [
            'url'     => $endpointUrl,
            'payload' => $payload
        ]);

        try {
            // 2. Panggil API AI Microservice
            $response = Http::timeout(25)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'ngrok-skip-browser-warning' => 'true'
                ])
                ->post($endpointUrl, $payload);

            if ($response->successful()) {
                $rawResponse = $response->json();

                $matchedMentors = [];
                if (isset($rawResponse['mentors']) && is_array($rawResponse['mentors'])) {
                    $matchedMentors = $rawResponse['mentors'];
                } elseif (isset($rawResponse['data'])) {
                    $matchedMentors = is_array($rawResponse['data']) && isset($rawResponse['data']['mentors']) 
                        ? $rawResponse['data']['mentors'] 
                        : (is_array($rawResponse['data']) ? $rawResponse['data'] : [$rawResponse['data']]);
                } elseif (isset($rawResponse['mentor'])) {
                    $matchedMentors = is_array($rawResponse['mentor']) && isset($rawResponse['mentor'][0]) 
                        ? $rawResponse['mentor'] 
                        : [$rawResponse['mentor']];
                } elseif (is_array($rawResponse)) {
                    $matchedMentors = isset($rawResponse[0]) ? $rawResponse : [$rawResponse];
                }

                if (!empty($matchedMentors)) {
                    Log::info('Berhasil menerima data Mentor Matching dari AI Microservice.', ['count' => count($matchedMentors)]);
                    return $this->enrichWithLocalMentors($matchedMentors);
                }
            } else {
                Log::warning('Respon AI Mentor Matching tidak sukses. Status: ' . $response->status(), [
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Gagal menghubungi AI Microservice Mentor Matching: ' . $e->getMessage());
        }

        // 3. Fallback jika AI Service tidak merespons atau mengembalikan data kosong
        Log::info('Menjalankan Fallback Local Mentor Matcher...');
        return $this->fallbackLocalMatching($user, $payload);
    }

    /**
     * Konstruksi payload request standar dari profil user & input custom
     */
    public function buildPayload(?User $user, array $customPayload = []): array
    {
        // Jika frontend/client sudah mengirim struktur 'profile' yang lengkap, gunakan itu
        if (isset($customPayload['profile'])) {
            return $customPayload;
        }

        $academicDirection = $customPayload['study_direction'] 
            ?? $customPayload['target_major'] 
            ?? ($user ? ($user->target_major ?? $user->undergraduate_major) : null) 
            ?? 'Computer Science';

        $careerArea = $customPayload['contribution_area'] 
            ?? $customPayload['career_area'] 
            ?? ($user ? $user->headline : null) 
            ?? 'Technology';

        $scholarshipName = $customPayload['scholarship_name'] 
            ?? ($user ? ($user->primary_scholarship_target ?? optional($user->target_scholarship_data)->name) : null) 
            ?? 'LPDP Scholarship';

        // Extract leadership experience dari DB jika tidak dikirim custom
        $leadershipExperience = $customPayload['leadership_experience'] ?? [];
        if (empty($leadershipExperience) && $user) {
            $latestAssessment = \App\Models\DiagnosticAssessment::where('user_id', $user->id)->latest()->first();
            if ($latestAssessment && isset($latestAssessment->raw_answers)) {
                $raw = is_array($latestAssessment->raw_answers) ? $latestAssessment->raw_answers : json_decode($latestAssessment->raw_answers, true);
                if (is_array($raw)) {
                    foreach ($raw as $ans) {
                        if (is_array($ans) && !empty($ans['text_value'])) {
                            $leadershipExperience[] = $ans['text_value'];
                        }
                    }
                }
            }
        }

        return [
            'profile' => [
                'student_profile' => [
                    'academic' => [
                        'study_direction' => $academicDirection
                    ],
                    'leadership' => [
                        'experience' => array_values(array_unique($leadershipExperience))
                    ],
                    'career' => [
                        'contribution_area' => $careerArea
                    ]
                ],
                'scholarship' => [
                    'name' => $scholarshipName
                ]
            ]
        ];
    }

    /**
     * Menyelaraskan hasil AI dengan data mentor di database lokal kami (Local User & MentorProfile)
     */
    protected function enrichWithLocalMentors(array $aiMentors): array
    {
        return array_map(function ($mentorItem) {
            if (!is_array($mentorItem)) {
                return [
                    "id"   => "mentor-001",
                    "name" => is_string($mentorItem) ? $mentorItem : "Mentor",
                    "bio"  => "Scholarship mentor"
                ];
            }

            $aiName = trim($mentorItem['name'] ?? 'Scholarship Mentor');
            if (empty($aiName)) {
                $aiName = 'Scholarship Mentor';
            }

            // 1. Cari mentor di database lokal berdasarkan nama
            $matchedLocal = User::where('role', 'mentor')
                ->where(function ($q) use ($aiName) {
                    $q->where('name', 'LIKE', '%' . $aiName . '%')
                      ->orWhere('email', 'LIKE', '%' . \Illuminate\Support\Str::slug($aiName) . '%');
                })
                ->with(['mentorProfile', 'availabilities'])
                ->first();

            // 2. Jika BELUM ADA di database lokal, buatkan otomatis user & mentor_profile baru untuk SEMUA nama mentor AI!
            if (!$matchedLocal) {
                \Illuminate\Support\Facades\DB::beginTransaction();
                try {
                    $slug = \Illuminate\Support\Str::slug($aiName, '_');
                    $email = 'mentor_' . $slug . '@gmail.com';
                    
                    // Pastikan email unik jika ada bentrokan
                    if (User::where('email', $email)->exists()) {
                        $email = 'mentor_' . $slug . rand(10, 99) . '@gmail.com';
                    }

                    $matchedLocal = User::create([
                        'name'               => $aiName,
                        'email'              => $email,
                        'phone_number'       => '081' . rand(10000000, 99999999),
                        'password'           => \Illuminate\Support\Facades\Hash::make('P@ssw0rd123'),
                        'role'               => 'mentor',
                        'status'             => 'active',
                        'session_rate'       => 150000.00,
                        'bio'                => $mentorItem['bio'] ?? 'Scholarship researcher and postgraduate mentor.',
                        'headline'           => isset($mentorItem['study_country']) ? 'Scholarship Awardee & Mentor in ' . $mentorItem['study_country'] : 'Scholarship Awardee & Mentor',
                        'email_verified_at'  => now(),
                    ]);

                    $fields = is_array($mentorItem['fields'] ?? null) ? $mentorItem['fields'] : ['Computer Science', 'Artificial Intelligence'];
                    $careerAreas = is_array($mentorItem['career_areas'] ?? null) ? $mentorItem['career_areas'] : ['Technology', 'Research'];
                    $countries = is_array($mentorItem['countries'] ?? null) ? $mentorItem['countries'] : [$mentorItem['study_country'] ?? 'Japan'];
                    $scholarships = is_array($mentorItem['scholarships'] ?? null) ? $mentorItem['scholarships'] : ['LPDP Scholarship'];

                    MentorProfile::create([
                        'user_id'                          => $matchedLocal->id,
                        'university'                       => isset($mentorItem['study_country']) ? 'Top University in ' . $mentorItem['study_country'] : 'Global University',
                        'major'                            => implode(', ', $fields),
                        'degree_level'                     => 'Master',
                        'scholarship_awardee'              => implode(', ', $scholarships),
                        'destination_countries_expertise'  => $countries,
                        'study_fields_expertise'           => $fields,
                        'expertise_tags'                   => $careerAreas,
                        'languages'                        => ['Indonesian', 'English'],
                        'mentoring_style'                  => 'Tactical, structured, and goal-oriented',
                        'current_job'                      => 'Scholarship Awardee & Researcher',
                        'years_of_experience'              => 3,
                        'rating'                           => 5.00,
                        'is_accepting_mentees'             => true,
                    ]);

                    // Tambahkan slot ketersediaan (availabilities) default untuk 3 hari ke depan agar mentee bisa langsung book mentor ini
                    for ($i = 1; $i <= 3; $i++) {
                        \App\Models\MentorAvailability::create([
                            'mentor_id'      => $matchedLocal->id,
                            'available_date' => now()->addDays($i)->format('Y-m-d'),
                            'start_time'     => '14:00:00',
                            'end_time'       => '15:00:00',
                            'is_booked'      => false,
                        ]);
                    }

                    \Illuminate\Support\Facades\DB::commit();
                    \Illuminate\Support\Facades\Log::info("Otomatis mendaftarkan AI Mentor baru ke database lokal: {$aiName} (Email: {$email}, ID: {$matchedLocal->id})");

                    // Kirim email kredensial login ke mentor baru yang di-generate!
                    try {
                        \Illuminate\Support\Facades\Mail::to($matchedLocal->email)->send(new \App\Mail\MentorCredentialMail($matchedLocal, 'P@ssw0rd123'));
                        \Illuminate\Support\Facades\Log::info("📧 Email kredensial login berhasil dikirim ke mentor baru: {$email}");
                    } catch (\Exception $mailEx) {
                        \Illuminate\Support\Facades\Log::error("Gagal mengirim email kredensial ke mentor {$email}: " . $mailEx->getMessage());
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\DB::rollBack();
                    \Illuminate\Support\Facades\Log::error("Gagal mendaftarkan AI Mentor ke database lokal: " . $e->getMessage());
                }
            }

            // 3. Attach data lokal & kredensial ke respon JSON
            if ($matchedLocal) {
                $matchedLocal->loadMissing(['mentorProfile', 'availabilities']);
                $profile = $matchedLocal->mentorProfile;

                $mentorItem['local_mentor_id']     = $matchedLocal->id;
                $mentorItem['name']                = $matchedLocal->name;
                $mentorItem['email']               = $matchedLocal->email;
                $mentorItem['default_password']    = 'P@ssw0rd123';
                $mentorItem['profile_picture_url'] = $matchedLocal->profile_picture_url;
                $mentorItem['session_rate']        = (float) $matchedLocal->session_rate;
                $mentorItem['rating']              = (float) ($profile->rating ?? 5.0);
                $mentorItem['available_slots']     = $matchedLocal->availabilities->where('is_booked', false)->count();
                $mentorItem['university']          = $profile->university ?? null;
                $mentorItem['scholarships']        = is_array($mentorItem['scholarships'] ?? null) ? $mentorItem['scholarships'] : [$profile->scholarship_awardee ?? 'LPDP Scholarship'];
                $mentorItem['study_country']       = $mentorItem['study_country'] ?? ($profile->destination_countries_expertise[0] ?? 'Japan');
            }

            return $mentorItem;
        }, $aiMentors);
    }

    /**
     * Fallback Matching menggunakan Database Lokal saat AI Offline
     */
    protected function fallbackLocalMatching(?User $user, array $payload): array
    {
        $academic = $payload['profile']['student_profile']['academic']['study_direction'] ?? ($user ? $user->target_major : null) ?? 'Computer Science';
        $scholarshipTarget = $payload['profile']['scholarship']['name'] ?? ($user ? $user->primary_scholarship_target : null) ?? 'LPDP Scholarship';

        $mentors = User::where('role', 'mentor')
            ->with(['mentorProfile', 'availabilities'])
            ->get();

        if ($mentors->isEmpty()) {
            // Kembalikan Mock Data Standar AI jika DB belum memiliki mentor
            return [
                [
                    "id"                    => "mentor-001",
                    "local_mentor_id"       => 2,
                    "name"                  => "Sarah",
                    "fields"                => ["Artificial Intelligence", "Computer Science"],
                    "career_areas"          => ["Artificial Intelligence", "Technology", "Education Technology"],
                    "countries"             => ["Indonesia", "United Kingdom"],
                    "scholarships"          => ["Chevening Scholarship"],
                    "study_country"         => "United Kingdom",
                    "leadership_experience" => true,
                    "bio"                   => "AI professional with scholarship and postgraduate experience."
                ],
                [
                    "id"                    => "mentor-002",
                    "local_mentor_id"       => 2,
                    "name"                  => "Daniel",
                    "fields"                => ["Engineering", "Computer Science"],
                    "career_areas"          => ["Technology", "Research"],
                    "countries"             => ["Indonesia", "Japan"],
                    "scholarships"          => ["LPDP Scholarship"],
                    "study_country"         => "Japan",
                    "leadership_experience" => true,
                    "bio"                   => "Technology researcher and postgraduate scholarship mentor."
                ],
                [
                    "id"                    => "mentor-003",
                    "local_mentor_id"       => 2,
                    "name"                  => "Aisha",
                    "fields"                => ["Business", "Public Policy"],
                    "career_areas"          => ["Education", "Social Impact", "Public Policy"],
                    "countries"             => ["Indonesia", "Australia"],
                    "scholarships"          => ["LPDP Scholarship"],
                    "study_country"         => "Australia",
                    "leadership_experience" => true,
                    "bio"                   => "Scholarship alumna focused on education and social impact."
                ]
            ];
        }

        return $mentors->map(function ($lm, $index) use ($scholarshipTarget) {
            $mp = $lm->mentorProfile;

            $countries = ($mp && is_array($mp->destination_countries_expertise)) 
                ? $mp->destination_countries_expertise 
                : ["Indonesia", "United Kingdom"];

            $fields = ($mp && is_array($mp->study_fields_expertise)) 
                ? $mp->study_fields_expertise 
                : [($mp->major ?? "Computer Science")];

            $careerAreas = ($mp && is_array($mp->expertise_tags)) 
                ? $mp->expertise_tags 
                : ["Technology", "Education"];

            return [
                "id"                    => "mentor-00" . ($index + 1),
                "local_mentor_id"       => $lm->id,
                "name"                  => $lm->name,
                "fields"                => $fields,
                "career_areas"          => $careerAreas,
                "countries"             => $countries,
                "scholarships"          => [($mp->scholarship_awardee ?? $scholarshipTarget)],
                "study_country"         => $countries[0] ?? "United Kingdom",
                "leadership_experience" => true,
                "bio"                   => $lm->bio ?? "Scholarship alumna and mentor.",
                "profile_picture_url"   => $lm->profile_picture_url,
                "session_rate"          => (float) $lm->session_rate,
                "rating"                => (float) ($mp->rating ?? 5.0),
            ];
        })->values()->toArray();
    }
}
