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
        // Zapis robi kilka sekwencyjnych round-tripów do CRM (createOrder →
        // harmonogram rat → inicjacja płatności) + sync zwrotny. Domyślne
        // max_execution_time (30s) bywa za krótkie i ZABIJA skrypt już PO
        // sukcesie w CRM, zanim zwróci payment_url — w aplikacji objawia się to
        // wiecznym kręcącym się kółkiem (klient nie dostaje żadnej odpowiedzi).
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            $data = CreateOrderData::fromArray(
                $request->all(),
                (int) $request->user()->getKey(),
            );
        } catch (\Throwable $e) {
            Log::error('OrderController: błąd podczas budowania CreateOrderData', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'payload'   => $request->except(['password']),
            ]);
            return response()->json([
                'message' => 'Błąd przetwarzania danych zamówienia.',
                'code'    => 'order_data_error',
                'detail'  => $e->getMessage(),
            ], 500);
        }

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
        } catch (\Throwable $e) {
            Log::error('OrderController: nieoczekiwany błąd podczas tworzenia zamówienia', [
                'guid'      => $data->guid ?? null,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Nieoczekiwany błąd serwera.',
                'code'    => 'server_error',
                'detail'  => $e->getMessage(),
            ], 500);
        }

        $httpStatus = $result->wasAlreadyProcessed ? 200 : 201;

        return response()->json([
            'data' => $result->toArray(),
        ], $httpStatus);
    }
}
