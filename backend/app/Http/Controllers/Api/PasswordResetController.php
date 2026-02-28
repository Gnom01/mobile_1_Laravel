<?php

namespace App\Http\Controllers\Api;

use App\Models\CrmUser;
use App\Models\OtpRequest;
use App\Services\SerwerSmsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PasswordResetController
{
    /**
     * Start password reset procedure - send OTP via SMS.
     */
    public function requestReset(Request $request, SerwerSmsClient $sms)
    {
        $request->validate([
            'phone' => 'required|string|max:25',
        ]);

        $phone = $this->normalizePhone($request->string('phone'));

        $user = CrmUser::where(function ($query) use ($phone) {
            $query->whereRaw('REPLACE(Phone, " ", "") = ?', [$phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['+48' . $phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['48' . $phone]);
        })->first();

        if (!$user) {
            Log::info('Password reset requested for unknown phone', ['phone' => $phone]);
            return response()->json(['ok' => true]); // anti-enumeration
        }

        // Rate limit: max 3 OTP per phone in last 15 minutes
        $recentCount = OtpRequest::where('phone', $phone)
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();

        if ($recentCount >= 3) {
            return response()->json([
                'ok'    => false,
                'error' => 'TOO_MANY_REQUESTS',
            ], 429);
        }

        // Generate 6-digit code
        $code = (string) random_int(100000, 999999);

        $otp = OtpRequest::create([
            'phone'      => $phone,
            'code_hash'  => bcrypt($code),
            'expires_at' => now()->addMinutes(5),
            'attempts'   => 0,
        ]);

        $msg = "Kod do resetu hasla: {$code}. Wazny 5 min.";

        // In local env, use test mode (no actual SMS sent)
        $res = $sms->sendOtp($phone, $msg, app()->environment('local'));

        $messageId = $res['data']['items'][0]['id'] ?? null;
        if ($messageId) {
            $otp->update(['sent_message_id' => $messageId]);
        }

        Log::info('Password reset OTP sent', [
            'phone'      => $phone,
            'sms_ok'     => $res['ok'],
            'message_id' => $messageId,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Confirm password reset code and set new password.
     */
    public function confirmReset(Request $request)
    {
        $request->validate([
            'phone'    => 'required|string|max:25',
            'code'     => 'required|string|size:6',
            'password' => 'required|string|min:8',
        ]);

        $phone = $this->normalizePhone($request->string('phone'));
        $code  = (string) $request->string('code');

        $otp = OtpRequest::where('phone', $phone)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json([
                'ok'    => false,
                'error' => 'INVALID_CODE',
            ], 422);
        }

        if ($otp->attempts >= 5) {
            return response()->json([
                'ok'    => false,
                'error' => 'TOO_MANY_ATTEMPTS',
            ], 429);
        }

        $otp->increment('attempts');

        if (!Hash::check($code, $otp->code_hash)) {
            return response()->json([
                'ok'    => false,
                'error' => 'INVALID_CODE',
            ], 422);
        }

        $user = CrmUser::where(function ($query) use ($phone) {
            $query->whereRaw('REPLACE(Phone, " ", "") = ?', [$phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['+48' . $phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['48' . $phone]);
        })->first();

        if (!$user) {
            return response()->json([
                'ok'    => false,
                'error' => 'USER_NOT_FOUND',
            ], 404);
        }

        $otp->update(['expires_at' => now()]);

        // Revoke token 
        $user->tokens()->delete();
        
        $user->Password = bcrypt(trim($request->string('password')));
        $user->changePassword = 0;
        $user->save();

        Log::info('Password reset successful via OTP flow', [
            'user_id' => $user->UsersID,
        ]);

        return response()->json(['ok' => true]);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);
        if (str_starts_with($phone, '+48')) return substr($phone, 3);
        if (str_starts_with($phone, '48') && strlen($phone) === 11) return substr($phone, 2);
        return $phone;
    }
}
