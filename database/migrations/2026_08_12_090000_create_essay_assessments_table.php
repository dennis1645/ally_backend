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
        Schema::create('essay_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_milestone_id')->nullable()->constrained('user_milestones')->nullOnDelete();
            
            // 6 Jenis Asesmen Esai: storytelling, motivation, leadership, impact, scholarship_alignment, clarity, atau general
            $table->string('essay_type')->default('general');
            $table->string('title')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('file_path')->nullable();
            $table->longText('essay_text')->nullable();
            
            // Hasil Penilaian AI
            $table->integer('overall_score')->default(0);
            $table->json('categories')->nullable();
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('raw_ai_response')->nullable();
            
            $table->integer('token_cost')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('essay_assessments');
    }
};
