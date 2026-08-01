<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ==========================================
        // 1. KATALOG KAMPUS (UNIVERSITIES)
        // ==========================================
        Schema::create('universities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country');
            $table->string('city')->nullable();
            $table->text('description')->nullable();
            
            $table->text('admission_process')->nullable(); 
            $table->text('admission_requirements')->nullable();
            
            $table->string('official_website')->nullable();
            $table->string('image_url')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // ==========================================
        // 2. KATALOG BEASISWA
        // ==========================================
        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider_country')->nullable();
            $table->text('description')->nullable();
            
            $table->enum('funding_type', ['fully_funded', 'partial_funded', 'self_funded'])->default('fully_funded');
            $table->enum('degree_level', ['bachelor', 'master', 'phd', 'non_degree'])->default('master');
            
            $table->date('start_date')->nullable();
            $table->text('eligibility_criteria')->nullable();
            $table->text('application_process')->nullable();
            $table->text('benefits')->nullable();
            $table->string('official_website')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('application_link')->nullable();
            $table->date('deadline_date')->nullable();
            
            $table->enum('status', ['draft', 'published'])->default('published');
            $table->text('notes')->nullable();
            $table->text('image_url')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // ==========================================
        // 3. PIVOT TABLE: RELASI BEASISWA & KAMPUS 
        // ==========================================
        Schema::create('scholarship_university', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
            $table->timestamps();
        });

        // ==========================================
        // 4. REQUIREMENTS CHECKLIST & DYNAMIC TIMELINE
        // ==========================================
        Schema::create('user_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->foreignId('scholarship_id')->nullable()->constrained()->cascadeOnDelete(); 
            $table->foreignId('university_id')->nullable()->constrained('universities')->cascadeOnDelete();
            
            $table->string('task_name');
            $table->text('description')->nullable();
            
            // PENAMBAHAN UNTUK FITUR FREEMIUM PM-MU
            $table->integer('step_order')->default(1); // Urutan milestone (1, 2, 3 gratis)
            $table->boolean('is_premium')->default(false); // Penanda apakah task ini dikunci (butuh bayar)

            $table->date('target_deadline');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->timestamp('completed_at')->nullable(); 
            
            $table->enum('source', ['system', 'mentor', 'user'])->default('system');
            $table->boolean('is_mandatory')->default(true);
            
            $table->integer('xp_reward')->default(0); 
            
            $table->timestamps();
        });

        // ==========================================
        // 5. DOCUMENT VAULT (BRANKAS PENYIMPANAN)
        // ==========================================
        Schema::create('document_vaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->foreignId('scholarship_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('university_id')->nullable()->constrained('universities')->cascadeOnDelete();
            
            $table->string('file_name'); 
            $table->string('file_path'); 
            $table->string('mime_type')->nullable(); 
            $table->integer('file_size')->nullable(); 
            $table->enum('file_type', ['cv', 'transcript', 'certificate', 'essay', 'loa', 'other']); 
            
            $table->enum('status', ['uploaded', 'ai_reviewed', 'mentor_reviewed', 'final'])->default('uploaded');
            
            $table->boolean('is_encrypted')->default(true); 
            
            $table->timestamps();
            $table->softDeletes(); 
        });

        // ==========================================
        // 6. DAILY CHECK-IN & JOURNALING
        // ==========================================
        Schema::create('daily_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('reflection')->nullable();
            $table->text('mood')->nullable(); 
            $table->text('goals')->nullable(); 
            $table->text('achievements')->nullable(); 
            $table->text('challenges')->nullable(); 
            $table->text('progress_notes')->nullable();
            $table->text('blockers')->nullable();
            
            $table->string('sentiment_status')->nullable(); 
            $table->boolean('needs_intervention')->default(false); 
            
            $table->boolean('is_streak_counted')->default(false);
            $table->integer('xp_awarded')->default(0); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_journals');
        Schema::dropIfExists('document_vaults');
        Schema::dropIfExists('user_milestones');
        Schema::dropIfExists('scholarship_university');
        Schema::dropIfExists('scholarships');
        Schema::dropIfExists('universities');
    }
};