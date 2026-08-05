<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticeOption extends Model
{
    use HasFactory;

    protected $table = 'practice_options';

    protected $fillable = [
        'practice_question_id',
        'option_text',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    /**
     * Relasi: Opsi jawaban ini milik satu pertanyaan tertentu.
     */
    public function question()
    {
        return $this->belongsTo(PracticeQuestion::class, 'practice_question_id');
    }
}