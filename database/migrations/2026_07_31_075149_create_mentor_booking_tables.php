<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ==========================================
        // 1. GAMIFICATION SYSTEM
        // ==========================================
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name'); 
            $table->text('description')->nullable();
            $table->string('icon_url')->nullable();
            $table->integer('required_xp')->default(0);
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained('badges')->cascadeOnDelete();
            $table->timestamp('earned_at')->useCurrent();
        });

        // ==========================================
        // 2. SHOP & BUNDLE ITEMS (SUBSCRIPTION & TOKEN PACKAGES)
        // ==========================================
        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->string('name'); 
            // Tipe item diubah untuk mendukung Langganan & Top-Up Token
            $table->enum('item_type', ['subscription', 'token_package', 'practice_bundle', 'gamification_item', 'other']); 
            $table->text('description')->nullable();
            
            $table->decimal('price_rupiah', 12, 2)->default(0); 
            $table->integer('price_xp')->default(0); // Jika bisa dibeli pakai XP
            
            // PENAMBAHAN UNTUK SISTEM TOKEN & LANGGANAN
            $table->integer('token_reward')->default(0)->comment('Jumlah token mentor yang didapat (misal: 3)');
            $table->integer('duration_days')->nullable()->comment('Durasi premium dalam hari (misal: 365 untuk 1 tahun)');
            
            $table->integer('stock_quantity')->default(0); 
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ==========================================
        // 3. MASTER TRANSACTIONS (MIDTRANS CENTRAL)
        // ==========================================
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('midtrans_order_id')->unique();
            
            // Tipe transaksi difokuskan pada pembelian dari shop (Langganan / Top Up)
            $table->enum('transaction_type', ['subscription', 'token_topup', 'shop_purchase'])->default('shop_purchase');
            
            $table->decimal('gross_amount', 12, 2);
            $table->enum('payment_status', ['pending', 'success', 'expired', 'failed'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_url')->nullable(); 
            $table->timestamps();
        });

        // ==========================================
        // 4. TRANSACTION DETAILS 
        // ==========================================
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('shop_item_id')->nullable()->constrained('shop_items')->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });

        // ==========================================
        // 5. MENTORING SYSTEM
        // ==========================================
        Schema::create('mentor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->date('available_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_booked')->default(false);
            $table->timestamps();
        });

        Schema::create('consultation_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('availability_id')->constrained('mentor_availabilities')->cascadeOnDelete();
            
            // Transaksi Midtrans dilepas, diganti dengan Token Cost
            $table->integer('token_cost')->default(1)->comment('Berapa token yang dihabiskan untuk sesi ini');
            
            $table->enum('session_status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->string('meeting_link')->nullable(); 
            $table->timestamps();
        });

        Schema::create('action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('consultation_bookings')->cascadeOnDelete();
            $table->foreignId('mentee_id')->constrained('users')->cascadeOnDelete();
            $table->string('task_description');
            $table->date('deadline')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_plans');
        Schema::dropIfExists('consultation_bookings');
        Schema::dropIfExists('mentor_availabilities');
        Schema::dropIfExists('transaction_details');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('shop_items');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
    }
};