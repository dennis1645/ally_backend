<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'diagnostic_question_id',
        'option_text',
        'score_weight',
        'weakness_tag',
        'strength_tag'
    ];

    // Relasi kembali ke pertanyaan (Belongs To)
    public function question()
    {
        return $this->belongsTo(DiagnosticQuestion::class, 'diagnostic_question_id');
    }
}