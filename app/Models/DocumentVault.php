<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentVault extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'scholarship_id',
        'university_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'file_type',
        'status',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scholarship()
    {
        return $this->belongsTo(Scholarship::class);
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }
}