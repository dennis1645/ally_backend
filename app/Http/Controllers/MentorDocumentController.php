<?php

namespace App\Http\Controllers;

use App\Models\MentorDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MentorDocumentController extends Controller
{
    /**
     * 1. MENTOR: Melihat daftar dokumen yang pernah ia unggah
     */
    public function index()
    {
        $documents = MentorDocument::where('mentor_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($document) {
                // Sisipkan link secara dinamis agar mentor bisa menyalin ulang jika lupa
                $document->shareable_link = url("/api/documents/view/{$document->share_token}");
                $document->honeypot_link = url("/api/documents/download/{$document->share_token}");
                return $document;
            });

        return response()->json([
            'status' => 'success',
            'data' => $documents
        ]);
    }

    /**
     * 2. MENTOR: Mengunggah dokumen baru dan mengatur waktu kedaluwarsa
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // Maksimal 5MB
            'duration' => 'required|in:5_minutes,1_hour,2_days,1_year'
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $fileType = strtolower($extension) === 'pdf' ? 'pdf' : 'image';

        // Simpan file di disk 'local' agar tidak bisa diakses langsung via URL publik
        $path = $file->store('mentor_documents', 'local');

        // Hitung waktu kedaluwarsa
        $expiresAt = match ($request->duration) {
            '5_minutes' => Carbon::now()->addMinutes(5),
            '1_hour' => Carbon::now()->addHour(),
            '2_days' => Carbon::now()->addDays(2),
            '1_year' => Carbon::now()->addYear(),
        };

        // Buat token unik acak (64 karakter)
        $token = Str::random(64);

        $document = MentorDocument::create([
            'mentor_id' => Auth::id(),
            'title' => $request->title,
            'file_path' => $path,
            'file_type' => $fileType,
            'share_token' => $token,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen berhasil diunggah dan tautan dibagikan.',
            'data' => [
                'document' => $document,
                'shareable_link' => url("/api/documents/view/{$token}"), // Link untuk view streaming
                'honeypot_link' => url("/api/documents/download/{$token}") // Link jebakan
            ]
        ], 201);
    }

    /**
     * 3. MENTOR: Menghapus dokumen miliknya
     */
    public function destroy($id)
    {
        $document = MentorDocument::where('id', $id)
            ->where('mentor_id', Auth::id())
            ->firstOrFail();

        // Hapus file fisik dari storage
        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen berhasil dihapus.'
        ]);
    }

    /**
     * 4. MENTEE / PUBLIK: Mengakses dokumen (VIEW ONLY)
     * Menggunakan header no-cache dan inline disposition agar tidak diunduh otomatis
     */
    public function viewSharedDocument(Request $request, $token)
    {
        $document = MentorDocument::where('share_token', $token)->first();

        // 1. Cek Ketersediaan & Waktu Kedaluwarsa
        if (!$document || ($document->expires_at && Carbon::now()->greaterThan($document->expires_at))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dokumen tidak ditemukan atau tautan sudah kedaluwarsa.'
            ], 403);
        }

        // 2. Cek eksistensi file fisik di server
        if (!Storage::disk('local')->exists($document->file_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File fisik tidak ditemukan.'
            ], 404);
        }

        // 3. Render dokumen secara inline (streaming) dengan security headers
        return Storage::disk('local')->response($document->file_path, null, [
            'Content-Type' => $document->file_type === 'pdf' ? 'application/pdf' : 'image/jpeg',
            'Content-Disposition' => 'inline; filename="secure-document"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * 5. HONEYPOT TRAP: Jebakan untuk Mentee yang mencoba memaksa download
     * Jika endpoint ini di-hit, akun langsung disuspend.
     */
    public function downloadTrap($token)
    {
        $user = Auth::user(); 

        if ($user) {
            // Ubah status akun menjadi suspended
            $user->update(['status' => 'suspended']);
            
            // Hapus semua token login agar ter-logout otomatis dari semua device
            $user->tokens()->delete();

            // Catat log keamanan
            Log::alert("SECURITY ALERT: User ID {$user->id} ({$user->email}) attempted to bypass document security by hitting the download trap. Account has been suspended.");

            return response()->json([
                'status' => 'error',
                'message' => 'PELANGGARAN KEAMANAN! Anda mencoba mengunduh dokumen rahasia. Akun Anda telah ditangguhkan secara otomatis.'
            ], 403);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Akses ditolak.'
        ], 403);
    }
}