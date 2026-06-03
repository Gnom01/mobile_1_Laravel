<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmDashboardBannerRequest;
use App\Http\Requests\ReorderDashboardBannersRequest;
use App\Models\DashboardBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmDashboardBannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeCrm($request);

        return response()->json([
            'data' => DashboardBanner::ordered()->get(),
        ]);
    }

    public function store(CrmDashboardBannerRequest $request): JsonResponse
    {
        $this->authorizeCrm($request);

        $banner = DashboardBanner::create($request->validated());

        return response()->json(['data' => $banner], 201);
    }

    public function update(CrmDashboardBannerRequest $request, int $id): JsonResponse
    {
        $this->authorizeCrm($request);

        $banner = DashboardBanner::findOrFail($id);
        $banner->update($request->validated());

        return response()->json(['data' => $banner->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeCrm($request);

        DashboardBanner::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(ReorderDashboardBannersRequest $request): JsonResponse
    {
        $this->authorizeCrm($request);

        DB::transaction(function () use ($request) {
            foreach ($request->validated('items') as $item) {
                DashboardBanner::whereKey($item['id'])->update([
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => DashboardBanner::ordered()->get(),
        ]);
    }

    private function authorizeCrm(Request $request): void
    {
        $expected = config('services.crm.push_api_token');
        if (!$expected) {
            return;
        }

        abort_unless(hash_equals($expected, (string) $request->bearerToken()), 401, 'Invalid CRM token');
    }
}
