<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Pertanyaan Asesmen
        Schema::create('diagnostic_questions', function (Blueprint $table) {
            $table->id();
            
            // Penanda jenis asesmen ('onboarding' atau 'initial_diagnostic')
            $table->string('assessment_type')->default('initial_diagnostic'); 
            
            $table->text('question_text');
            
            // Kategori soal (academic, scholarship_goal, leadership, achievements, english, application)
            $table->string('category'); 
            
            $table->boolean('is_active')->default(true);
            $table->integer('order_number')->default(0); 
            $table->timestamps();
        });

        // Tabel Pilihan Jawaban (Opsi)
        Schema::create('diagnostic_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_question_id')->constrained()->cascadeOnDelete();
            
            $table->string('option_text');
            
            // Bobot dan tag masih bisa dipertahankan sebagai konteks tambahan untuk dikirim ke Prompt AI
            $table->integer('score_weight')->default(0); 
            $table->string('weakness_tag')->nullable(); 
            $table->string('strength_tag')->nullable(); 
            
            $table->timestamps();
        });

        // Tabel Hasil Asesmen User (UPDATED FOR AI INTEGRATION)
        Schema::create('diagnostic_assessments', function (Blueprint $table) {
            $table->id();
            
            // Relasi User / Guest
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_token')->nullable()->index(); 
            $table->string('assessment_type')->default('initial_diagnostic'); 

            $table->json('raw_answers')->nullable()->comment('Menyimpan jawaban mentah user dalam format key-value');
            
            // ---------------------------------------------------
            // DATA HASIL GENERATE AI GEMINI
            // ---------------------------------------------------
            
            // Skor Keseluruhan & Level
            $table->integer('readiness_percentage')->default(0); 
            $table->string('readiness_level')->nullable(); // cth: "Strong Foundation"
            
            // Breakdown skor kategori (Sesuai objek JSON "categories" dari AI)
            $table->integer('academic_score')->default(0);            // Untuk "academic"
            $table->integer('scholarship_goal_score')->default(0);    // Untuk "scholarship_goal"
            $table->integer('leadership_score')->default(0);          // Untuk "leadership"
            $table->integer('achievements_score')->default(0);        // Untuk "achievements"
            $table->integer('english_score')->default(0);             // Untuk "english"
            $table->integer('application_score')->default(0);         // Untuk "application"
            
            // Array output AI untuk kekuatan dan area improvisasi
            $table->json('strengths_mapping')->nullable();            // Untuk "strengths"
            $table->json('improvements_mapping')->nullable();         // Untuk "improvements"
            $table->text('reason')->nullable(); 
            
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