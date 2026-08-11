<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Midtrans\Config;
use Midtrans\Snap;

class PaymentController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-zPZzeYWIuU8ckXT3gwASJ31c');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * MEMBUAT TRANSAKSI PEMBAYARAN MIDTRANS UNTUK UPGRADE AKUN PREMIUM
     */
    public function upgradeToPremium(Request $request)
    {
        $user = Auth::user();

        if ($user->is_premium) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Anda sudah berstatus Premium.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $orderId = 'PREMIUM-UPGRADE-' . time() . '-' . rand(100, 999);
            $grossAmount = 150000; // Harga upgrade akun premium

            // 1. Simpan ke Master Transactions
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'midtrans_order_id' => $orderId,
                'transaction_type' => 'premium_unlock',
                'gross_amount' => $grossAmount,
                'payment_status' => 'pending',
            ]);

            // 2. Simpan ke Transaction Details
            TransactionDetail::create([
                'transaction_id' => $transaction->id,
                'shop_item_id' => null,
                'price' => $grossAmount,
            ]);

            // ========================================================
            // PERBAIKAN: Arahkan callback ke route proxy Laravel kita
            // menggunakan url() agar otomatis mengikuti domain Ngrok
            // ========================================================
            $proxyUrl = url('/api/payment/return');

            // 3. Request Snap Token / Payment URL ke Midtrans API
            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int) $grossAmount,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone_number ?? '08123456789',
                ],
                'item_details' => [
                    [
                        'id' => 'PREMIUM-ACC',
                        'price' => (int) $grossAmount,
                        'quantity' => 1,
                        'name' => 'Upgrade Akun Premium & Unlock Semua Fitur Eksklusif',
                    ]
                ],
                // Set Callback Redirect ke Proxy Laravel
                'callbacks' => [
                    'finish' => $proxyUrl,
                    'unfinish' => $proxyUrl,
                    'error' => $proxyUrl,
                ]
            ];

            $paymentUrl = Snap::createTransaction($params)->redirect_url;

            $transaction->update([
                'payment_url' => $paymentUrl
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Link pembayaran berhasil dibuat. Silakan selesaikan pembayaran.',
                'data' => [
                    'order_id' => $orderId,
                    'gross_amount' => $grossAmount,
                    'payment_status' => 'pending',
                    'payment_url' => $paymentUrl
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses pembayaran.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * MIDTRANS WEBHOOK CALLBACK HANDLER
     * Mengaktifkan status is_premium = true secara otomatis saat pembayaran sukses
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? 'accept';
        $paymentType = $payload['payment_type'] ?? null;

        if (!$orderId) {
            return response()->json(['status' => 'error', 'message' => 'Order ID tidak ditemukan.'], 400);
        }

        $transaction = Transaction::where('midtrans_order_id', $orderId)->first();

        if (!$transaction) {
            return response()->json(['status' => 'error', 'message' => 'Transaksi tidak terdaftar.'], 404);
        }

        DB::beginTransaction();
        try {
            if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
                if ($fraudStatus != 'challenge') {
                    $transaction->update(['payment_status' => 'success', 'payment_method' => $paymentType]);

                    // Jika tipe transaksinya adalah premium_unlock, jadikan user sebagai premium
                    if ($transaction->transaction_type === 'premium_unlock') {
                        $user = $transaction->user;
                        if ($user) {
                            $user->is_premium = true;
                            $user->save();
                        }
                    }
                }
            } elseif ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
                $transaction->update(['payment_status' => 'expired', 'payment_method' => $paymentType]);
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Webhook handled successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Webhook Exception: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PROXY REDIRECT: Menangkap redirect dari Midtrans lalu melempar paksa ke Frontend (localhost)
     */
    public function paymentReturn(Request $request)
    {
        // Midtrans otomatis mengirimkan parameter ini via URL
        $orderId = $request->query('order_id');
        $transactionStatus = $request->query('transaction_status', 'pending');

        // Tarik URL frontend dari .env (http://localhost:5173/checkout)
        $redirectUrl = env('REDIRECT_URL', 'http://localhost:5173/checkout');

        // Mapping status agar lebih rapi untuk dibaca frontend
        $status = 'pending';
        if (in_array($transactionStatus, ['capture', 'settlement'])) {
            $status = 'success';
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $status = 'error';
        }

        // Paksa browser melempar user ke Localhost Frontend!
        return redirect()->away($redirectUrl . '?status=' . $status . '&order_id=' . $orderId);
    }
}