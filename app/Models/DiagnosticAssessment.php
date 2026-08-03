<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'overall_score',
        'academic_score',
        'language_score',
        'experience_score',
        'document_score',
        'weaknesses_mapping',
        'strengths_mapping',
        'system_recommendation'
    ];

    // Mengubah data JSON secara otomatis menjadi Array di PHP
    protected $casts = [
        'weaknesses_mapping' => 'array',
        'strengths_mapping' => 'array',
    ];

    // Relasi ke user yang mengerjakan asesmen (Belongs To)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}