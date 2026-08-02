<?php

namespace App\Http\Controllers;

use App\Models\University;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UniversityController extends Controller
{
    /**
     * Get all universities with filtering, search, and pagination.
     */
    public function index(Request $request)
    {
        $query = University::query();

        // Pencarian berdasarkan nama kampus, negara, atau kota
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Filter spesifik berdasarkan negara
        if ($request->has('country')) {
            $query->where('country', $request->country);
        }

        $universities = $query->latest()->paginate($request->per_page ?? 10);

        return response()->json([
            'status' => 'success',
            'message' => 'Universities retrieved successfully.',
            'data' => $universities
        ]);
    }

    /**
     * Create a new university.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 
                'string', 
                'min:3', 
                'max:255',
                'regex:/^[a-zA-Z0-9\s]+$/', // Hanya izinkan huruf, angka, dan spasi (tanpa simbol/karakter spesial)
                'regex:/[a-zA-Z]/',         // Wajib mengandung setidaknya satu huruf (tidak boleh angka semua)
            ],
            'country' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'admission_process' => 'nullable|string',
            'admission_requirements' => 'nullable|string',
            // url:http,https mencegah XSS dari skema berbahaya seperti javascript://
            'official_website' => 'nullable|url:http,https|max:255', 
            // mimetypes memeriksa struktur asli file, mimes memeriksa ekstensi
            'image' => 'nullable|image|mimetypes:image/jpeg,image/png,image/webp|mimes:jpeg,png,jpg,webp|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Sanitasi input teks untuk mencegah XSS yang masuk dari form
        $textFields = ['name', 'country', 'city', 'description', 'admission_process', 'admission_requirements'];
        foreach ($textFields as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = strip_tags($validated[$field]);
            }
        }

        // Penanganan upload gambar dengan keamanan tambahan
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            
            // Menggunakan ->extension() (membaca header MIME asli file) 
            // BUKAN ->getClientOriginalExtension() (hanya membaca nama string file)
            $extension = $file->extension(); 
            
            $filename = Str::uuid() . '.' . $extension;
            $path = $file->storeAs('universities', $filename, 'public'); 
            $validated['image_url'] = $path;
        }

        $university = University::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'University created successfully.',
            'data' => $university
        ], 201);
    }

    /**
     * Get specific university details along with related scholarships.
     */
    public function show($id)
    {
        $university = University::with('scholarships')->find($id);

        if (!$university) {
            return response()->json([
                'status' => 'error',
                'message' => 'University not found.',
                'data' => []
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'University retrieved successfully.',
            'data' => $university
        ]);
    }

    /**
     * Update university details.
     */
    public function update(Request $request, $id)
    {
        $university = University::find($id);

        if (!$university) {
            return response()->json([
                'status' => 'error',
                'message' => 'University not found.',
                'data' => []
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes', 
                'string', 
                'min:3', 
                'max:255',
                'regex:/^[a-zA-Z0-9\s]+$/',
                'regex:/[a-zA-Z]/',
            ],
            'country' => 'sometimes|string|max:255',
            'city' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'admission_process' => 'nullable|string',
            'admission_requirements' => 'nullable|string',
            'official_website' => 'nullable|url:http,https|max:255',
            'image' => 'nullable|image|mimetypes:image/jpeg,image/png,image/webp|mimes:jpeg,png,jpg,webp|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Sanitasi input teks untuk update
        $textFields = ['name', 'country', 'city', 'description', 'admission_process', 'admission_requirements'];
        foreach ($textFields as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = strip_tags($validated[$field]);
            }
        }

        // Penanganan penggantian gambar
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($university->image_url && Storage::disk('public')->exists($university->image_url)) {
                Storage::disk('public')->delete($university->image_url);
            }

            // Upload gambar baru
            $file = $request->file('image');
            $extension = $file->extension(); 
            
            $filename = Str::uuid() . '.' . $extension;
            $path = $file->storeAs('universities', $filename, 'public');
            $validated['image_url'] = $path;
        }

        $university->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'University updated successfully.',
            'data' => $university->fresh()
        ]);
    }

    /**
     * Soft delete a university.
     */
    public function destroy($id)
    {
        $university = University::find($id);

        if (!$university) {
            return response()->json([
                'status' => 'error',
                'message' => 'University not found.',
                'data' => []
            ], 404);
        }

        $university->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'University deleted successfully.',
            'data' => []
        ]);
    }

    /**
     * Restore a soft-deleted university.
     */
    public function restore($id)
    {
        $university = University::withTrashed()->find($id);

        if (!$university) {
            return response()->json([
                'status' => 'error',
                'message' => 'University not found.',
                'data' => []
            ], 404);
        }

        if (!$university->trashed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'University is not currently deleted.',
                'data' => []
            ], 400);
        }

        $university->restore();

        return response()->json([
            'status' => 'success',
            'message' => 'University restored successfully.',
            'data' => $university
        ]);
    }
}