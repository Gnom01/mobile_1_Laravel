<?php

namespace App\Http\Controllers\Api;

use App\Models\CrmUser;
use App\Models\OtpRequest;
use App\Services\CrmClient;
use App\Services\SerwerSmsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PasswordResetController
{
    /**
     * Step 1: Start password reset — send OTP via SMS.
     * POST /password/reset {phone}
     */
    public function requestReset(Request $request, SerwerSmsClient $sms)
    {
        $request->validate([
            'phone' => 'required|string|max:25',
        ]);

        $phone = $this->normalizePhone($request->string('phone'));

        $user = $this->findUserByPhone($phone);

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

        $appHash = config('services.sms.app_hash', '');
        $msg = "<#> Kod do resetu hasla: {$code}. Wazny 5 min.\n{$appHash}";

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
     * Step 2: Verify OTP code and return all accounts linked to this phone.
     * POST /password/reset/verify {phone, code}
     *
     * Does NOT invalidate OTP — it stays valid for confirmReset.
     */
    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:25',
            'code'  => 'required|string|size:6',
        ]);

        $phone = $this->normalizePhone($request->string('phone'));
        $code  = (string) $request->string('code');

        // Verify OTP
        $otp = $this->verifyOtp($phone, $code);
        if (!$otp) {
            return response()->json([
                'ok'    => false,
                'error' => 'OTP_INVALID',
            ], 422);
        }

        // Fetch ALL accounts linked to this phone number
        $accounts = $this->findAllUsersByPhone($phone);

        if ($accounts->isEmpty()) {
            return response()->json([
                'ok'    => false,
                'error' => 'NO_ACCOUNTS',
            ], 404);
        }

        Log::info('Password reset OTP verified, returning accounts', [
            'phone'         => $phone,
            'accounts_count' => $accounts->count(),
        ]);

        return response()->json([
            'ok'       => true,
            'accounts' => $accounts->map(fn ($u) => [
                'guid'      => $u->guid,
                'Email'     => $u->Email,
                'Login'     => $u->Login,
                'FirstName' => $u->FirstName,
                'LastName'  => $u->LastName,
            ])->values(),
        ]);
    }

    /**
     * Step 3: Confirm password reset — update login + password for selected account.
     * POST /password/reset/confirm {phone, code, guid, login, password}
     *
     * Flow: re-verify OTP → validate guid belongs to phone → CRM update → sync → local save → invalidate OTP.
     */
    public function confirmReset(Request $request, CrmClient $crmClient)
    {
        $request->validate([
            'phone'    => 'required|string|max:25',
            'code'     => 'required|string|size:6',
            'guid'     => 'required|string',
            'login'    => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        $phone       = $this->normalizePhone($request->string('phone'));
        $code        = (string) $request->string('code');
        $guid        = (string) $request->input('guid');
        $newLogin    = strtolower(trim($request->input('login')));
        $newPassword = trim((string) $request->input('password'));

        // 1. Re-verify OTP (must still be valid)
        $otp = $this->verifyOtp($phone, $code);
        if (!$otp) {
            return response()->json([
                'ok'    => false,
                'error' => 'OTP_INVALID',
            ], 422);
        }

        // 2. Validate that guid belongs to this phone number
        $accountsForPhone = $this->findAllUsersByPhone($phone);
        $user = $accountsForPhone->firstWhere('guid', $guid);

        if (!$user) {
            Log::warning('Password reset: guid does not match phone', [
                'phone' => $phone,
                'guid'  => $guid,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'ACCOUNT_NOT_MATCH_PHONE',
            ], 422);
        }

        // 3. Send update to CRM
        $crmPayload = [
            'usersID'                       => $user->UsersID,
            'active'                        => (string) $user->Active,
            'dateOfBirdth'                  => $user->DateOfBirdth ? $user->DateOfBirdth->format('Y-m-d') : '0000-00-00',
            'postalCode'                    => $user->PostalCode ?? '',
            'postPlace'                     => $user->PostPlace ?? '',
            'memberCardNumber'              => $user->MemberCardNumber ?? '',
            'lastName'                      => $user->LastName ?? '',
            'firstName'                     => $user->FirstName ?? '',
            'email'                         => $newLogin,
            'address'                       => $user->address ?? '',
            'login'                         => $newLogin,
            'password'                      => $newPassword,
            'phone'                         => $user->Phone ?? '',
            'city'                          => $user->City ?? '',
            'default_LocalizationsID'       => (string) ($user->Default_LocalizationsID ?? 0),
            'parent_UsersID'                => (int) ($user->Parent_UsersID ?? 0),
            'street'                        => $user->Street ?? '',
            'building'                      => $user->Building ?? '',
            'flat'                          => $user->Flat ?? '',
            'description'                   => $user->Description ?? '',
            'genderDVID'                    => (int) ($user->GenderDVID ?? 0),
            'genderName'                    => '',
            'identityNumber'                => $user->IdentityNumber ?? '',
            'pesel'                         => $user->Pesel ?? '',
            'entryFee'                      => (int) ($user->entryFee ?? 0),
            'activationDate'                => $user->ActivationDate ? $user->ActivationDate->format('Y-m-d') : null,
            'paymentMethodsDVID'            => (int) ($user->PaymentMethodsDVID ?? 0),
            'paymentMethodsName'            => '',
            'personalDataProcessingConsent' => (int) ($user->PersonalDataProcessingConsent ?? 0),
            'consentReceiveSmsEmailPhone'   => (int) ($user->consentReceiveSmsEmailPhone ?? 0),
            'marketingAgreement'            => (int) ($user->marketingAgreement ?? 0),
            'fileName'                      => $user->FileName ?? '',
            'fileExtension'                 => $user->FileExtension ?? '',
            'positionsDVID'                 => 0,
            'employeesID'                   => 0,
            'statusUser'                    => $user->statusUser ?? 'Lead',
            'colorStatus'                   => '',
            'userStatus'                    => (int) ($user->UserStatus ?? 3),
            'bankAccount'                   => $user->bankAccount ?? '',
            'fileURL'                       => '',
            'cancelled'                     => (string) ($user->Cancelled ?? '0'),
            'birthPlace'                    => $user->BirthPlace ?? '',
            'voivodeshipDVID'               => (string) ($user->VoivodeshipDVID ?? ''),
            'comunity'                      => $user->Comunity ?? '',
            'district'                      => $user->District ?? '',
            'localizationsID'               => null,
            'localizationsIDArray'          => [$user->Default_LocalizationsID ?? 0],
            'current_LocalizationsID'       => (string) ($user->Default_LocalizationsID ?? 0),
        ];

        try {
            $crmResp = $crmClient->post('/CrmToMobileSync/setUsersForLocalization', $crmPayload);
            $crmData = $crmResp->json();

            Log::info('Password reset: CRM update response', [
                'guid'   => $guid,
                'status' => $crmResp->status(),
                'data'   => $crmData,
            ]);
        } catch (\Throwable $e) {
            Log::error('Password reset: CRM update failed', [
                'guid'  => $guid,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'error'   => 'CRM_ERROR',
                'message' => 'Failed to update credentials in CRM. Please try again.',
            ], 500);
        }

        // 4. Sync users from CRM to local DB
        try {
            \App\Jobs\PullUsersJob::dispatchSync();
        } catch (\Throwable $e) {
            Log::warning('Password reset: PullUsersJob failed after CRM update', [
                'guid'  => $guid,
                'error' => $e->getMessage(),
            ]);
            // Non-fatal: CRM was updated, local sync can happen later
        }

        // 5. Revoke old tokens
        $user->tokens()->delete();

        // 7. Invalidate OTP (only after everything succeeded)
        $otp->update(['expires_at' => now()]);

        Log::info('Password reset successful', [
            'guid'      => $guid,
            'new_login' => $newLogin,
            'phone'     => $phone,
        ]);

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    /**
     * Verify OTP code for the given phone. Returns OtpRequest if valid, null otherwise.
     * Increments attempts but does NOT invalidate the OTP.
     */
    private function verifyOtp(string $phone, string $code): ?OtpRequest
    {
        $otp = OtpRequest::where('phone', $phone)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return null;
        }

        if ($otp->attempts >= 5) {
            return null;
        }

        $otp->increment('attempts');

        if (!Hash::check($code, $otp->code_hash)) {
            return null;
        }

        return $otp;
    }

    /**
     * Find a single user by phone number.
     */
    private function findUserByPhone(string $phone): ?CrmUser
    {
        return CrmUser::where(function ($query) use ($phone) {
            $query->whereRaw('REPLACE(Phone, " ", "") = ?', [$phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['+48' . $phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['48' . $phone]);
        })->first();
    }

    /**
     * Find ALL users linked to a phone number.
     */
    private function findAllUsersByPhone(string $phone)
    {
        return CrmUser::where(function ($query) use ($phone) {
            $query->whereRaw('REPLACE(Phone, " ", "") = ?', [$phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['+48' . $phone])
                  ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['48' . $phone]);
        })->get();
    }

    /**
     * Normalize phone number to 9-digits format (removes +48).
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);
        if (str_starts_with($phone, '+48')) return substr($phone, 3);
        if (str_starts_with($phone, '48') && strlen($phone) === 11) return substr($phone, 2);
        return $phone;
    }
}
