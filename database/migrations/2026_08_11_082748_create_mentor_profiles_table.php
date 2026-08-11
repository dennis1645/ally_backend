<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_profiles', function (Blueprint $table) {
            $table->id();
            
            // Relasi 1-to-1 ke tabel users
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            
            // ==========================================
            // 1. LATAR BELAKANG AKADEMIK (Hard Skills Matching)
            // ==========================================
            $table->string('university'); // cth: Oxford University
            $table->string('major'); // cth: MSc in Computer Science
            $table->string('degree_level')->default('Master'); // cth: Master, PhD (Cocokkan dengan target mentee)
            $table->string('scholarship_awardee'); // cth: Chevening Scholar 2023
            
            // JSON Array agar AI bisa mencocokkan lebih dari 1 negara/bidang
            $table->json('destination_countries_expertise')->nullable(); // cth: ["United Kingdom", "Europe"] -> Match dengan q12_target_countries mentee
            $table->json('study_fields_expertise')->nullable(); // cth: ["Computer Science", "STEM"] -> Match dengan target_major mentee
            
            // ==========================================
            // 2. PENDEKATAN MENTORING (Soft Skills Matching)
            // ==========================================
            $table->json('expertise_tags')->nullable(); // cth: ["Essay Review", "Mock Interview", "Motivation Letter"] -> Match dengan q20_support_needed
            $table->json('languages')->nullable(); // cth: ["Indonesian", "English"]
            $table->string('mentoring_style')->nullable(); // cth: "Supportive", "Strict", "Analytical" -> AI bisa mencocokkan dengan kepribadian/kebutuhan mentee
            
            // ==========================================
            // 3. KREDIBILITAS & MANAJEMEN
            // ==========================================
            $table->string('current_job')->nullable(); // Pekerjaan saat ini
            $table->integer('years_of_experience')->default(1);
            $table->string('linkedin_url')->nullable(); // Validasi portofolio
            
            $table->integer('max_active_mentees')->default(5); // Kapasitas maksimal mentee yang bisa dipegang bersamaan
            $table->boolean('is_accepting_mentees')->default(true); // Status apakah mentor sedang open slot
            $table->decimal('rating', 3, 2)->default(0.00); // Rating rata-rata (cth: 4.95) untuk prioritas rekomendasi AI
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_profiles');
    }
};