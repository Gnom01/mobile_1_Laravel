<?php

namespace App\Http\Controllers\Api;

use App\Models\CrmUser;
use App\Models\OtpRequest;
use App\Models\UsersRelation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController
{
    /**
     * Handle login request and return API token.
     */
    public function login(Request $request)
    {

         //\App\Jobs\PullSchedulesEventsSettlementsJob::dispatchSync();
        // PullDictionariesJob::PullCoursesJob(); // trigger sync to update dictionaries immediately after login 

        $request->validate([
            'Email' => 'required|email',
            'Password' => 'required',
            'current_LocalizationsID' => 'sometimes|integer',
        ]);

        $email = strtolower(trim($request->input('Email')));
        $password = trim((string) $request->input('Password'));

        $loginCandidates = CrmUser::where(function ($query) use ($email) {
                $query->where('login', $email)
                    ->orWhere('Email', $email);
            })
            ->where('Cancelled', 0)
            ->orderByRaw('CASE WHEN Parent_UsersID IS NULL OR Parent_UsersID = 0 THEN 0 ELSE 1 END')
            ->orderByDesc('isMainAccount')
            ->orderBy('UsersID')
            ->get();

        $user = $loginCandidates->first();

        // Check if user exists
        if (!$user) {
            Log::warning('Login failed: user not found', [
                'email_hash' => sha1($email),
            ]);
            throw ValidationException::withMessages([
                'Email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $loginCandidates->first(fn (CrmUser $candidate) => $this->passwordMatches($candidate, $password));

        if (!$user) {
            Log::warning('Login failed: incorrect password', [
                'email_hash' => sha1($email),
                'candidate_count' => $loginCandidates->count(),
            ]);
            throw ValidationException::withMessages([
                'Email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Auto-migrate legacy/plaintext password to Bcrypt
        $storedHash = (string) $user->Password;
        if (!$this->isModernPasswordHash($storedHash)) {
            $user->Password = Hash::make($password);
            $user->save();
            Log::info('Password migrated to Bcrypt', ['user_id' => $user->UsersID]);
        }

        Log::info('Login successful', [
            'email_hash' => sha1($email),
            'user_id' => $user->UsersID,
            'guid' => $user->guid,
        ]);

        // Usuń poprzednie tokeny (opcjonalnie)
        $user->tokens()->delete();

        // Utworz nowy token
        $token = $user->createToken('api-token')->plainTextToken;

        $user->refresh();

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'guid' => $user->guid,
                'email' => $user->Email,
                'first_name' => $user->FirstName,
                'last_name' => $user->LastName,
                'role' => \App\Support\RoleResolver::resolve($user),
            ],
        ]);
    }

    /**
     * Handle register request and return API token.
     * Flow: POST to CRM → sync from CRM to local DB → login user.
     */
    public function register(Request $request, \App\Services\CrmClient $crmClient)
    {
        // Map snake_case to PascalCase if they exist
        $request->merge([
            'FirstName' => $request->input('FirstName', $request->input('first_name')),
            'LastName' => $request->input('LastName', $request->input('last_name')),
            'Email' => $request->input('Email', $request->input('email')),
            'Phone' => $request->input('Phone', $request->input('phone')),
            'Password' => $request->input('Password', $request->input('password')),
            'PersonalDataProcessingConsent' => $request->input('PersonalDataProcessingConsent', $request->input('personal_data_processing_consent')),
            'consentReceiveSmsEmailPhone' => $request->input('consentReceiveSmsEmailPhone', $request->input('consent_receive_sms_email_phone')),
            'marketingAgreement' => $request->input('marketingAgreement', $request->input('marketing_agreement')),
            'current_LocalizationsID' => $request->input('current_LocalizationsID', $request->input('current_localizations_id')),
        ]);

        $request->validate([
            'FirstName' => 'required|string|max:255',
            'LastName' => 'required|string|max:255',
            'Email' => 'required|email',
            'Phone' => 'required|string|max:25',
            'otp_code' => 'required|string|size:6',
            'Password' => 'required|string|min:8',
            'PersonalDataProcessingConsent' => 'nullable|boolean',
            'consentReceiveSmsEmailPhone' => 'nullable|boolean',
            'marketingAgreement' => 'nullable|boolean',
            'current_LocalizationsID' => 'nullable|integer',
        ]);

        $email = strtolower(trim($request->input('Email')));
        $phone = $request->input('Phone');
        $otpCode = trim((string) $request->input('otp_code'));
        $password = (string) $request->input('Password');
        $localizationId = $request->input('current_LocalizationsID', 3);
        $normalizedPhone = $this->normalizePhone((string) $phone);

        // Update request with normalized email
        $request->merge(['Email' => $email]);
        
        // Check if user already exists locally
        
        if (CrmUser::where('login', $email)->orWhere('Email', $email)->exists()) {
            throw ValidationException::withMessages([
                'Email' => ['A user with this email already exists.'],
            ]);
        }

        if (!$this->verifyOtpForPhone($normalizedPhone, $otpCode)) {
            return response()->json([
                'success' => false,
                'error' => 'OTP_INVALID',
                'message' => 'Invalid or expired OTP code.',
            ], 422);
        }

        // 1. POST new user to CRM API
        $crmPayload = [
            'usersID'                       => 0, // 0 = new user
            'active'                        => '1',
            'dateOfBirdth'                  => '0000-00-00',
            'postalCode'                    => '',
            'postPlace'                     => '',
            'memberCardNumber'              => '',
            'lastName'                      => $request->input('LastName'),
            'firstName'                     => $request->input('FirstName'),
            'email'                         => $email,
            'address'                       => '',
            'login'                         => $email,
            'password'                      => $password,
            'phone'                         => $phone,
            'city'                          => '',
            'default_LocalizationsID'       => (string) $localizationId,
            'parent_UsersID'                => 0,
            'street'                        => '',
            'building'                      => '',
            'flat'                          => '',
            'description'                   => '',
            'genderDVID'                    => 0,
            'genderName'                    => '',
            'identityNumber'                => '',
            'pesel'                         => '',
            'entryFee'                      => 0,
            'activationDate'                => null,
            'paymentMethodsDVID'            => 0,
            'paymentMethodsName'            => '',
            'personalDataProcessingConsent' => $request->input('PersonalDataProcessingConsent', false) ? 1 : 0,
            'consentReceiveSmsEmailPhone'   => $request->input('consentReceiveSmsEmailPhone', false) ? 1 : 0,
            'marketingAgreement'            => $request->input('marketingAgreement', false) ? 1 : 0,
            'fileName'                      => '',
            'fileExtension'                 => '',
            'positionsDVID'                 => 0,
            'employeesID'                   => 0,
            'statusUser'                    => 'Lead',
            'colorStatus'                   => '',
            'userStatus'                    => 3,
            'bankAccount'                   => '',
            'fileURL'                       => '',
            'cancelled'                     => '0',
            'birthPlace'                    => '',
            'voivodeshipDVID'               => '',
            'comunity'                      => '',
            'district'                      => '',
            'localizationsID'               => null,
            'localizationsIDArray'          => [$localizationId],
            'current_LocalizationsID'       => (string) $localizationId,
        ];

        Log::info('Registering user in CRM', [
            'email_hash' => sha1($email),
            'phone_suffix' => substr($normalizedPhone, -3),
        ]);

        try {
            $crmResp = $crmClient->post('/CrmToMobileSync/setUsersForLocalization', $crmPayload);

            Log::info('CRM registration response', [
                'status' => $crmResp->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error('CRM registration failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Registration failed in CRM. Please try again later.',
            ], 500);
        }

        // 2. Sync users from CRM to local mobile DB
        try {
            \App\Jobs\PullUsersJob::dispatchSync();
        } catch (\Throwable $e) {
            Log::warning('PullUsersJob failed after registration', [
                'error' => $e->getMessage(),
            ]);
        }

        // 3. Find the newly synced user in local DB and login
        $user = CrmUser::where('Email', $email)
            ->orWhere('login', $email)
            ->first();

        if (!$user) {
            // Fallback: try finding by phone
            $user = CrmUser::whereRaw('REPLACE(Phone, " ", "") = ?', [
                preg_replace('/[\s\-\(\)]+/', '', $phone),
            ])->first();
        }

        if (!$user) {
            Log::error('User not found in local DB after CRM registration + sync', [
                'email_hash' => sha1($email),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'User was created in CRM but sync to local DB failed. Please try logging in.',
            ], 500);
        }

        OtpRequest::where('phone', $normalizedPhone)
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        // Revoke old tokens & create new one
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        Log::info('Registration successful', [
            'email_hash'   => sha1($email),
            'user_id' => $user->UsersID,
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'guid' => $user->guid,
                'email' => $user->Email,
                'first_name' => $user->FirstName,
                'last_name' => $user->LastName,
                'role' => \App\Support\RoleResolver::resolve($user),
            ],
        ]);
    }

    /**
     * Get the authenticated user's profile or a specific user's profile by usersID.
     */
    public function profile(Request $request)
    {
        $authUser = $request->user();
        $guid = $request->input('guid');

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = $authUser;

        if ($guid) {
            $target = CrmUser::where('guid', $guid)->where('Cancelled', 0)->first();
            if (!$target) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $isSelf = $target->UsersID === $authUser->UsersID;
            $isRelated = UsersRelation::where('Parent_UsersID', $authUser->UsersID)
                ->where('UsersID', $target->UsersID)
                ->where('Cancelled', 0)
                ->exists();

            if (!$isSelf && !$isRelated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden',
                ], 403);
            }

            $user = $target;
        }

        return response()->json([
            'success' => true,
            'user' => [
                'guid' => $user->guid,
                'FirstName' => $user->FirstName,
                'LastName' => $user->LastName,
                'Email' => $user->Email,
                'Phone' => $user->Phone,
                'DateOfBirdth' => $user->DateOfBirdth ? $user->DateOfBirdth->format('Y-m-d') : null,
                'Street' => $user->Street,
                'Building' => $user->Building,
                'Flat' => $user->Flat,
                'City' => $user->City,
                'PostalCode' => $user->PostalCode,
                'GenderDVID' => $user->GenderDVID,
                'address' => $user->address,
            ],
        ]);
    }

    /**
     * Get the user's consents (permissions).
     */
    public function consents(Request $request, \App\Services\CrmClient $crmClient)
    {
        $authUser = $request->user();
        $guid = $request->input('guid');

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = $authUser;

        if ($guid) {
            $target = CrmUser::where('guid', $guid)->where('Cancelled', 0)->first();
            if (!$target) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $isSelf = $target->UsersID === $authUser->UsersID;
            $isRelated = UsersRelation::where('Parent_UsersID', $authUser->UsersID)
                ->where('UsersID', $target->UsersID)
                ->where('Cancelled', 0)
                ->exists();

            if (!$isSelf && !$isRelated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden',
                ], 403);
            }

            $user = $target;
        }

        // ── Zapis zgód (POST/PUT) ─────────────────────────────────────────────
        // Zgodę na przetwarzanie danych (wymaganą) zostawiamy bez zmian — nie da
        // się jej wyłączyć z aplikacji. Pozostałe są opcjonalne.
        if (!$request->isMethod('get')) {
            $validated = $request->validate([
                'consentReceiveSmsEmailPhone' => 'sometimes|boolean',
                'marketingAgreement'          => 'sometimes|boolean',
                'newsletter'                  => 'sometimes|boolean',
            ]);

            if (array_key_exists('consentReceiveSmsEmailPhone', $validated)) {
                $user->consentReceiveSmsEmailPhone = $validated['consentReceiveSmsEmailPhone'] ? 1 : 0;
            }
            if (array_key_exists('marketingAgreement', $validated)) {
                $user->marketingAgreement = $validated['marketingAgreement'] ? 1 : 0;
            }
            if (array_key_exists('newsletter', $validated)) {
                $user->Newsletter = $validated['newsletter'] ? 1 : 0;
            }
            $user->save();

            // Push do CRM (źródło prawdy) — inaczej najbliższy sync nadpisałby zmianę.
            try {
                $crmClient->post('/CrmToMobileSync/setUserConsents', [
                    'usersID'                       => (int) $user->UsersID,
                    'personalDataProcessingConsent' => (int) $user->PersonalDataProcessingConsent,
                    'consentReceiveSmsEmailPhone'   => (int) $user->consentReceiveSmsEmailPhone,
                    'marketingAgreement'            => (int) $user->marketingAgreement,
                    'newsletter'                    => (int) $user->Newsletter,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Consents: push do CRM nieudany', [
                    'user_id' => $user->UsersID,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'consents' => [
                'PersonalDataProcessingConsent' => (bool) $user->PersonalDataProcessingConsent,
                'consentReceiveSmsEmailPhone'   => (bool) $user->consentReceiveSmsEmailPhone,
                'marketingAgreement'            => (bool) $user->marketingAgreement,
                'newsletter'                    => (bool) $user->Newsletter,
            ],
        ]);
    }

    /**
     * Logout and revoke the token.
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    private function verifyOtpForPhone(string $normalizedPhone, string $code): bool
    {
        $otp = OtpRequest::where('phone', $normalizedPhone)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp || $otp->attempts >= 5) {
            return false;
        }

        $otp->increment('attempts');

        return Hash::check($code, $otp->code_hash);
    }

    private function passwordMatches(CrmUser $user, string $password): bool
    {
        $storedHash = (string) $user->Password;

        // 1. Check Bcrypt / Argon2 (Laravel defaults)
        if ($this->isModernPasswordHash($storedHash)) {
            return Hash::check($password, $storedHash);
        }

        // 2. Check MD5 (Legacy CRM)
        if (preg_match('/^[a-f0-9]{32}$/i', $storedHash)) {
            return hash_equals(strtolower($storedHash), md5($password));
        }

        // 3. Check SHA1 (Legacy CRM)
        if (preg_match('/^[a-f0-9]{40}$/i', $storedHash)) {
            return hash_equals(strtolower($storedHash), sha1($password));
        }

        // 4. Check Plaintext (as fallback, matching original logic)
        return hash_equals($storedHash, $password);
    }

    private function isModernPasswordHash(string $storedHash): bool
    {
        return str_starts_with($storedHash, '$2y$')
            || str_starts_with($storedHash, '$2a$')
            || str_starts_with($storedHash, '$argon2');
    }

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