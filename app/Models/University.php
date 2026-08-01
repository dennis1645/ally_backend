<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class University extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'country',
        'city',
        'description',
        'admission_process',
        'admission_requirements',
        'official_website',
        'image_url',
    ];

    // Relasi Many-to-Many ke Scholarship
    public function scholarships()
    {
        return $this->belongsToMany(Scholarship::class, 'scholarship_university');
    }

    public function userMilestones()
    {
        return $this->hasMany(UserMilestone::class);
    }

    public function documentVaults()
    {
        return $this->hasMany(DocumentVault::class);
    }
}