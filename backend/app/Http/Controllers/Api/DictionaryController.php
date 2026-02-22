<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dictionary;
use Illuminate\Http\Request;

class DictionaryController extends Controller
{
    /**
     * Get dictionary items, optionally filtered by name.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Dictionary::query()->where('Cancelled', 0);

        if ($request->has('dictionaryName')) {
            $query->where('DictionaryName', $request->get('dictionaryName'));
        }

        if ($request->has('parentDictionaryName')) {
            $query->where('Parent_DictionaryName', $request->get('parentDictionaryName'));
        }

        $dictionaries = $query->orderBy('DictionaryName')
            ->orderBy('OrderPosition')
            ->get();

        return response()->json($dictionaries);
    }
}
