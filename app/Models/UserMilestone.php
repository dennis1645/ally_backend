<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'scholarship_id',
        'university_id',
        'task_name',
        'description',
        'step_order',     // Ditambahkan
        'is_premium',     // Ditambahkan
        'target_deadline',
        'status',
        'completed_at',
        'source',
        'is_mandatory',
        'xp_reward',
    ];

    protected $casts = [
        'is_premium' => 'boolean', // Casting boolean
        'is_mandatory' => 'boolean',
        'target_deadline' => 'date',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scholarship()
    {
        return $this->belongsTo(Scholarship::class);
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }
}