<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    // Menampilkan data profil (termasuk relasi badges dan profil mentor)
    public function show(Request $request)
    {
        $user = $request->user();
        
        // Memuat relasi badges dan mentorProfile (jika ada)
        $user->load(['badges', 'mentorProfile']);
        
        // Sembunyikan field password dari response
        $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile data retrieved successfully.',
            'data' => $user
        ]);
    }

    // Mengupdate data profil User & MentorProfile
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
        ];

        // 3. Tambahkan Rule Mentor JIKA role-nya mengizinkan
        if ($isMentorOrAdmin) {
            $rules = array_merge($rules, [
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

        // Data yang tervalidasi (Otomatis akan membuang field mentor jika role-nya 'user')
        $validated = $validator->validated();
        
        // ==========================================
        // 4. UPDATE DATA USER (Tabel 'users')
        // ==========================================
        $userAllowedFields = [
            'name', 'phone_number', 'gender', 'headline', 'bio', 'linkedin_id',
            'gpa', 'undergraduate_major', 'target_major', 'primary_scholarship_target'
        ];
        
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
        // 5. UPDATE DATA MENTOR PROFILE (Hanya Mentor/Admin)
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

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully.',
            // Refresh data user, load badges & mentorProfile, dan sembunyikan password
            'data' => $user->fresh()->load(['badges', 'mentorProfile'])->makeHidden(['password'])
        ]);
    }
}