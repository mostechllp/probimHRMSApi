<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Password;

class ProfileApiController extends ApiController
{
    /**
     * Change User Password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation Error', 422, $validator->errors());
        }

        $user = auth('api')->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Current password does not match nuestro record.', 422, [
                'current_password' => ['The current password provided is incorrect.']
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return $this->success($user, 'Password updated successfully.');
    }

    /**
     * Update Profile Data
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user)
            return $this->error('Unauthorized', 401);

        $request->validate([
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'email'    => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'avatar'   => 'nullable|string|starts_with:temp/',
        ]);

        // Update user fields (no avatar here)
        $userData = $request->only('username', 'email');
        $user->update($userData);

        // Handle avatar — store in employees table
        if ($request->hasFile('avatar')) {
            $employee = $user->employee;

            if ($employee) {
                // Delete old avatar if exists
                if ($employee->avatar) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($employee->avatar);
                }

                $path = $request->file('avatar')->store('avatars', 'public');
                $employee->update(['avatar' => $path]);
            }
        } elseif ($request->filled('avatar') && str_starts_with($request->input('avatar'), 'temp/')) {
            $employee = $user->employee;
            
            if ($employee) {
                $tempPath = $request->input('avatar');
                
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($tempPath)) {
                    // Delete old avatar if exists
                    if ($employee->avatar) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($employee->avatar);
                    }
                    
                    $fileName = basename($tempPath);
                    $newPath = 'avatars/' . $fileName;
                    
                    \Illuminate\Support\Facades\Storage::disk('public')->move($tempPath, $newPath);
                    $employee->update(['avatar' => $newPath]);
                }
            }
        }

        $user->load('employee');

        return $this->success($user, 'Profile updated successfully.');
    }
}
