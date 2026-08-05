<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyDrillAnswer extends Model
{
    use HasFactory;

    protected $table = 'daily_drill_answers';

    protected $fillable = [
        'daily_drill_id',
        'practice_question_id',
        'selected_option_id',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function dailyDrill()
    {
        return $this->belongsTo(DailyDrill::class, 'daily_drill_id');
    }

    public function question()
    {
        return $this->belongsTo(PracticeQuestion::class, 'practice_question_id');
    }

    public function selectedOption()
    {
        return $this->belongsTo(PracticeOption::class, 'selected_option_id');
    }
}