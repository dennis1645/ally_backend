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
    'profile_picture_url', 
    'headline', 
    'bio', 
    'google_id', 
    'linkedin_id', 
    'xp_points', 
    'current_streak', 
    'longest_streak',
    'is_premium',
])]
#[Hidden([
    'password', 
    'remember_token',
    'google_id',  // Best practice: Menyembunyikan ID OAuth dari response JSON API
    'linkedin_id' 
])]
// PERBAIKAN: Menambahkan "implements MustVerifyEmail" di sini
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

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
            'xp_points' => 'integer',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'is_premium' => 'boolean',
        ];
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
        return $this->belongsToMany(Badge::class, 'user_badges')->withPivot('earned_at');
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

    // Override untuk email verifikasi
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\CustomVerifyEmail);
    }

    // Override untuk email reset password
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\CustomResetPassword($token));
    }
}