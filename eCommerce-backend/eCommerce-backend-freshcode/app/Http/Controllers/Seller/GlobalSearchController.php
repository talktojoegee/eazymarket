<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalSearchRequest;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;

class GlobalSearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request, GlobalSearchService $search): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $search->search($request->searchQuery(), 'seller', $request->user()),
        ]);
    }
}
