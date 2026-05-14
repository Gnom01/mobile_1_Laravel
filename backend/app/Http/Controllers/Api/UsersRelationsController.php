<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PullUsersJob;
use App\Jobs\PullUsersRelationsJob;
use App\Services\CrmClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsersRelationsController extends Controller
{
    /**
     * GET /api/users-relations/{parentGuid}
     *
     * Returns list of participants (children) related to the authenticated parent.
     */
    public function getRelatedUsers(Request $request, $parentGuid)
    {
        $authUser = $request->user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'error' => 'UNAUTHORIZED',
            ], 401);
        }

        $parent = DB::table('users')
            ->where('guid', $parentGuid)
            ->where('Cancelled', 0)
            ->first();

        if (!$parent) {
            return response()->json([
                'success' => false,
                'error' => 'USER_NOT_FOUND',
            ], 404);
        }

        if ((int) $parent->UsersID !== (int) $authUser->UsersID) {
            return response()->json([
                'success' => false,
                'error' => 'FORBIDDEN',
            ], 403);
        }

        $relatedUsers = DB::table('usersrelations as ur')
            ->leftJoin('users as u', function ($join) {
                $join->on('u.UsersID', '=', 'ur.UsersID')
                    ->where('u.Cancelled', '=', 0);
            })
            ->where('ur.Parent_UsersID', $authUser->UsersID)
            ->where('ur.Cancelled', 0)
            ->select(
                'u.fullName',
                'u.FirstName',
                'u.LastName',
                'u.DateOfBirdth',
                'u.Phone',
                'u.Email',
                'u.guid'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => $relatedUsers,
        ]);
    }

    /**
     * POST /api/users-relations
     *
     * Creates a new participant (child account) linked to the authenticated parent.
     *
     * Body (JSON):
     *   firstName                       string  required
     *   lastName                        string  required
     *   dateOfBirth                     string  required  Y-m-d
     *   pesel                           string  optional
     *   genderDVID                      int     optional  (0 = unset)
     *   personalDataProcessingConsent   int     optional  0|1
     *   consentReceiveSmsEmailPhone     int     optional  0|1
     *   marketingAgreement              int     optional  0|1
     *   phone                           string  optional
     *   email                           string  optional
     */
    public function store(Request $request, CrmClient $crmClient)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'firstName'                      => ['required', 'string', 'max:100'],
            'lastName'                       => ['required', 'string', 'max:100'],
            'dateOfBirth'                    => ['required', 'date_format:Y-m-d'],
            'pesel'                          => ['sometimes', 'nullable', 'string', 'max:11'],
            'genderDVID'                     => ['sometimes', 'nullable', 'integer'],
            'personalDataProcessingConsent'  => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'consentReceiveSmsEmailPhone'    => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'marketingAgreement'             => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'phone'                          => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'                          => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        $crmPayload = [
            'usersID'                       => 0,
            'active'                        => '1',
            'dateOfBirdth'                  => $validated['dateOfBirth'],
            'postalCode'                    => '',
            'postPlace'                     => '',
            'memberCardNumber'              => '',
            'lastName'                      => $validated['lastName'],
            'firstName'                     => $validated['firstName'],
            'email'                         => $validated['email'] ?? '',
            'address'                       => '',
            'login'                         => $validated['email'] ?? '',
            'password'                      => '',
            'phone'                         => $validated['phone'] ?? '',
            'city'                          => '',
            'default_LocalizationsID'       => (string) ($authUser->Default_LocalizationsID ?? 0),
            'parent_UsersID'                => (int) $authUser->UsersID,
            'street'                        => '',
            'building'                      => '',
            'flat'                          => '',
            'description'                   => '',
            'genderDVID'                    => (int) ($validated['genderDVID'] ?? 0),
            'genderName'                    => '',
            'identityNumber'                => '',
            'pesel'                         => $validated['pesel'] ?? '',
            'entryFee'                      => 0,
            'activationDate'                => null,
            'paymentMethodsDVID'            => 0,
            'paymentMethodsName'            => '',
            'personalDataProcessingConsent' => (int) ($validated['personalDataProcessingConsent'] ?? 0),
            'consentReceiveSmsEmailPhone'   => (int) ($validated['consentReceiveSmsEmailPhone'] ?? 0),
            'marketingAgreement'            => (int) ($validated['marketingAgreement'] ?? 0),
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
            'localizationsIDArray'          => [(int) ($authUser->Default_LocalizationsID ?? 0)],
            'current_LocalizationsID'       => (string) ($authUser->Default_LocalizationsID ?? 0),
        ];

        try {
            $crmResp = $crmClient->post('/CrmToMobileSync/setUsersForLocalization', $crmPayload);

            Log::info('UsersRelations store: CRM response', [
                'parent_id' => $authUser->UsersID,
                'status'    => $crmResp->status(),
            ]);

            if (!$crmResp->successful()) {
                throw new \Exception('CRM returned non-success status: ' . $crmResp->status());
            }
        } catch (\Exception $e) {
            Log::error('UsersRelations store: CRM call failed', [
                'parent_id' => $authUser->UsersID,
                'error'     => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się zapisać uczestnika w systemie. Spróbuj ponownie.',
            ], 500);
        }

        // Sync updated users and relations back from CRM
        try {
            PullUsersJob::dispatch();
            PullUsersRelationsJob::dispatch();
        } catch (\Throwable $e) {
            Log::warning('UsersRelations store: sync jobs failed after CRM write', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Uczestnik został dodany.',
        ], 201);
    }
}
