<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ShopItem;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Midtrans\Config;
use Midtrans\Snap;

class ShopController extends Controller
{
    public function __construct()
    {
        // Konfigurasi Midtrans
        // Menambahkan env() sebagai fallback jika config/midtrans.php belum terbaca
        Config::$serverKey = config('midtrans.server_key') ?? env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = config('midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Menampilkan daftar item yang tersedia di Shop
     */
    public function index()
    {
        $items = ShopItem::where('is_active', true)->get();

        return response()->json([
            'status' => 'success',
            'data' => $items
        ]);
    }

    /**
     * Memproses checkout dan generate Midtrans Snap Token & Redirect URL
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'shop_item_id' => 'required|exists:shop_items,id'
        ]);

        $user = Auth::user();
        $item = ShopItem::findOrFail($request->shop_item_id);

        // Pengecekan menggunakan enum 'token_package' dari migration baru
        if ($item->item_type === 'token_package' && !$user->is_premium) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda harus berlangganan Premium (1 Tahun) terlebih dahulu untuk dapat membeli Token Mentor.'
            ], 403);
        }

        // Pengecekan menggunakan enum 'subscription' dari migration baru
        if ($item->item_type === 'subscription' && $user->is_premium) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah memiliki langganan Premium yang aktif.'
            ], 400); 
        }

        DB::beginTransaction();
        try {
            $orderId = 'TRX-' . time() . '-' . $user->id;
            
            // Perbaikan nama kolom: midtrans_order_id dan gross_amount menggunakan price_rupiah
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'midtrans_order_id' => $orderId,
                'transaction_type' => 'shop_purchase',
                'gross_amount' => $item->price_rupiah,
                'payment_status' => 'pending', 
            ]);

            // Perbaikan kolom price menggunakan price_rupiah dan menghapus quantity (tidak ada di migration)
            TransactionDetail::create([
                'transaction_id' => $transaction->id,
                'shop_item_id' => $item->id,
                'price' => $item->price_rupiah,
            ]);

            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int) $item->price_rupiah,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
                'item_details' => [
                    [
                        'id' => (string) $item->id,
                        'price' => (int) $item->price_rupiah,
                        'quantity' => 1,
                        'name' => $item->name,
                    ]
                ]
            ];

            // --- PERBAIKAN DI SINI ---
            // Menggunakan createTransaction() untuk mendapatkan object response lengkap (token & redirect url)
            $snapResponse = Snap::createTransaction($params);
            
            $snapToken = $snapResponse->token;
            $redirectUrl = $snapResponse->redirect_url;
            
            // Opsional: Simpan snap_token jika kolomnya ada di database
            // $transaction->update(['snap_token' => $snapToken]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil membuat transaksi',
                'data' => [
                    'order_id' => $transaction->midtrans_order_id,
                    'snap_token' => $snapToken,
                    'redirect_url' => $redirectUrl, // Link Snap Midtrans ditambahkan ke response
                    'item' => $item->name,
                    'total' => $transaction->gross_amount
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook / Callback Midtrans untuk update status & tambah token otomatis
     */
    public function webhook(Request $request)
    {
        // Tambahkan env() sebagai fallback di webhook juga
        $serverKey = config('midtrans.server_key') ?? env('MIDTRANS_SERVER_KEY');
        $hashed = hash("sha512", $request->order_id . $request->status_code . $request->gross_amount . $serverKey);

        if ($hashed !== $request->signature_key) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // Perbaikan pencarian menggunakan kolom midtrans_order_id
        $transaction = Transaction::where('midtrans_order_id', $request->order_id)->first();
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($transaction->payment_status !== 'pending') {
            return response()->json(['message' => 'Transaction already processed']);
        }

        $transactionStatus = $request->transaction_status;

        DB::beginTransaction();
        try {
            if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
                $transaction->update(['payment_status' => 'success']);

                $detail = TransactionDetail::where('transaction_id', $transaction->id)->first();
                $item = ShopItem::find($detail->shop_item_id);
                $user = User::find($transaction->user_id);

                if ($item && $user) {
                    // Perbaikan enum menjadi 'subscription'
                    if ($item->item_type === 'subscription') {
                        $user->is_premium = true;
                        $user->premium_until = now()->addDays($item->duration_days ?? 365);
                        $user->token_balance += $item->token_reward ?? 7; 
                        $user->save();
                    } 
                    // Perbaikan enum menjadi 'token_package'
                    elseif ($item->item_type === 'token_package') {
                        $tokenAmount = $item->token_reward ?? 1; 
                        $user->token_balance += $tokenAmount;
                        $user->save();
                    }
                }
            } 
            elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
                $transaction->update(['payment_status' => 'failed']);
            }

            DB::commit();
            return response()->json(['message' => 'Webhook processed successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Webhook error: ' . $e->getMessage()], 500);
        }
    }
}