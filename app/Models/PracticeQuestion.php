<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticeQuestion extends Model
{
    use HasFactory;

    protected $table = 'practice_questions';

    protected $fillable = [
        'practice_exam_id',
        'section',
        'context_text',
        'audio_url',
        'question_text',
        'question_type',
        'score_weight',
    ];

    /**
     * Relasi: Pertanyaan ini milik satu ujian tertentu.
     */
    public function exam()
    {
        return $this->belongsTo(PracticeExam::class, 'practice_exam_id');
    }

    /**
     * Relasi: Satu pertanyaan pilihan ganda memiliki banyak opsi jawaban.
     */
    public function options()
    {
        return $this->hasMany(PracticeOption::class, 'practice_question_id');
    }
}   