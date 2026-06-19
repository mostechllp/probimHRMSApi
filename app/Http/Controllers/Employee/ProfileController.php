<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show()
    {
        $employee = Auth::guard('employee')->user();
        return view('employee.profile.index', compact('employee'));
    }

    public function update(Request $request)
    {
        $employee = Auth::guard('employee')->user();

        $request->validate([
            'name'            => 'required|string|max:255',
            'personal_email'  => 'nullable|email|max:255',
            'personal_number' => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:500',
            'avatar'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data = $request->only(['name', 'personal_email', 'personal_number', 'address']);

        if ($request->hasFile('avatar')) {
            // Delete old avatar
            if ($employee->avatar) {
                Storage::disk('public')->delete($employee->avatar);
            }
            $path = $request->file('avatar')->store('avatars/employees', 'public');
            $data['avatar'] = $path;
        }

        $employee->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function changePassword(Request $request)
    {
        $employee = Auth::guard('employee')->user();

        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $employee->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $employee->update(['password' => $request->password]);

        return back()->with('password_success', 'Password changed successfully.');
    }
}
