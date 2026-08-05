<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Utama untuk Sesi Drill Harian
        Schema::create('daily_drills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->date('drill_date');
            $table->integer('total_questions')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->decimal('total_score', 5, 2)->default(0);
            
            // Gamifikasi: Tambahan XP jika user rajin ngerjain
            $table->integer('xp_earned')->default(0);
            
            // Log Feedback dari User
            $table->enum('difficulty_feedback', ['too_easy', 'good', 'too_hard'])->nullable();
            $table->text('feedback_note')->nullable(); // Untuk deskripsi tambahan jika perlu
            
            $table->timestamps();
        });

        // Tabel Detail untuk mencatat soal apa saja yang dikerjakan & jawaban user
        Schema::create('daily_drill_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_drill_id')->constrained('daily_drills')->cascadeOnDelete();
            
            // Ambil soal dari bank soal yang sudah ada
            $table->foreignId('practice_question_id')->constrained('practice_questions')->cascadeOnDelete();
            
            // Jawaban yang dipilih user
            $table->foreignId('selected_option_id')->nullable()->constrained('practice_options')->nullOnDelete();
            
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_drill_answers');
        Schema::dropIfExists('daily_drills');
    }
};