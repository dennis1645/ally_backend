<?php

namespace App\Http\Controllers;

use App\Models\ShopItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminShopItemController extends Controller
{
    /**
     * Menampilkan semua item di Shop (Termasuk yang tidak aktif)
     */
    public function index()
    {
        $items = ShopItem::orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil data shop items',
            'data' => $items
        ]);
    }

    /**
     * Menyimpan item baru ke database
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'item_type' => 'required|in:subscription,token_package,practice_bundle,gamification_item,other',
            'description' => 'nullable|string',
            'price_rupiah' => 'nullable|numeric|min:0',
            'price_xp' => 'nullable|integer|min:0',
            'token_reward' => 'nullable|integer|min:0',
            'duration_days' => 'nullable|integer|min:1',
            'stock_quantity' => 'nullable|integer|min:0',
            'image_url' => 'nullable|string', // Bisa diubah ke file/mimes jika menggunakan upload gambar fisik
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $item = ShopItem::create([
            'name' => $request->name,
            'item_type' => $request->item_type,
            'description' => $request->description,
            'price_rupiah' => $request->price_rupiah ?? 0,
            'price_xp' => $request->price_xp ?? 0,
            'token_reward' => $request->token_reward ?? 0,
            'duration_days' => $request->duration_days,
            'stock_quantity' => $request->stock_quantity ?? 0,
            'image_url' => $request->image_url,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Item berhasil ditambahkan',
            'data' => $item
        ], 201);
    }

    /**
     * Menampilkan detail satu item berdasarkan ID
     */
    public function show($id)
    {
        $item = ShopItem::find($id);

        if (!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail item berhasil diambil',
            'data' => $item
        ]);
    }

    /**
     * Memperbarui data item
     */
    public function update(Request $request, $id)
    {
        $item = ShopItem::find($id);

        if (!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'item_type' => 'sometimes|required|in:subscription,token_package,practice_bundle,gamification_item,other',
            'description' => 'nullable|string',
            'price_rupiah' => 'nullable|numeric|min:0',
            'price_xp' => 'nullable|integer|min:0',
            'token_reward' => 'nullable|integer|min:0',
            'duration_days' => 'nullable|integer|min:1',
            'stock_quantity' => 'nullable|integer|min:0',
            'image_url' => 'nullable|string', 
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $item->update($request->only([
            'name', 'item_type', 'description', 'price_rupiah', 'price_xp', 
            'token_reward', 'duration_days', 'stock_quantity', 'image_url', 'is_active'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Item berhasil diperbarui',
            'data' => $item
        ]);
    }

    /**
     * Menghapus item dari database
     */
    public function destroy($id)
    {
        $item = ShopItem::find($id);

        if (!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item tidak ditemukan'
            ], 404);
        }

        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Item berhasil dihapus'
        ]);
    }
}