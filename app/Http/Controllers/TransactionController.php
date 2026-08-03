<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    /**
     * Melihat semua riwayat transaksi milik user yang sedang login
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Mengambil transaksi diurutkan dari yang terbaru, beserta detail item yang dibeli
        $transactions = Transaction::with(['details.shopItem'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Belum ada riwayat transaksi.',
                'data' => []
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil riwayat transaksi.',
            'data' => $transactions
        ], 200);
    }

    /**
     * Melihat detail dari 1 transaksi spesifik (bisa untuk cek status)
     * Parameter $identifier bisa berupa ID dari database atau midtrans_order_id
     */
    public function show($identifier)
    {
        $user = Auth::user();

        $transaction = Transaction::with(['details.shopItem'])
            ->where('user_id', $user->id)
            ->where(function($query) use ($identifier) {
                $query->where('id', $identifier)
                      ->orWhere('midtrans_order_id', $identifier);
            })
            ->first();

        if (!$transaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaksi tidak ditemukan atau bukan milik Anda.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil detail transaksi.',
            'data' => $transaction
        ], 200);
    }
}