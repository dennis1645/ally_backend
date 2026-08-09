<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ticket_number')->unique()->comment('Nomor tiket unik (cth: TIX-12345)');
            
            // Kategori pengaduan (termasuk kendala Midtrans)
            $table->enum('category', [
                'payment_issue',    // Kendala sinkronisasi / pembayaran Midtrans
                'bug_report',       // Laporan error/bug
                'feature_request',  // Permintaan fitur baru
                'general_inquiry'   // Pertanyaan umum
            ])->default('general_inquiry');
            
            $table->string('subject');
            $table->text('message');
            $table->text('admin_reply')->nullable()->comment('Catatan atau balasan terakhir admin');
            
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};