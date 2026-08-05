<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    // Menampilkan data profil
    public function show(Request $request)
    {
        $user = $request->user();
        
        // Memuat relasi badges yang dimiliki user
        $user->load('badges');
        
        // Sembunyikan field password dari response
        $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile data retrieved successfully.',
            'data' => $user
        ]);
    }

    // Mengupdate data profil & informasi akademik / target beasiswa (Task 1.5)
    public function update(Request $request)
    {
        $user = $request->user();

        // Validasi input dengan strict rules, termasuk field akademik & target beasiswa
        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes', 
                'string', 
                'min:3', 
                'max:255', 
                'regex:/^[a-zA-Z\s]+$/' // Hanya izinkan huruf dan spasi
            ],
            'phone_number' => [
                'sometimes', 
                'string', 
                'min:9',
                'max:20',
                'regex:/^[0-9\-\+\(\)]+$/', // Hanya izinkan format nomor telepon
                'unique:users,phone_number,' . $user->id
            ],
            'gender' => 'sometimes|in:male,female',
            'headline' => 'sometimes|string|max:255',
            'bio' => 'sometimes|string',
            'linkedin_id' => 'sometimes|string|max:255|unique:users,linkedin_id,' . $user->id,
            'profile_picture' => 'sometimes|file|image|mimes:jpeg,png,jpg|max:2048', 
            
            // Validasi Field Akademik & Target (Task 1.5)
            'gpa' => 'sometimes|numeric|between:0.00,4.00',
            'undergraduate_major' => 'sometimes|string|max:255',
            'target_major' => 'sometimes|string|max:255',
            'primary_scholarship_target' => 'sometimes|string|max:255',
        ], [
            // Custom pesan error bahasa Inggris
            'name.regex' => 'The name may only contain letters and spaces.',
            'phone_number.regex' => 'The phone number format contains invalid characters.',
            'gpa.between' => 'The GPA must be a value between 0.00 and 4.00.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $updateData = [];

        // Filter field yang diizinkan (termasuk atribut akademik & target)
        $allowedFields = [
            'name', 
            'phone_number', 
            'gender', 
            'headline', 
            'bio', 
            'linkedin_id',
            'gpa',
            'undergraduate_major',
            'target_major',
            'primary_scholarship_target'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($validated[$field])) {
                $updateData[$field] = $validated[$field];
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
            $updateData['profile_picture_url'] = url('storage/' . $path);
        }

        // Update database
        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile and academic target updated successfully.',
            // Refresh data user, load badges, dan sembunyikan password
            'data' => $user->fresh()->load('badges')->makeHidden(['password'])
        ]);
    }
}