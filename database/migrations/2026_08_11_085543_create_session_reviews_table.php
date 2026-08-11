<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_reviews', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke sesi konsultasi
            $table->foreignId('booking_id')->constrained('consultation_bookings')->cascadeOnDelete();
            
            // Siapa yang memberikan review? (Bisa Mentee, bisa Mentor)
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            
            // Siapa yang dinilai?
            $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();
            
            // Nilai 1 sampai 5
            $table->tinyInteger('rating')->default(5)->comment('Bintang 1-5');
            
            // Ulasan teks
            $table->text('feedback')->nullable();
            
            $table->timestamps();

            // Aturan unik: 1 orang hanya boleh review 1 kali untuk sesi yang sama
            $table->unique(['booking_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_reviews');
    }
};