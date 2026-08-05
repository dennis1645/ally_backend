<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyDrill extends Model
{
    use HasFactory;

    protected $table = 'daily_drills';

    protected $fillable = [
        'user_id',
        'drill_date',
        'total_questions',
        'correct_answers',
        'total_score',
        'xp_earned',
        'difficulty_feedback',
        'feedback_note',
    ];

    protected $casts = [
        'drill_date' => 'date',
        'total_score' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function answers()
    {
        return $this->hasMany(DailyDrillAnswer::class, 'daily_drill_id');
    }
}