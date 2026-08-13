<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 
    'email', 
    'phone_number', 
    'gender',
    'password', 
    'role', 
    'status', 
    'readiness_score', 
    'token_balance',       
    'premium_until',       
    'profile_picture_url', 
    'headline', 
    'bio', 
    'google_id', 
    'linkedin_id', 
    'xp_points', 
    'current_streak', 
    'longest_streak',
    'is_streak_frozen',
    'is_premium',
    'assigned_mentor_id',
    
    // ==========================================
    // Atribut Akademik & Target Baru (Task 1.5)
    // ==========================================
    'gpa', 
    'undergraduate_major', 
    'target_major', 
    'primary_scholarship_target',

    // ==========================================
    // Atribut Gaji, Saldo & Rekening Mentor
    // ==========================================
    'session_rate',
    'earning_balance',
    'bank_name',
    'bank_account_number',
    'bank_account_name'
])]
#[Hidden([
    'password', 
    'remember_token',
    'google_id',  // Best practice: Menyembunyikan ID OAuth dari response JSON API
])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Pastikan attribute virtual 'level' otomatis dikembalikan saat output JSON/API
     *
     * @var array
     */
    protected $appends = ['level', 'target_scholarship_id', 'target_scholarship_data', 'weekly_streak_tracker'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'readiness_score' => 'integer',
            'token_balance' => 'integer',    
            'premium_until' => 'datetime',   
            'xp_points' => 'integer',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'is_streak_frozen' => 'boolean',
            'is_premium' => 'boolean',
            'gpa' => 'decimal:2', // Format angka desimal untuk IPK
            'session_rate' => 'decimal:2',
            'earning_balance' => 'decimal:2',
        ];
    }

    // ==========================================
    // GAMIFIKASI & LEVELING LOGIC
    // ==========================================
    
    /**
     * Accessor untuk mengkalkulasi Level Gamifikasi secara real-time.
     * Logic: 1 Level = 300 XP (Maksimal Level 100)
     */
    public function getLevelAttribute(): int
    {
        $xp = (int) $this->xp_points;
        
        // Contoh: 0 - 299 XP = Level 1 | 300 - 599 XP = Level 2
        $calculatedLevel = floor($xp / 300) + 1;
        
        // Membatasi level maksimal di 100
        return $calculatedLevel > 100 ? 100 : (int) $calculatedLevel;
    }

    /**
     * Accessor untuk ID Beasiswa Target Utama User.
     */
    public function getTargetScholarshipIdAttribute(): ?int
    {
        // 1. Cek dari pivot table user_scholarships
        $scholarshipId = \Illuminate\Support\Facades\DB::table('user_scholarships')
            ->where('user_id', $this->id)
            ->value('scholarship_id');

        if ($scholarshipId) {
            return (int) $scholarshipId;
        }

        // 2. Fallback: Cek dari user_milestones aktif user
        $milestoneScholarshipId = UserMilestone::where('user_id', $this->id)
            ->whereNotNull('scholarship_id')
            ->value('scholarship_id');

        if ($milestoneScholarshipId) {
            return (int) $milestoneScholarshipId;
        }

        // 3. Fallback: Match berdasarkan nama primary_scholarship_target
        if ($this->primary_scholarship_target) {
            $matchedId = Scholarship::where('name', $this->primary_scholarship_target)
                ->orWhere('name', 'LIKE', '%' . $this->primary_scholarship_target . '%')
                ->value('id');

            if ($matchedId) {
                return (int) $matchedId;
            }
        }

        return null;
    }

    /**
     * Accessor untuk Data Detail Beasiswa Target Utama User.
     */
    public function getTargetScholarshipDataAttribute()
    {
        $scholarshipId = $this->target_scholarship_id;

        if (!$scholarshipId) {
            return null;
        }

        return Scholarship::with('universities:id,name,country,city,image_url')->find($scholarshipId);
    }

    /**
     * Relasi ke Log Aktivitas Harian User (Weekly Streak Tracker).
     */
    public function dailyActivityLogs()
    {
        return $this->hasMany(UserDailyActivityLog::class, 'user_id');
    }

    /**
     * Accessor untuk mengkalkulasi 7 hari Tracker Streak Mingguan (Senin - Minggu).
     */
    public function getWeeklyStreakTrackerAttribute(): array
    {
        $today = \Carbon\Carbon::now();
        $startOfWeek = $today->copy()->startOfWeek(); // Senin

        $logs = UserDailyActivityLog::where('user_id', $this->id)
            ->whereBetween('activity_date', [$startOfWeek->toDateString(), $today->copy()->endOfWeek()->toDateString()])
            ->get()
            ->keyBy(function ($item) {
                return \Carbon\Carbon::parse($item->activity_date)->toDateString();
            });

        $tracker = [];
        for ($i = 0; $i < 7; $i++) {
            $dayDate = $startOfWeek->copy()->addDays($i);
            $dateStr = $dayDate->toDateString();
            $dayName = $dayDate->format('D'); // Mon, Tue, Wed...

            $isToday = $dayDate->isToday();
            $isPast  = $dayDate->isPast() && !$isToday;

            if (isset($logs[$dateStr])) {
                $status = $logs[$dateStr]->status;
            } elseif ($isPast) {
                $status = $this->is_streak_frozen ? 'frozen' : 'missed';
            } elseif ($isToday) {
                $status = 'completed'; // Default hari ini aktif saat diakses
            } else {
                $status = 'upcoming';
            }

            $tracker[] = [
                'day'      => $dayName,
                'date'     => $dateStr,
                'status'   => $status,
                'is_today' => $isToday,
            ];
        }

        return $tracker;
    }

    // ==========================================
    // HELPER METHODS (Untuk Authorization)
    // ==========================================

    public function isMentor(): bool
    {
        return $this->role === 'mentor';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Relasi ke Mentor yang di-assign ke Mentee ini.
     */
    public function assignedMentor()
    {
        return $this->belongsTo(User::class, 'assigned_mentor_id')->with('mentorProfile');
    }

    public function milestones()
    {
        return $this->hasMany(UserMilestone::class);
    }

    public function essayAssessments()
    {
        return $this->hasMany(EssayAssessment::class);
    }

    /**
     * Relasi ke Document Vault (Nama fungsi disesuaikan dengan controller)
     */
    public function documents()
    {
        // Diperbaiki: DocumentVault::class (tanpa 's' sesuai nama file model di VS Code)
        return $this->hasMany(DocumentVault::class, 'user_id'); 
    }

    /**
     * Relasi Alias untuk Document Vault (Opsional, jika sudah terlanjur dipakai di tempat lain)
     */
    public function documentVaults()
    {
        return $this->hasMany(DocumentVault::class);
    }

    public function dailyJournals()
    {
        return $this->hasMany(DailyJournal::class);
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges', 'user_id', 'badge_id')
                    ->withPivot('earned_at');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function bookingsAsMentee()
    {
        return $this->hasMany(ConsultationBooking::class, 'mentee_id');
    }

    public function actionPlans()
    {
        return $this->hasMany(ActionPlan::class, 'mentee_id');
    }

    /**
     * Relasi ke Target Beasiswa (Dibutuhkan di getMenteeList)
     */
    public function scholarships()
    {
        return $this->belongsToMany(Scholarship::class, 'user_scholarships', 'user_id', 'scholarship_id');
    }

    /**
     * Relasi ke Bookmark Beasiswa (Unlimited Bookmarks - Milestone 2 Task 1.5)
     */
    public function bookmarks()
    {
        return $this->hasMany(ScholarshipBookmark::class, 'user_id');
    }

    /**
     * Relasi tunggal ke Hasil Diagnostic terbaru (Dibutuhkan di getPreSessionDossier)
     */
    public function diagnosticAssessment()
    {
        return $this->hasOne(DiagnosticAssessment::class)->latestOfMany();
    }

    /**
     * Relasi jamak ke semua riwayat Diagnostic 
     */
    public function diagnosticAssessments()
    {
        return $this->hasMany(DiagnosticAssessment::class);
    }

    // ==========================================
    // RELASI SEBAGAI MENTOR
    // ==========================================

    public function availabilities()
    {
        return $this->hasMany(MentorAvailability::class, 'mentor_id');
    }

    public function bookingsAsMentor()
    {
        return $this->hasMany(ConsultationBooking::class, 'mentor_id');
    }

    // ==========================================
    // OVERRIDE EMAIL NOTIFICATIONS (CUSTOM)
    // ==========================================

    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\CustomVerifyEmail);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\CustomResetPassword($token));
    }

    /**
     * Relasi: User memiliki banyak riwayat pengerjaan latihan/ujian (IELTS/TOEFL).
     */
    public function practiceAttempts()
    {
        return $this->hasMany(PracticeAttempt::class, 'user_id');
    }

    /**
     * Relasi: User memiliki banyak riwayat latihan harian (Daily Drills).
     */
    public function dailyDrills()
    {
        return $this->hasMany(DailyDrill::class, 'user_id');
    }
    
    /**
     * Relasi ke profil mentor (Hanya berisi data jika role = 'mentor')
     */
    public function mentorProfile()
    {
        // Pastikan nama relasinya sama persis: mentorProfile
        return $this->hasOne(MentorProfile::class, 'user_id', 'id');
    }
    
    /**
     * Review yang DIBERIKAN oleh user ini.
     */
    public function reviewsGiven()
    {
        return $this->hasMany(SessionReview::class, 'reviewer_id');
    }

    /**
     * Review yang DITERIMA oleh user ini (Berguna untuk nampilin ulasan di halaman profil Mentor).
     */
    public function reviewsReceived()
    {
        return $this->hasMany(SessionReview::class, 'reviewee_id');
    }
}