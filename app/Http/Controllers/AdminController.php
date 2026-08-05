<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Mail\PremiumRefundMail;
use App\Mail\MentorCreatedMail;
use App\Mail\UserStatusChangedMail; // <-- IMPORT MAILABLE STATUS BARU

class AdminController extends Controller
{
    /**
     * Get all users with filtering and pagination.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Pencarian berdasarkan nama atau email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter berdasarkan status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Jangan tampilkan admin tunggal di daftar manajemen untuk menghindari salah ubah
        $query->where('role', '!=', 'admin');

        $users = $query->latest()->paginate($request->per_page ?? 10);

        return response()->json([
            'status' => 'success',
            'message' => 'Users retrieved successfully.',
            'data' => $users
        ]);
    }

    /**
     * Create a new user (Only 'user' or 'mentor' roles allowed).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'min:3', 'max:255',
                'regex:/^[a-zA-Z\s\.]+$/'
            ],
            'email' => [
                'required', 'email:rfc,dns', 'unique:users,email'
            ],
            'phone_number' => [
                'nullable', 'string', 'min:9', 'max:20',
                'regex:/^[0-9\-\+\(\)]+$/', 'unique:users,phone_number'
            ],
            'gender' => 'nullable|in:male,female',
            'role' => 'required|in:user,mentor', 
            'password' => [
                'required', 'string',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols()
            ],
            'is_premium' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $plainPassword = $request->password;
        $validated = $validator->validated();
        
        $validated['password'] = Hash::make($plainPassword);
        $validated['status'] = 'active';

        $user = User::create($validated);
        $user->markEmailAsVerified();

        if ($user->role === 'mentor') {
            try {
                Mail::to($user->email)->send(new MentorCreatedMail($user, $plainPassword));
            } catch (\Exception $e) {
                Log::error("Failed to send credentials to mentor {$user->email}: " . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => $user->role === 'mentor' 
                ? 'Mentor created successfully and credentials have been emailed.' 
                : 'User created successfully.',
            'data' => $user
        ], 201);
    }

    /**
     * Get specific user details.
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
                'data' => []
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User retrieved successfully.',
            'data' => $user
        ]);
    }

    /**
     * Update user details (Name, Email, Role, etc).
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
                'data' => []
            ], 404);
        }

        if ($user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Action restricted. You cannot modify the main administrator account via this endpoint.',
                'data' => []
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes', 'string', 'min:3', 'max:255',
                'regex:/^[a-zA-Z\s\.]+$/'
            ],
            'email' => [
                'sometimes', 'email:rfc,dns', 'unique:users,email,' . $id
            ],
            'phone_number' => [
                'nullable', 'string', 'min:9', 'max:20',
                'regex:/^[0-9\-\+\(\)]+$/', 'unique:users,phone_number,' . $id
            ],
            'gender' => 'nullable|in:male,female',
            'role' => 'sometimes|in:user,mentor',
            'is_premium' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully.',
            'data' => $user->fresh()
        ]);
    }

    /**
     * Force change user's password.
     */
    public function updatePassword(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
                'data' => []
            ], 404);
        }

        if ($user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot modify the main administrator password here.',
                'data' => []
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'password' => [
                'required', 'string', 'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols()
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User password updated successfully.',
            'data' => []
        ]);
    }

    /**
     * Suspend or Activate a user and send notification email.
     */
    public function toggleStatus(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
                'data' => []
            ], 404);
        }

        if ($user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot suspend the main administrator account.',
                'data' => []
            ], 403);
        }

        $newStatus = $user->status === 'active' ? 'suspended' : 'active';
        
        $user->update([
            'status' => $newStatus
        ]);

        // Jika user disuspend, cabut semua token (paksa logout)
        if ($newStatus === 'suspended') {
            $user->tokens()->delete();
        }

        // --- TAMBAHAN: Kirim Email Pemberitahuan Status Akun ---
        try {
            Mail::to($user->email)->send(new UserStatusChangedMail($user, $newStatus));
        } catch (\Exception $e) {
            Log::error("Failed to send status change email to {$user->email}: " . $e->getMessage());
        }
        // -------------------------------------------------------

        return response()->json([
            'status' => 'success',
            'message' => "User account has been {$newStatus} successfully and notification email sent.",
            'data' => [
                'status' => $newStatus
            ]
        ]);
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
                'data' => []
            ], 404);
        }

        if (!$user->trashed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not currently deleted.',
                'data' => []
            ], 400);
        }

        $user->restore();

        return response()->json([
            'status' => 'success',
            'message' => 'User restored successfully.',
            'data' => $user
        ]);
    }

    /**
     * Soft Delete a user and handle premium refund notification.
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
                'data' => []
            ], 404);
        }

        if ($user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete the main administrator account.',
                'data' => []
            ], 403);
        }

        if ($user->is_premium) {
            try {
                Mail::to($user->email)->send(new PremiumRefundMail($user));
            } catch (\Exception $e) {
                Log::error("Failed to send refund email to {$user->email}: " . $e->getMessage());
            }
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => $user->is_premium 
                ? 'Premium user deleted successfully. A refund notification email has been sent.'
                : 'User deleted successfully.',
            'data' => []
        ]);
    }
}