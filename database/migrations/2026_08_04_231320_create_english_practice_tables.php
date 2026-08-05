<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabel Ujian / Latihan Utama (IELTS, TOEFL, dsb)
        Schema::create('practice_exams', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Contoh: "IELTS Mock Test 1"
            $table->enum('type', ['ielts', 'toefl', 'other'])->default('other');
            $table->text('description')->nullable();
            $table->integer('duration_minutes')->comment('Durasi pengerjaan dalam menit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Tabel Soal Latihan (Mendukung Reading, Listening, dsb)
        Schema::create('practice_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_exam_id')->constrained('practice_exams')->onDelete('cascade');
            $table->enum('section', ['reading', 'listening', 'writing', 'speaking', 'structure'])->default('reading');
            
            // Untuk soal cerita/reading passage atau audio listening
            $table->text('context_text')->nullable()->comment('Teks panjang untuk Reading / Passage');
            $table->string('audio_url')->nullable()->comment('URL Audio untuk soal Listening');
            
            // Pertanyaan spesifik
            $table->text('question_text');
            $table->enum('question_type', ['multiple_choice', 'essay', 'fill_in_the_blank'])->default('multiple_choice');
            $table->integer('score_weight')->default(1)->comment('Bobot nilai soal ini');
            
            $table->timestamps();
        });

        // 3. Tabel Pilihan Ganda (Hanya dipakai jika question_type = multiple_choice)
        Schema::create('practice_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_question_id')->constrained('practice_questions')->onDelete('cascade');
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });

        // 4. Tabel Riwayat Pengerjaan (Tracking User Attempts)
        Schema::create('practice_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('practice_exam_id')->constrained('practice_exams')->onDelete('cascade');
            
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            
            $table->decimal('total_score', 8, 2)->nullable()->comment('Nilai akhir (Band score IELTS atau skor TOEFL)');
            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Hapus dari yang memiliki foreign key terdalam untuk menghindari constraint error
        Schema::dropIfExists('practice_attempts');
        Schema::dropIfExists('practice_options');
        Schema::dropIfExists('practice_questions');
        Schema::dropIfExists('practice_exams');
    }
};