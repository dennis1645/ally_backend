<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. TABEL PERTANYAAN ASESMEN
        Schema::create('diagnostic_questions', function (Blueprint $table) {
            $table->id();
            
            // Menggunakan tipe 'text' karena beberapa pertanyaan memiliki contoh panjang (seperti Q7, Q8, Q9)
            $table->text('question_text');
            
            // Kategori internal untuk sistem memecah skor (academic, goals, leadership, language, readiness)
            $table->string('category'); 
            
            $table->boolean('is_active')->default(true);
            
            // order_number digunakan untuk mengurutkan pertanyaan 1 s/d 20 sebelum di-paginate
            $table->integer('order_number')->default(0); 
            $table->timestamps();
        });

        // 2. TABEL PILIHAN JAWABAN (OPSI)
        Schema::create('diagnostic_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_question_id')->constrained()->cascadeOnDelete();
            
            $table->string('option_text');
            $table->integer('score_weight')->default(0); // Bobot nilai jika user memilih jawaban ini
            
            // Tag untuk mapping kelemahan/kekuatan otomatis oleh Rule-based scoring AI
            $table->string('weakness_tag')->nullable(); 
            $table->string('strength_tag')->nullable(); 
            
            $table->timestamps();
        });

        // 3. TABEL HASIL ASESMEN USER
        Schema::create('diagnostic_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->integer('overall_score')->default(0); 
            
            // ==========================================
            // BREAKDOWN SKOR BERDASARKAN KATEGORI SOAL
            // ==========================================
            $table->integer('academic_score')->default(0);                  // Soal Pendidikan & IPK
            $table->integer('goals_score')->default(0);                     // Soal Tujuan Beasiswa
            $table->integer('leadership_experience_score')->default(0);     // Soal Organisasi & Prestasi
            $table->integer('language_score')->default(0);                  // Soal Bahasa Inggris
            $table->integer('application_readiness_score')->default(0);     // Soal Kesiapan Dokumen (CV/Essay) & Tantangan
            
            // ==========================================
            // AI & RULE-BASED MAPPING OUTPUT
            // ==========================================
            $table->json('weaknesses_mapping')->nullable(); 
            $table->json('strengths_mapping')->nullable();  
            $table->text('system_recommendation')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Urutan drop dibalik agar tidak error foreign key constraint
        Schema::dropIfExists('diagnostic_assessments');
        Schema::dropIfExists('diagnostic_options');
        Schema::dropIfExists('diagnostic_questions');
    }
};