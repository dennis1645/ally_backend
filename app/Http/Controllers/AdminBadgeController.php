<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminBadgeController extends Controller
{
    // Lihat semua badge
    public function index()
    {
        $badges = Badge::orderBy('required_xp', 'asc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $badges
        ]);
    }

    // Buat badge baru
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|url',
            'required_xp' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $badge = Badge::create($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Badge created successfully',
            'data' => $badge
        ], 201);
    }

    // Lihat detail badge
    public function show($id)
    {
        $badge = Badge::find($id);
        if (!$badge) {
            return response()->json(['status' => 'error', 'message' => 'Badge not found'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $badge]);
    }

    // Update badge
    public function update(Request $request, $id)
    {
        $badge = Badge::find($id);
        if (!$badge) {
            return response()->json(['status' => 'error', 'message' => 'Badge not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|url',
            'required_xp' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $badge->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Badge updated successfully',
            'data' => $badge
        ]);
    }

    // Hapus badge
    public function destroy($id)
    {
        $badge = Badge::find($id);
        if (!$badge) {
            return response()->json(['status' => 'error', 'message' => 'Badge not found'], 404);
        }

        $badge->delete();
        return response()->json(['status' => 'success', 'message' => 'Badge deleted successfully']);
    }
}