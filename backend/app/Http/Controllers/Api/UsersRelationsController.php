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
    public function getRelatedUsers($parentGuid)
    {
        $relatedUsers = DB::table('usersrelations as ur')
            ->leftJoin('users as u', function ($join) {
                $join->on('u.UsersID', '=', 'ur.UsersID')
                    ->where('u.Cancelled', '=', 0);
            })
            ->where('ur.Parent_UsersID', function ($query) use ($parentGuid) {
                $query->select('p.UsersID')
                    ->from('users as p')
                    ->where('p.guid', $parentGuid)
                    ->where('p.Cancelled', 0)
                    ->limit(1);
            })
            ->where('ur.Cancelled', 0)
            ->select('u.fullName', 'u.Pesel', 'u.address', 'u.Phone', 'u.Email')
            ->get();

        return response()->json($relatedUsers);
    }
}
