<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PdfGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PdfController extends Controller
{
    public function __construct(private PdfGeneratorService $pdfService) {}

    /**
     * POST /api/mobile/pdf/generate
     *
     * Body: {
     *   "type": "contract" | "annex" | "schedule",
     *   "filename": "optional_filename",
     *   "contractData": { ... }
     * }
     *
     * Returns a PDF file download.
     */
    public function generate(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        // Accept both camelCase (contractData) and snake_case (contract_data)
        $contractData = $request->input('contractData') ?? $request->input('contract_data');

        $request->validate([
            'type' => 'required|string|in:contract,annex,schedule',
        ]);

        if (empty($contractData) || !is_array($contractData)) {
            return response()->json([
                'message' => 'The contract data field is required.',
                'errors'  => ['contractData' => ['The contract data field is required (use contractData or contract_data).']],
            ], 422);
        }

        $type     = $request->input('type');
        $filename = $request->input('filename') ?? $request->input('file_name');

        if (!$filename) {
            $filename = match ($type) {
                'annex'    => 'Wzor_Aneks',
                'schedule' => 'Wzor_Harmonogram',
                default    => 'Wzor_UmowaEDS',
            };
        }

        try {
            $pdf = $this->pdfService->generate($type, $contractData);
            return $pdf->download($filename . '.pdf');
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'PDF generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/mobile/pdf/preview
     *
     * Same as generate but returns inline (for WebView display in Flutter).
     */
    public function preview(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        // Accept both camelCase (contractData) and snake_case (contract_data)
        $contractData = $request->input('contractData') ?? $request->input('contract_data');

        $request->validate([
            'type' => 'required|string|in:contract,annex,schedule',
        ]);

        if (empty($contractData) || !is_array($contractData)) {
            return response()->json([
                'message' => 'The contract data field is required.',
                'errors'  => ['contractData' => ['The contract data field is required (use contractData or contract_data).']],
            ], 422);
        }

        $type = $request->input('type');

        try {
            $pdf = $this->pdfService->generate($type, $contractData);
            return $pdf->stream();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'PDF generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
