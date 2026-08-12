<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssayAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_milestone_id',
        'essay_type',
        'title',
        'original_filename',
        'file_path',
        'essay_text',
        'score',
        'overall_score',
        'categories',
        'strengths',
        'weaknesses',
        'recommendations',
        'raw_ai_response',
        'token_cost',
    ];

    protected $casts = [
        'categories'       => 'array',
        'strengths'        => 'array',
        'weaknesses'       => 'array',
        'recommendations'  => 'array',
        'raw_ai_response'  => 'array',
        'score'            => 'integer',
        'overall_score'    => 'integer',
        'token_cost'       => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userMilestone()
    {
        return $this->belongsTo(UserMilestone::class, 'user_milestone_id');
    }
}
