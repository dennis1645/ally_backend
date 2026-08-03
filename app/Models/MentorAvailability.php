<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MentorAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentor_id',
        'available_date',
        'start_time',
        'end_time',
        'is_booked',
    ];

    protected function casts(): array
    {
        return [
            'available_date' => 'date',
            'is_booked'      => 'boolean',
        ];
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function booking()
    {
        return $this->hasOne(ConsultationBooking::class, 'availability_id');
    }
}