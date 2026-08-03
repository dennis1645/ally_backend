<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'mentee_id',
        'task_description',
        'deadline',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'deadline'     => 'date',
            'is_completed' => 'boolean',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(ConsultationBooking::class, 'booking_id');
    }

    public function mentee()
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }
}