<?php

namespace App\Http\Controllers\Api;

use App\Models\CrmUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController
{
    /**
     * Handle login request and return API token.
     */
    public function login(Request $request)
    {

        //  \App\Jobs\PullUsersRelationsJob::dispatchSync();
        $request->validate([
            'Email' => 'required|email',
            'Password' => 'required',
            'current_LocalizationsID' => 'sometimes|integer',
        ]);

        $email = $request->input('Email');
        $password = $request->input('Password');

        $user = CrmUser::where('login', $email)
            ->orWhere('Email', $email)
            ->first();

        // Match CRM's authentication logic: try password_verify first, then plaintext comparison
        if (!$user || !(Hash::check($password, $user->Password) || $user->Password === $password)) {
            \Illuminate\Support\Facades\Log::warning('Login failed', [
                'email' => $email,
                'user_exists' => (bool) $user,
            ]);
            throw ValidationException::withMessages([
                'Email' => ['The provided credentials are incorrect.'],
            ]);
        }

        \Illuminate\Support\Facades\Log::info('Login successful', [
            'email' => $email,
            'user_id' => $user->UsersID,
        ]);

        // Usuń poprzednie tokeny (opcjonalnie)
        $user->tokens()->delete();

        // Utworz nowy token
        $token = $user->createToken('api-token')->plainTextToken;


        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'guid' => $user->guid,
                'email' => $user->Email,
                'first_name' => $user->FirstName,
                'last_name' => $user->LastName,
                'role' => 2,
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
            'Password' => 'required|string|min:8',
            'PersonalDataProcessingConsent' => 'nullable|boolean',
            'consentReceiveSmsEmailPhone' => 'nullable|boolean',
            'marketingAgreement' => 'nullable|boolean',
            'current_LocalizationsID' => 'nullable|integer',
        ]);

        $email = $request->input('Email');
        $phone = $request->input('Phone');
        $localizationId = $request->input('current_LocalizationsID', 3);
        
        // Check if user already exists locally
        
        if (CrmUser::where('login', $email)->orWhere('Email', $email)->exists()) {
            throw ValidationException::withMessages([
                'Email' => ['A user with this email already exists.'],
            ]);
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
            'password'                      => $request->input('Password'),
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

        \Illuminate\Support\Facades\Log::info('Registering user in CRM', [
            'email' => $email,
            'phone' => $phone,
        ]);

        try {
            $crmResp = $crmClient->post('/Users/setUsersForLocalization', $crmPayload);
            $crmData = $crmResp->json();

            \Illuminate\Support\Facades\Log::info('CRM registration response', [
                'status' => $crmResp->status(),
                'data'   => $crmData,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('CRM registration failed', [
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
            \Illuminate\Support\Facades\Log::warning('PullUsersJob failed after registration', [
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
            \Illuminate\Support\Facades\Log::error('User not found in local DB after CRM registration + sync', [
                'email' => $email,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'User was created in CRM but sync to local DB failed. Please try logging in.',
            ], 500);
        }

        // Revoke old tokens & create new one
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        \Illuminate\Support\Facades\Log::info('Registration successful', [
            'email'   => $email,
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
                'role' => 2,
            ],
        ]);
    }

    /**
     * Get the authenticated user's profile or a specific user's profile by usersID.
     */
    public function profile(Request $request)
    {
        // DEBUG: Trigger PullPaymentsJob locally
        \App\Jobs\PullPaymentsJob::dispatchSync();
        
        $guid = $request->input('guid');

        if ($guid) {
            $user = CrmUser::where('guid', $guid)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
        } else {
            $user = $request->user();
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
                'Pesel' => $user->Pesel,
                'GenderDVID' => $user->GenderDVID,
                'MemberCardNumber' => $user->MemberCardNumber,
                'address' => $user->address,
            ],
        ]);
    }

    /**
     * Get the user's consents (permissions).
     */
    public function consents(Request $request)
    {
        $guid = $request->input('guid');

        if ($guid) {
            $user = CrmUser::where('guid', $guid)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
        } else {
            $user = $request->user();
        }

        return response()->json([
            'success' => true,
            'consents' => [
                'PersonalDataProcessingConsent' => (bool) $user->PersonalDataProcessingConsent,
                'consentReceiveSmsEmailPhone' => (bool) $user->consentReceiveSmsEmailPhone,
                'marketingAgreement' => (bool) $user->marketingAgreement,
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
}
