<?php

namespace App\Http\Controllers\Explorer;

use App\Http\Controllers\Controller;
use App\Services\Paystack\EndpointRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, EndpointRegistry $registry): JsonResponse
    {
        $query = mb_substr((string) $request->query('q', ''), 0, 100);

        return response()->json([
            'query' => $query,
            'results' => $registry->search($query, 25)->map(fn (array $e) => [
                'key' => $e['key'],
                'name' => $e['name'],
                'method' => $e['method'],
                'path' => $e['path'],
                'category' => $e['category'],
                'url' => url($e['url']),
            ])->values(),
        ]);
    }
}
