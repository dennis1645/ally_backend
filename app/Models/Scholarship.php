<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Scholarship extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'provider_country',
        'description',
        'funding_type',
        'degree_level',
        'start_date',
        'eligibility_criteria',
        'application_process',
        'benefits',
        'official_website',
        'contact_email',
        'contact_phone',
        'application_link',
        'deadline_date',
        'status',
        'notes',
        'image_url',
    ];

    protected $casts = [
        'start_date' => 'date',
        'deadline_date' => 'date',
    ];

    // Relasi Many-to-Many ke University
    public function universities()
    {
        return $this->belongsToMany(University::class, 'scholarship_university');
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