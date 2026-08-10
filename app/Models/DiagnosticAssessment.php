<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_token',
        'assessment_type',
        'reason',
        // Data utama hasil AI
        'readiness_percentage',
        'readiness_level',
        
        // Breakdown skor kategori dari AI
        'academic_score',
        'scholarship_goal_score',
        'leadership_score',
        'achievements_score',
        'raw_answers', // Menyimpan jawaban mentah user dalam bentuk JSON
        'english_score',
        'application_score',
        
        // Array mapping AI
        'strengths_mapping',
        'improvements_mapping'
    ];

    // Mengubah data JSON di database secara otomatis menjadi Array di PHP
    protected $casts = [
        'strengths_mapping' => 'array',
        'improvements_mapping' => 'array',
    ];

    // Relasi ke user yang mengerjakan asesmen (Belongs To)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}