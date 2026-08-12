<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    // Menampilkan data profil (termasuk relasi badges, profil mentor, dan target beasiswa)
    public function show(Request $request)
    {
        $user = $request->user();
        
        // Memuat relasi badges, mentorProfile, scholarships, dan assignedMentor
        $user->load(['badges', 'mentorProfile', 'scholarships', 'assignedMentor']);
        
        // Sembunyikan field password dari response
        $user->makeHidden(['password']);

        // Ubah model ke array untuk memanipulasi struktur response
        $userData = $user->toArray();

        // Ambil beasiswa utama/pertama yang dipilih user dari tabel pivot
        $selectedScholarship = $user->scholarships->first();

        // Tambahkan ID dan Data Beasiswa ke dalam payload profil
        $userData['target_scholarship_id'] = $selectedScholarship ? $selectedScholarship->id : null;
        $userData['target_scholarship_data'] = $selectedScholarship ? $selectedScholarship : null;

        // Hapus array bawaan relasi agar response lebih rapi (opsional)
        unset($userData['scholarships']);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile data retrieved successfully.',
            'data' => $userData
        ]);
    }

    // Mengupdate data profil User, Target Beasiswa, Data Rekening Bank & MentorProfile
    public function update(Request $request)
    {
        $user = $request->user();

        // 1. Cek hak akses: Apakah user ini Mentor atau Admin?
        $isMentorOrAdmin = in_array($user->role, ['mentor', 'admin']);

        // 2. Siapkan Rule Dasar (Hanya untuk tabel Users)
        $rules = [
            'name' => ['sometimes', 'string', 'min:3', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
            'phone_number' => ['sometimes', 'string', 'min:9', 'max:20', 'regex:/^[0-9\-\+\(\)]+$/', 'unique:users,phone_number,' . $user->id],
            'gender' => 'sometimes|in:male,female',
            'headline' => 'sometimes|string|max:255',
            'bio' => 'sometimes|string',
            'linkedin_id' => 'sometimes|string|max:255|unique:users,linkedin_id,' . $user->id,
            'profile_picture' => 'sometimes|file|image|mimes:jpeg,png,jpg|max:2048', 
            
            // Field Akademik & Target (Task 1.5)
            'gpa' => 'sometimes|numeric|between:0.00,4.00',
            'undergraduate_major' => 'sometimes|string|max:255',
            'target_major' => 'sometimes|string|max:255',
            'primary_scholarship_target' => 'sometimes|string|max:255',
            
            // Field untuk memilih Target Beasiswa dari Master Data
            'scholarship_id' => 'sometimes|nullable|exists:scholarships,id',
        ];

        // 3. Tambahkan Rule Mentor JIKA role-nya mengizinkan
        if ($isMentorOrAdmin) {
            $rules = array_merge($rules, [
                // Rekening Bank (disimpan di tabel users)
                'bank_name' => 'sometimes|nullable|string|max:255',
                'bank_account_number' => 'sometimes|nullable|string|max:255',
                'bank_account_name' => 'sometimes|nullable|string|max:255',
                
                // Profil Mentor (disimpan di tabel mentor_profiles)
                'university' => 'sometimes|string|max:255',
                'major' => 'sometimes|string|max:255',
                'degree_level' => 'sometimes|string|max:255',
                'scholarship_awardee' => 'sometimes|string|max:255',
                'destination_countries_expertise' => 'sometimes|array', 
                'study_fields_expertise' => 'sometimes|array',
                'expertise_tags' => 'sometimes|array',
                'languages' => 'sometimes|array',
                'mentoring_style' => 'sometimes|string|max:255',
                'current_job' => 'sometimes|string|max:255',
                'years_of_experience' => 'sometimes|integer|min:0',
                'linkedin_url' => 'sometimes|string|url|max:255',
                'max_active_mentees' => 'sometimes|integer|min:1',
                'is_accepting_mentees' => 'sometimes|boolean',
            ]);
        }

        // Pesan Error Custom
        $customMessages = [
            'name.regex' => 'The name may only contain letters and spaces.',
            'phone_number.regex' => 'The phone number format contains invalid characters.',
            'gpa.between' => 'The GPA must be a value between 0.00 and 4.00.'
        ];

        $validator = Validator::make($request->all(), $rules, $customMessages);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        
        // ==========================================
        // 4. UPDATE DATA USER (Tabel 'users')
        // ==========================================
        $userAllowedFields = [
            'name', 'phone_number', 'gender', 'headline', 'bio', 'linkedin_id',
            'gpa', 'undergraduate_major', 'target_major', 'primary_scholarship_target'
        ];

        // Izinkan mentor/admin mengupdate informasi bank mereka
        if ($isMentorOrAdmin) {
            array_push($userAllowedFields, 'bank_name', 'bank_account_number', 'bank_account_name');
        }
        
        $updateUserData = [];
        foreach ($userAllowedFields as $field) {
            if (array_key_exists($field, $validated)) {
                $updateUserData[$field] = $validated[$field];
            }
        }

        // Upload foto profil
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $filename = Str::uuid() . '.' . $file->extension();

            // Hapus foto lama
            if ($user->profile_picture_url) {
                $oldPath = str_replace(url('storage') . '/', '', $user->profile_picture_url);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Simpan foto baru
            $path = $file->storeAs('photo_profile', $filename, 'public');
            $updateUserData['profile_picture_url'] = url('storage/' . $path);
        }

        if (!empty($updateUserData)) {
            $user->update($updateUserData);
        }

        // ==========================================
        // 5. SINKRONISASI SCHOLARSHIP (Tabel Pivot)
        // ==========================================
        if (array_key_exists('scholarship_id', $validated)) {
            if (!empty($validated['scholarship_id'])) {
                // Gunakan sync untuk memastikan user hanya menargetkan 1 beasiswa utama
                $user->scholarships()->sync([$validated['scholarship_id']]);
            } else {
                // Jika request kosong (null), hapus target beasiswa
                $user->scholarships()->detach();
            }
        }

        // ==========================================
        // 6. UPDATE DATA MENTOR PROFILE (Hanya Mentor/Admin)
        // ==========================================
        if ($isMentorOrAdmin) {
            $mentorAllowedFields = [
                'university', 'major', 'degree_level', 'scholarship_awardee',
                'destination_countries_expertise', 'study_fields_expertise', 'expertise_tags',
                'languages', 'mentoring_style', 'current_job', 'years_of_experience',
                'linkedin_url', 'max_active_mentees', 'is_accepting_mentees'
            ];

            $updateMentorData = [];
            foreach ($mentorAllowedFields as $field) {
                if (array_key_exists($field, $validated)) {
                    $updateMentorData[$field] = $validated[$field];
                }
            }

            // Simpan ke tabel mentor_profiles jika ada data yang diinputkan
            if (!empty($updateMentorData)) {
                $user->mentorProfile()->updateOrCreate(
                    ['user_id' => $user->id], // Kondisi pencarian
                    $updateMentorData         // Data yang diupdate/dibuat
                );
            }
        }

        // Refresh data untuk Response akhir
        $user->refresh()->load(['badges', 'mentorProfile', 'scholarships', 'assignedMentor']);
        $user->makeHidden(['password']);
        $responseUserData = $user->toArray();

        $selectedScholarship = $user->scholarships->first();
        $responseUserData['target_scholarship_id'] = $selectedScholarship ? $selectedScholarship->id : null;
        $responseUserData['target_scholarship_data'] = $selectedScholarship ? $selectedScholarship : null;
        unset($responseUserData['scholarships']);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully.',
            'data' => $responseUserData
        ]);
    }
}