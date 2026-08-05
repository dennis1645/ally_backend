<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // morphs akan otomatis membuat 2 kolom: bookmarkable_type (string) dan bookmarkable_id (bigint)
            $table->morphs('bookmarkable'); 
            
            $table->timestamps();

            // Mencegah user mem-bookmark hal yang sama berkali-kali
            $table->unique(['user_id', 'bookmarkable_id', 'bookmarkable_type'], 'user_bookmark_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};