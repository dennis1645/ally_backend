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
        'token_cost', // Menggantikan transaction_detail_id
        'session_status',
        'meeting_link',
    ];

    protected function casts(): array
    {
        return [
            'token_cost' => 'integer',
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
}