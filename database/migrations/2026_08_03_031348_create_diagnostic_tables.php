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
            $table->string('question_text');
            // Kategori untuk memecah skor: academic, language, experience, document
            $table->string('category'); 
            $table->boolean('is_active')->default(true);
            $table->integer('order_number')->default(0); 
            $table->timestamps();
        });

        // 2. TABEL PILIHAN JAWABAN (OPSI)
        Schema::create('diagnostic_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_question_id')->constrained()->cascadeOnDelete();
            $table->string('option_text');
            $table->integer('score_weight'); // Bobot nilai jika user memilih jawaban ini (misal: 0, 5, 10)
            
            // Tag untuk mapping kelemahan/kekuatan otomatis
            $table->string('weakness_tag')->nullable(); // Contoh: "no_ielts", "low_gpa"
            $table->string('strength_tag')->nullable(); // Contoh: "leadership_exp", "high_gpa"
            
            $table->timestamps();
        });

        // 3. TABEL HASIL ASESMEN USER
        Schema::create('diagnostic_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->integer('overall_score'); 
            
            // Breakdown skor per kategori
            $table->integer('academic_score')->default(0);       
            $table->integer('language_score')->default(0);       
            $table->integer('experience_score')->default(0);     
            $table->integer('document_score')->default(0);       
            
            // Pemetaan hasil berupa array JSON
            $table->json('weaknesses_mapping')->nullable(); 
            $table->json('strengths_mapping')->nullable();  
            $table->text('system_recommendation')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Urutan drop dibalik agar tidak error foreign key
        Schema::dropIfExists('diagnostic_assessments');
        Schema::dropIfExists('diagnostic_options');
        Schema::dropIfExists('diagnostic_questions');
    }
};