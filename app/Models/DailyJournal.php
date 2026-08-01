<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'reflection',
        'mood',
        'goals',
        'achievements',
        'challenges',
        'progress_notes',
        'blockers',
        'sentiment_status',
        'needs_intervention',
        'is_streak_counted',
        'xp_awarded',
    ];

    protected $casts = [
        'date' => 'date',
        'needs_intervention' => 'boolean',
        'is_streak_counted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}