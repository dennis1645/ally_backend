<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_text',
        'category',
        'is_active',
        'order_number'
    ];

    // Relasi ke pilihan jawaban (One to Many)
    public function options()
    {
        return $this->hasMany(DiagnosticOption::class, 'diagnostic_question_id');
    }
}