<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MentorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'university',
        'major',
        'degree_level',
        'scholarship_awardee',
        'destination_countries_expertise',
        'study_fields_expertise',
        'expertise_tags',
        'languages',
        'mentoring_style',
        'current_job',
        'years_of_experience',
        'linkedin_url',
        'max_active_mentees',
        'is_accepting_mentees',
        'rating'
    ];

    // CASTING PENTING UNTUK FITUR MATCHING AI
    protected $casts = [
        'destination_countries_expertise' => 'array',
        'study_fields_expertise' => 'array',
        'expertise_tags' => 'array',
        'languages' => 'array',
        'is_accepting_mentees' => 'boolean',
        'rating' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}