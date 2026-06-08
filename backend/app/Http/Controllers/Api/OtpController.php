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

    // \App\Jobs\PullSchedulesEventsSettlementsJob::dispatchSync();
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
            Log::info('OTP requested for unknown phone', ['phone_suffix' => substr($phone, -3)]);
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

        $msg = "<#> Kod logowania1: {$code}. Wazny 5 min.\n{$appHash}";

        $res = $sms->sendOtp($phone, $msg, (bool) config('services.sms.test_mode', false));

        // Store SerwerSMS message ID if available
        $messageId = $res['data']['items'][0]['id'] ?? null;
        if ($messageId) {
            $otp->update(['sent_message_id' => $messageId]);
        }

        Log::info('OTP sent', [
            'phone_suffix' => substr($phone, -3),
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
            'phone_suffix' => substr($phone, -3),
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
    // 3. SMS Login — list accounts linked to phone
    //    POST /sms/login/verify
    // ─────────────────────────────────────────────

    /**
     * Verify OTP and return all accounts (self + family) linked to this phone.
     * Does NOT create a token — used before account selection.
     */
    public function loginListAccounts(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:25',
            'code'  => 'required|string|size:6',
        ]);

        $phone = $this->normalizePhone($request->string('phone'));
        $code  = (string) $request->string('code');

        $otp = OtpRequest::where('phone', $phone)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json(['ok' => false, 'error' => 'INVALID_CODE'], 422);
        }

        if ($otp->attempts >= 5) {
            return response()->json(['ok' => false, 'error' => 'TOO_MANY_ATTEMPTS'], 429);
        }

        $otp->increment('attempts');

        if (!Hash::check($code, $otp->code_hash)) {
            return response()->json(['ok' => false, 'error' => 'INVALID_CODE'], 422);
        }

        $phoneOwner = CrmUser::where(function ($query) use ($phone) {
            $query->whereRaw('REPLACE(Phone, " ", "") = ?', [$phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['+48' . $phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['48' . $phone]);
        })->where('Cancelled', 0)->first();

        if (!$phoneOwner) {
            return response()->json(['ok' => false, 'error' => 'USER_NOT_FOUND'], 404);
        }

        // Build account list: phone owner + linked family members
        $accounts = [];

        $accounts[] = [
            'guid'                     => $phoneOwner->guid,
            'first_name'               => $phoneOwner->FirstName,
            'last_name'                => $phoneOwner->LastName,
            'email'                    => $phoneOwner->Email,
            'login'                    => $phoneOwner->login ?? $phoneOwner->Email,
            'relationship'             => 'self',
            'participantRelationsDVID' => 0,
        ];

        $relations = \App\Models\UsersRelation::where('Parent_UsersID', $phoneOwner->UsersID)
            ->where('Cancelled', 0)
            ->get();

        foreach ($relations as $rel) {
            $member = CrmUser::where('UsersID', $rel->UsersID)
                ->where('Cancelled', 0)
                ->first();

            if (!$member) {
                continue;
            }

            $accounts[] = [
                'guid'                     => $member->guid,
                'first_name'               => $member->FirstName,
                'last_name'                => $member->LastName,
                'email'                    => $member->Email,
                'login'                    => $phoneOwner->login ?? $phoneOwner->Email,
                'relationship'             => 'family',
                'participantRelationsDVID' => (int) $rel->ParticipantRelationsDVID,
            ];
        }

        Log::info('SMS login: account list returned', [
            'phone_suffix'  => substr($phone, -3),
            'account_count' => count($accounts),
        ]);

        return response()->json([
            'ok'       => true,
            'accounts' => $accounts,
        ]);
    }

    // ─────────────────────────────────────────────
    // 4. SMS Login — select account and log in
    //    POST /sms/login/select-account
    // ─────────────────────────────────────────────

    /**
     * Log in the user selected from the SMS-verified account list.
     * Re-validates the OTP, confirms the selected GUID is linked to
     * the phone owner, then issues a Sanctum token.
     * Returns the same structure as standard login + relationship info.
     */
    public function selectAccountLogin(Request $request)
    {
        $request->validate([
            'phone'         => 'required|string|max:25',
            'code'          => 'required|string|size:6',
            'selected_guid' => 'required|string',
            'login'         => 'required|string',
        ]);

        $phone        = $this->normalizePhone($request->string('phone'));
        $code         = (string) $request->string('code');
        $selectedGuid = (string) $request->string('selected_guid');

        // Verify OTP is still valid
        $otp = OtpRequest::where('phone', $phone)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json(['ok' => false, 'error' => 'INVALID_CODE'], 422);
        }

        if ($otp->attempts >= 5) {
            return response()->json(['ok' => false, 'error' => 'TOO_MANY_ATTEMPTS'], 429);
        }

        $otp->increment('attempts');

        if (!Hash::check($code, $otp->code_hash)) {
            return response()->json(['ok' => false, 'error' => 'INVALID_CODE'], 422);
        }

        // Find phone owner
        $phoneOwner = CrmUser::where(function ($query) use ($phone) {
            $query->whereRaw('REPLACE(Phone, " ", "") = ?', [$phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['+48' . $phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['48' . $phone]);
        })->where('Cancelled', 0)->first();

        if (!$phoneOwner) {
            return response()->json(['ok' => false, 'error' => 'USER_NOT_FOUND'], 404);
        }

        // Find the selected user
        $selectedUser = CrmUser::where('guid', $selectedGuid)
            ->where('Cancelled', 0)
            ->first();

        if (!$selectedUser) {
            return response()->json(['ok' => false, 'error' => 'SELECTED_USER_NOT_FOUND'], 404);
        }

        // Determine relationship and verify access
        $relationship             = 'self';
        $participantRelationsDVID = 0;

        if ($selectedUser->UsersID !== $phoneOwner->UsersID) {
            $relation = \App\Models\UsersRelation::where('Parent_UsersID', $phoneOwner->UsersID)
                ->where('UsersID', $selectedUser->UsersID)
                ->where('Cancelled', 0)
                ->first();

            if (!$relation) {
                Log::warning('SMS select-account: selected GUID not linked to phone owner', [
                    'phone_suffix'  => substr($phone, -3),
                    'selected_guid' => $selectedGuid,
                    'owner_id'      => $phoneOwner->UsersID,
                ]);
                return response()->json(['ok' => false, 'error' => 'FORBIDDEN'], 403);
            }

            $relationship             = 'family';
            $participantRelationsDVID = (int) $relation->ParticipantRelationsDVID;
        }

        // Invalidate OTP (one-time use)
        $otp->update(['expires_at' => now()]);

        // Revoke previous tokens & create new one for the selected user
        $selectedUser->tokens()->delete();
        $token = $selectedUser->createToken('mobile-sms')->plainTextToken;

        Log::info('SMS select-account: login successful', [
            'phone_suffix'  => substr($phone, -3),
            'selected_id'   => $selectedUser->UsersID,
            'relationship'  => $relationship,
        ]);

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'guid'                     => $selectedUser->guid,
                'email'                    => $selectedUser->Email,
                'first_name'               => $selectedUser->FirstName,
                'last_name'                => $selectedUser->LastName,
                'role'                     => 2,
                'relationship'             => $relationship,
                'participantRelationsDVID' => $participantRelationsDVID,
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // 5. Set Password — POST /auth/password/set
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