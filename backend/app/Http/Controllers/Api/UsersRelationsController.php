<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsersRelationsController extends Controller
{
    /**
     * Get related users by parent GUID.
     *
     * @param string $parentGuid
     * @return \Illuminate\Http\JsonResponse
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
}
