<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Mail\VerificationCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ForgotPasswordApiController extends ApiController
{
    public function sendResetCode(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store code in password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $code, // Random code for verification
                'created_at' => Carbon::now()
            ]
        );

        try {
            Mail::to($request->email)->send(new VerificationCodeMail($code));
            return $this->success(null, 'Reset code sent to your email.');
        } catch (\Exception $e) {
            return $this->error('Failed to send reset code: ' . $e->getMessage(), 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->first();

        if (!$record || Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            return $this->error('The verification code is invalid or has expired.', 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return $this->success(null, 'Password has been updated successfully.');
    }
}
