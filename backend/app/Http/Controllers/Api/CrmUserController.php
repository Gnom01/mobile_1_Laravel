<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Services\CrmClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmUserController extends Controller
{
    /**
     * Update the credentials (login and password) for a connected user.
     * POST /api/users/{guid}/credentials
     */
    public function updateCredentials(Request $request, string $guid, CrmClient $crmClient)
    {
        $request->validate([
            'login'    => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        $newLogin    = strtolower(trim($request->input('login')));
        $newPassword = trim((string) $request->input('password'));
        
        $parentUser = $request->user();

        // 1. Verify that the target user (by guid) belongs to the authenticated parent
        $isRelated = DB::table('usersrelations as ur')
            ->join('users as u', 'u.UsersID', '=', 'ur.UsersID')
            ->where('ur.Parent_UsersID', $parentUser->UsersID)
            ->where('u.guid', $guid)
            ->where('ur.Cancelled', 0)
            ->where('u.Cancelled', 0)
            ->exists();

        if (!$isRelated) {
            Log::warning('Unauthorized credentials update attempt', [
                'parent_id'   => $parentUser->UsersID,
                'target_guid' => $guid,
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Unauthorized access to this user',
            ], 403);
        }

        // 2. Fetch target user
        $targetUser = CrmUser::where('guid', $guid)->first();
        if (!$targetUser) {
            return response()->json([
                'ok'      => false,
                'message' => 'User not found',
            ], 404);
        }

        // 3. Send update to CRM
        $crmPayload = [
            'usersID'                       => $targetUser->UsersID,
            'active'                        => (string) $targetUser->Active,
            'dateOfBirdth'                  => $targetUser->DateOfBirdth ? $targetUser->DateOfBirdth->format('Y-m-d') : '0000-00-00',
            'postalCode'                    => $targetUser->PostalCode ?? '',
            'postPlace'                     => $targetUser->PostPlace ?? '',
            'memberCardNumber'              => $targetUser->MemberCardNumber ?? '',
            'lastName'                      => $targetUser->LastName ?? '',
            'firstName'                     => $targetUser->FirstName ?? '',
            'email'                         => $newLogin,
            'address'                       => $targetUser->address ?? '',
            'login'                         => $newLogin,
            'password'                      => $newPassword,
            'phone'                         => $targetUser->Phone ?? '',
            'city'                          => $targetUser->City ?? '',
            'default_LocalizationsID'       => (string) ($targetUser->Default_LocalizationsID ?? 0),
            'parent_UsersID'                => (int) ($targetUser->Parent_UsersID ?? 0),
            'street'                        => $targetUser->Street ?? '',
            'building'                      => $targetUser->Building ?? '',
            'flat'                          => $targetUser->Flat ?? '',
            'description'                   => $targetUser->Description ?? '',
            'genderDVID'                    => (int) ($targetUser->GenderDVID ?? 0),
            'genderName'                    => '',
            'identityNumber'                => $targetUser->IdentityNumber ?? '',
            'pesel'                         => $targetUser->Pesel ?? '',
            'entryFee'                      => (int) ($targetUser->entryFee ?? 0),
            'activationDate'                => $targetUser->ActivationDate ? $targetUser->ActivationDate->format('Y-m-d') : null,
            'paymentMethodsDVID'            => (int) ($targetUser->PaymentMethodsDVID ?? 0),
            'paymentMethodsName'            => '',
            'personalDataProcessingConsent' => (int) ($targetUser->PersonalDataProcessingConsent ?? 0),
            'consentReceiveSmsEmailPhone'   => (int) ($targetUser->consentReceiveSmsEmailPhone ?? 0),
            'marketingAgreement'            => (int) ($targetUser->marketingAgreement ?? 0),
            'fileName'                      => $targetUser->FileName ?? '',
            'fileExtension'                 => $targetUser->FileExtension ?? '',
            'positionsDVID'                 => 0,
            'employeesID'                   => 0,
            'statusUser'                    => $targetUser->statusUser ?? 'Lead',
            'colorStatus'                   => '',
            'userStatus'                    => (int) ($targetUser->UserStatus ?? 3),
            'bankAccount'                   => $targetUser->bankAccount ?? '',
            'fileURL'                       => '',
            'cancelled'                     => (string) ($targetUser->Cancelled ?? '0'),
            'birthPlace'                    => $targetUser->BirthPlace ?? '',
            'voivodeshipDVID'               => (string) ($targetUser->VoivodeshipDVID ?? ''),
            'comunity'                      => $targetUser->Comunity ?? '',
            'district'                      => $targetUser->District ?? '',
            'localizationsID'               => null,
            'localizationsIDArray'          => [$targetUser->Default_LocalizationsID ?? 0],
            'current_LocalizationsID'       => (string) ($targetUser->Default_LocalizationsID ?? 0),
        ];

        try {
            $crmResp = $crmClient->post('/CrmToMobileSync/setUsersForLocalization', $crmPayload);
            $crmData = $crmResp->json();

            Log::info('Subaccount credentials: CRM update response', [
                'target_guid' => $guid,
                'status'      => $crmResp->status(),
                'data'        => $crmData,
            ]);
            
            if (!$crmResp->successful()) {
                throw new \Exception('CRM returned non-success status: ' . $crmResp->status());
            }
        } catch (\Exception $e) {
            Log::error('Subaccount credentials: CRM update failed', [
                'target_guid' => $guid,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Failed to update credentials in CRM. Please try again.',
            ], 500);
        } catch (\Throwable $e) {
             Log::error('Subaccount credentials: CRM update failed', [
                'target_guid' => $guid,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Failed to update credentials in CRM. Please try again.',
            ], 500);
        }

        // 4. Sync from CRM to local DB
        try {
            \App\Jobs\PullUsersJob::dispatchSync();
        } catch (\Throwable $e) {
            Log::warning('Subaccount credentials: PullUsersJob failed after CRM update', [
                'target_guid' => $guid,
                'error'       => $e->getMessage(),
            ]);
            // Still ok to return success since CRM was updated
        }

        // 5. Revoke tokens for the updated user
        $targetUser->tokens()->delete();

        Log::info('Subaccount credentials updated successfully', [
            'parent_id'   => $parentUser->UsersID,
            'target_guid' => $guid,
            'new_login'   => $newLogin,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Credentials updated successfully',
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // ...
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // ...
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // ...
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // ...
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // ...
    }
}
