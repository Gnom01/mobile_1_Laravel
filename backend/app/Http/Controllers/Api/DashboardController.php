<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DashboardBanner;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function banners(): JsonResponse
    {
        return response()->json([
            'data' => DashboardBanner::active()->get(),
        ]);
    }
}
