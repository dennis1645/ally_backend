<?php

namespace App\Http\Controllers;

use App\Models\Scholarship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class ScholarshipController extends Controller
{
    public function index(Request $request)
    {
        $query = Scholarship::with('universities');

        // Filter sederhana
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('funding_type')) {
            $query->where('funding_type', $request->funding_type);
        }

        $scholarships = $query->paginate((int) $request->query('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $scholarships
        ], 200);
    }

    public function show($id)
    {
        $scholarship = Scholarship::with('universities')->find($id);

        if (!$scholarship) {
            return response()->json(['status' => 'error', 'message' => 'Beasiswa tidak ditemukan.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $scholarship
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s\-\.,&]+$/'],
            'provider_country' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'funding_type' => 'in:fully_funded,partial_funded,self_funded',
            'degree_level' => 'in:bachelor,master,phd,non_degree',
            'start_date' => 'nullable|date',
            'deadline_date' => 'nullable|date|after_or_equal:start_date',
            'eligibility_criteria' => 'nullable|string',
            'application_process' => 'nullable|string',
            'benefits' => 'nullable|string',
            'official_website' => 'nullable|url',
            'contact_email' => 'nullable|email',
            'contact_phone' => ['nullable', 'regex:/^[0-9\+\-\(\)\s]+$/'],
            'application_link' => 'nullable|url',
            'status' => 'in:draft,published',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'university_ids' => 'nullable|array',
            'university_ids.*' => 'exists:universities,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $data = $request->except(['image', 'university_ids']);

        // Anti-XSS Sanitization
        foreach ($data as $key => $value) {
            if (is_string($value) && $value !== null) {
                $data[$key] = strip_tags($value);
            }
        }

        // Upload File Aman menggunakan UUID
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('scholarships', $filename, 'public');
            $data['image_url'] = '/storage/' . $path;
        }

        $scholarship = Scholarship::create($data);

        // Simpan relasi Many-to-Many ke tabel pivot scholarship_university
        if ($request->has('university_ids')) {
            $scholarship->universities()->sync($request->university_ids);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Beasiswa berhasil ditambahkan.',
            'data' => $scholarship->load('universities')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $scholarship = Scholarship::find($id);

        if (!$scholarship) {
            return response()->json(['status' => 'error', 'message' => 'Beasiswa tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s\-\.,&]+$/'],
            'provider_country' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'funding_type' => 'in:fully_funded,partial_funded,self_funded',
            'degree_level' => 'in:bachelor,master,phd,non_degree',
            'start_date' => 'nullable|date',
            'deadline_date' => 'nullable|date|after_or_equal:start_date',
            'eligibility_criteria' => 'nullable|string',
            'application_process' => 'nullable|string',
            'benefits' => 'nullable|string',
            'official_website' => 'nullable|url',
            'contact_email' => 'nullable|email',
            'contact_phone' => ['nullable', 'regex:/^[0-9\+\-\(\)\s]+$/'],
            'application_link' => 'nullable|url',
            'status' => 'in:draft,published',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'university_ids' => 'nullable|array',
            'university_ids.*' => 'exists:universities,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $data = $request->except(['image', 'university_ids']);

        // Anti-XSS Sanitization
        foreach ($data as $key => $value) {
            if (is_string($value) && $value !== null) {
                $data[$key] = strip_tags($value);
            }
        }

        // Hapus file lama sebelum simpan file baru
        if ($request->hasFile('image')) {
            if ($scholarship->image_url) {
                $oldPath = str_replace('/storage/', '', $scholarship->image_url);
                Storage::disk('public')->delete($oldPath);
            }
            
            $file = $request->file('image');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('scholarships', $filename, 'public');
            $data['image_url'] = '/storage/' . $path;
        }

        $scholarship->update($data);

        // Update relasi kampus
        if ($request->has('university_ids')) {
            $scholarship->universities()->sync($request->university_ids);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Beasiswa berhasil diperbarui.',
            'data' => $scholarship->load('universities')
        ], 200);
    }

    public function destroy($id)
    {
        $scholarship = Scholarship::find($id);

        if (!$scholarship) {
            return response()->json(['status' => 'error', 'message' => 'Beasiswa tidak ditemukan.'], 404);
        }

        $scholarship->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Beasiswa berhasil dihapus (Soft Delete).'
        ], 200);
    }

    public function restore($id)
    {
        $scholarship = Scholarship::onlyTrashed()->find($id);

        if (!$scholarship) {
            return response()->json(['status' => 'error', 'message' => 'Beasiswa di tong sampah tidak ditemukan.'], 404);
        }

        $scholarship->restore();

        return response()->json([
            'status' => 'success',
            'message' => 'Beasiswa berhasil dipulihkan.'
        ], 200);
    }
}