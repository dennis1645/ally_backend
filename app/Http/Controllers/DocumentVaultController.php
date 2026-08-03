<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DocumentVault;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentVaultController extends Controller
{
    /**
     * Menampilkan daftar dokumen milik user yang sedang login.
     */
    public function index()
    {
        $documents = DocumentVault::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $documents
        ]);
    }

    /**
     * Mengunggah dan mengenkripsi dokumen baru.
     */
    public function store(Request $request)
    {
        // 1. Validasi Input & File
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // Max 5MB
            'file_type' => 'required|in:cv,transcript,certificate,essay,loa,other',
            'scholarship_id' => 'nullable|exists:scholarships,id',
            'university_id' => 'nullable|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $originalName = strip_tags($file->getClientOriginalName()); // Anti-XSS
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        // 2. Keamanan: Penamaan menggunakan UUID
        $uuidName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $directory = 'vault/' . Auth::id();
        $filePath = $directory . '/' . $uuidName;

        try {
            // 3. Keamanan: Enkripsi File (Encrypted at rest)
            $fileContent = file_get_contents($file->getRealPath());
            $encryptedContent = Crypt::encryptString($fileContent);

            // Simpan ke disk 'local' (tidak bisa diakses publik secara langsung)
            Storage::disk('local')->put($filePath, $encryptedContent);

            // 4. Simpan ke Database
            $document = DocumentVault::create([
                'user_id' => Auth::id(),
                'scholarship_id' => $request->scholarship_id,
                'university_id' => $request->university_id,
                'file_name' => $originalName,
                'file_path' => $filePath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'file_type' => $request->file_type,
                'status' => 'uploaded',
                'is_encrypted' => true,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Dokumen berhasil diunggah dan dienkripsi.',
                'data' => $document
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses dokumen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan detail dan mendekripsi file untuk diunduh/dilihat.
     */
    public function show($id)
    {
        // Ganti findOrFail dengan find agar bisa di-handle manual dengan JSON
        $document = DocumentVault::find($id);

        if (!$document) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dokumen tidak ditemukan.'
            ], 404);
        }

        // 1. Keamanan: Otorisasi Kepemilikan
        if ($document->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to this document.'
            ], 403);
        }

        // 2. Cek eksistensi file fisik
        if (!Storage::disk('local')->exists($document->file_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File fisik tidak ditemukan.'
            ], 404);
        }

        try {
            // 3. Dekripsi file secara on-the-fly
            $encryptedContent = Storage::disk('local')->get($document->file_path);
            $decryptedContent = Crypt::decryptString($encryptedContent);

            // 4. Kembalikan file sebagai response stream
            return response($decryptedContent, 200)
                ->header('Content-Type', $document->mime_type)
                ->header('Content-Disposition', 'inline; filename="' . $document->file_name . '"');

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mendekripsi dokumen.'
            ], 500);
        }
    }

    /**
     * Menghapus dokumen (Soft Delete).
     */
    public function destroy($id)
    {
        // Ganti findOrFail dengan find agar bisa di-handle manual dengan JSON
        $document = DocumentVault::find($id);

        if (!$document) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dokumen tidak ditemukan.'
            ], 404);
        }

        // Keamanan: Otorisasi Kepemilikan
        if ($document->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to this document.'
            ], 403);
        }

        // Karena menggunakan SoftDeletes, kita hanya menghapus record di database.
        // File fisik tetap disimpan untuk keperluan audit/recovery, atau bisa dihapus
        // dengan Storage::disk('local')->delete($document->file_path) jika ingin hard delete.
        $document->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen berhasil dihapus.'
        ]);
    }
}