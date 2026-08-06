<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number', 20)->nullable()->unique(); 
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('gender', ['male', 'female'])->nullable();
            
            // Atribut Khusus Sistem Beasiswa
            $table->enum('role', ['user', 'mentor', 'admin'])->default('user');
            
            $table->foreignId('assigned_mentor_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Mentor tetap yang di-assign ke mentee ini');

            $table->boolean('is_premium')->default(false); 
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->integer('readiness_score')->nullable(); 
            
            // ==========================================
            // AKADEMIK & TARGET (Task 1.5 Milestone 2)
            // ==========================================
            $table->decimal('gpa', 3, 2)->nullable(); // IPK, format misal: 3.85
            $table->string('undergraduate_major')->nullable(); // Jurusan S1
            $table->string('target_major')->nullable(); // Target Jurusan S2
            $table->string('primary_scholarship_target')->nullable(); // 1 Primary Target Beasiswa
            
            // Saldo token untuk booking mentor (Tidak akan reset saat ganti target)
            $table->integer('token_balance')->default(0);
            
            // Batas waktu masa aktif premium 12 bulan (Tidak akan reset saat ganti target)
            $table->timestamp('premium_until')->nullable();
            
            // Profil & Portofolio
            $table->string('profile_picture_url')->nullable(); 
            $table->string('headline')->nullable(); 
            $table->text('bio')->nullable(); 
            
            // Integrasi OAuth
            $table->string('google_id')->nullable()->unique(); 
            $table->string('linkedin_id')->nullable()->unique(); 
            
            // Sistem Gamifikasi & Retensi User
            $table->integer('xp_points')->default(0); 
            // Level tidak dimasukkan ke DB, melainkan di-generate dinamis di Model via Accessor!
            $table->integer('current_streak')->default(0); 
            $table->boolean('is_streak_frozen')->default(false); // Dihapus ->after()-nya
            $table->integer('longest_streak')->default(0); 
            
            
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); 
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};