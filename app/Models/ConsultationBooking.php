<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultationBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentee_id',
        'mentor_id',
        'availability_id',
        'token_cost', 
        'mentor_earned_fee', // <-- DITAMBAHKAN SESUAI MIGRATION
        'session_status',
        'meeting_link',
        'user_milestone_id'
    ];

    protected function casts(): array
    {
        return [
            'token_cost' => 'integer',
            'mentor_earned_fee' => 'decimal:2', // <-- DITAMBAHKAN FORMAT DESIMAL
        ];
    }

    public function mentee()
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function availability()
    {
        return $this->belongsTo(MentorAvailability::class, 'availability_id');
    }

    public function actionPlans()
    {
        return $this->hasMany(ActionPlan::class, 'booking_id');
    }
    
    public function reviews()
    {
        return $this->hasMany(SessionReview::class, 'booking_id');
    }
}