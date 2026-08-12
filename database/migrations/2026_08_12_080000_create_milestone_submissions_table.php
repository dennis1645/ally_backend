<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestone_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_milestone_id')->constrained('user_milestones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_vault_id')->nullable()->constrained('document_vaults')->nullOnDelete();
            
            $table->enum('submission_type', ['text', 'document', 'both'])->default('text');
            $table->text('text_response')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            
            $table->enum('review_status', ['pending', 'approved', 'revision_requested'])->default('pending');
            $table->text('mentor_feedback')->nullable();
            $table->integer('rating')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->integer('xp_awarded')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestone_submissions');
    }
};
