<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    /**
     * Menampilkan semua bookmark milik user yang sedang login
     */
    public function index()
    {
        // Akan otomatis mengambil data kampus atau beasiswa yang dibookmark
        $bookmarks = Bookmark::with('bookmarkable')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $bookmarks
        ]);
    }

    /**
     * Fitur Toggle (Tambah jika belum ada, Hapus jika sudah dibookmark)
     */
    public function toggleBookmark(Request $request)
    {
        $request->validate([
            'item_id' => 'required|integer',
            'item_type' => 'required|string|in:scholarship,university' // Batasi hanya untuk beasiswa dan kampus
        ]);

        // Tentukan namespace model berdasarkan input
        $modelClass = $request->item_type === 'scholarship' 
            ? 'App\\Models\\Scholarship' 
            : 'App\\Models\\University';

        // Cek apakah item ini sudah dibookmark oleh user
        $existingBookmark = Bookmark::where('user_id', Auth::id())
            ->where('bookmarkable_id', $request->item_id)
            ->where('bookmarkable_type', $modelClass)
            ->first();

        // Jika sudah ada, hapus (Unbookmark)
        if ($existingBookmark) {
            $existingBookmark->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Bookmark berhasil dihapus.',
                'action' => 'unbookmarked'
            ]);
        }

        // Jika belum ada, buat baru (Bookmark)
        $bookmark = Bookmark::create([
            'user_id' => Auth::id(),
            'bookmarkable_id' => $request->item_id,
            'bookmarkable_type' => $modelClass
        ]);

        // MENGAMBIL DATA RELASI POLIMORFIK AGAR MUNCUL DI RESPONS JSON
        $bookmark->load('bookmarkable');

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil ditambahkan ke bookmark.',
            'action' => 'bookmarked',
            'data' => $bookmark
        ], 201);
    }
}