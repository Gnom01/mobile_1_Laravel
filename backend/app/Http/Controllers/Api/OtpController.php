<?php

namespace App\Http\Controllers\Api;

use App\Models\CrmUser;
use App\Models\OtpRequest;
use App\Services\SerwerSmsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpController
{
    // ─────────────────────────────────────────────
    // 1. Request OTP — POST /auth/otp/request
    // ─────────────────────────────────────────────

    /**
     * Generate OTP code, store its hash, and send SMS via SerwerSMS.
     * Always returns {"ok": true} regardless of whether the phone exists (anti-enumeration).
     */
    public function requestOtp(Request $request, SerwerSmsClient $sms)
    {
        $request->validate([
            'phone' => 'required|string|max:25',
        ]);

        $phone = $this->normalizePhone($request->string('phone'));

        // Anti-enumeration: if user doesn't exist, still return ok
        $user = CrmUser::where(function ($query) use ($phone) {
            $query->whereRaw('REPLACE(Phone, " ", "") = ?', [$phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['+48' . $phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['48' . $phone]);
        })->first();

        if (!$user) {
            Log::info('OTP requested for unknown phone', ['phone' => $phone]);
            return response()->json(['ok' => true]);
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

        $appHash = config('services.sms.app_hash', '');
        $msg = "<#> Kod logowania: {$code}. Wazny 5 min.\n{$appHash}";

        // In local env, use test mode (no actual SMS sent)
        $res = $sms->sendOtp($phone, $msg, app()->environment('local'));

        // Store SerwerSMS message ID if available
        $messageId = $res['data']['items'][0]['id'] ?? null;
        if ($messageId) {
            $otp->update(['sent_message_id' => $messageId]);
        }

        Log::info('OTP sent', [
            'phone'      => $phone,
            'sms_ok'     => $res['ok'],
            'message_id' => $messageId,
        ]);

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────
    // 2. Verify OTP — POST /auth/otp/verify
    // ─────────────────────────────────────────────

    /**
     * Verify OTP code. On success: invalidate OTP, create Sanctum token,
     * and return token + user info + must_set_password flag.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:25',
            'code'  => 'required|string|size:6',
        ]);

        $phone = $this->normalizePhone($request->string('phone'));
        $code  = (string) $request->string('code');

        // Find latest non-expired OTP for this phone
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

        // Max 5 attempts
        if ($otp->attempts >= 5) {
            return response()->json([
                'ok'    => false,
                'error' => 'TOO_MANY_ATTEMPTS',
            ], 429);
        }

        $otp->increment('attempts');

        // Verify code against hash
        if (!Hash::check($code, $otp->code_hash)) {
            return response()->json([
                'ok'    => false,
                'error' => 'INVALID_CODE',
            ], 422);
        }

        // Find the user
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

        // Invalidate OTP (one-time use)
        $otp->update(['expires_at' => now()]);

        // Revoke previous tokens & create new one
        $user->tokens()->delete();
        $token = $user->createToken('mobile-otp')->plainTextToken;

        Log::info('OTP verified, token created', [
            'phone'   => $phone,
            'user_id' => $user->UsersID,
        ]);

        return response()->json([
            'ok'                => true,
            'token'             => $token,
            'must_set_password' => (bool) ($user->changePassword ?? false),
            'user'              => [
                'guid'       => $user->guid,
                'email'      => $user->Email,
                'first_name' => $user->FirstName,
                'last_name'  => $user->LastName,
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // 3. Set Password — POST /auth/password/set
    // ─────────────────────────────────────────────

    /**
     * Set a new password for the authenticated user.
     * Requires auth:sanctum middleware.
     */
    public function setPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user = $request->user();
        $user->Password = bcrypt(trim($request->string('password')));
        $user->changePassword = 0;
        $user->save();

        Log::info('Password set via OTP flow', [
            'user_id' => $user->UsersID,
        ]);

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    /**
     * Normalize phone number to 9-digits format (removes +48).
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);

        if (str_starts_with($phone, '+48')) {
            return substr($phone, 3);
        }

        if (str_starts_with($phone, '48') && strlen($phone) === 11) {
            return substr($phone, 2);
        }

        return $phone;
    }
}
