<?php

namespace App\Http\Controllers\Api;

use App\Data\Order\CreateOrderData;
use App\Exceptions\Order\CrmIntegrationException;
use App\Exceptions\Order\CrmOrderException;
use App\Exceptions\Order\OrderAlreadyProcessingException;
use App\Exceptions\Order\OrderIdempotencyConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Services\Order\OrderApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /** @var OrderApplicationService */
    private $orderService;

    public function __construct(OrderApplicationService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * POST /api/orders
     *
     * Creates (or idempotently returns) an order.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $data = CreateOrderData::fromArray(
            $request->all(),
            (int) $request->user()->getKey(),
        );

        try {
            $result = $this->orderService->createOrder($data);
        } catch (OrderAlreadyProcessingException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'order_already_processing',
            ], 409);
        } catch (OrderIdempotencyConflictException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'idempotency_conflict',
            ], 409);
        } catch (CrmOrderException $e) {
            return response()->json([
                'message'    => $e->getMessage(),
                'code'       => 'crm_order_failed',
                'http_status'=> $e->httpStatus,
                'crm_errors' => $e->crmErrors,
            ], 422);
        } catch (CrmIntegrationException $e) {
            Log::error('CRM integration failure during order creation', [
                'guid'        => $data->guid,
                'http_status' => $e->httpStatus,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Serwis zamówień jest chwilowo niedostępny. Spróbuj ponownie.',
                'code'    => 'crm_integration_error',
            ], 503);
        }

        $httpStatus = $result->wasAlreadyProcessed ? 200 : 201;

        return response()->json([
            'data' => $result->toArray(),
        ], $httpStatus);
    }
}
