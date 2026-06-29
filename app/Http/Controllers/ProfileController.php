<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileAvatarRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (array_key_exists('full_name', $validated)) {
            $user->full_name = $validated['full_name'];
        }

        if (array_key_exists('job_title', $validated)) {
            $user->job_title = $validated['job_title'];
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $user->fresh(),
            ],
        ]);
    }

    public function updateAvatar(ProfileAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $oldAvatar = $user->getRawOriginal('avatar_url');

        $file = $request->file('avatar');
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;

        try {
            Storage::disk('public')->putFileAs('avatars', $file, $filename);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Avatar upload failed. Please try again.',
                $e
            ], 422);
        }

        $user->avatar_url = 'avatars/' . $filename;
        $user->save();

        if ($oldAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Avatar updated successfully',
            'data' => [
                'user' => $user->fresh(),
            ],
        ]);
    }
}
