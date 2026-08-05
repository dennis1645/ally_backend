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
    'is_premium',
    'assigned_mentor_id',
    
    // ==========================================
    // Atribut Akademik & Target Baru (Task 1.5)
    // ==========================================
    'gpa', 
    'undergraduate_major', 
    'target_major', 
    'primary_scholarship_target'
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
    protected $appends = ['level'];

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
            'is_premium' => 'boolean',
            'gpa' => 'decimal:2', // Format angka desimal untuk IPK
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

    // ==========================================
    // RELASI SEBAGAI MENTEE / USER BIASA
    // ==========================================

    public function milestones()
    {
        return $this->hasMany(UserMilestone::class);
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
}