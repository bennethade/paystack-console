<?php

namespace App\Http\Controllers\Explorer;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request, Endpoint $endpoint): JsonResponse|RedirectResponse
    {
        abort_unless($endpoint->is_enabled, 404);

        $result = $request->user()->favoriteEndpoints()->toggle([$endpoint->id]);
        $isFavorite = in_array($endpoint->id, $result['attached'], true);

        if ($request->expectsJson()) {
            return response()->json(['favorite' => $isFavorite, 'key' => $endpoint->key]);
        }

        return back()->with('status', $isFavorite ? "{$endpoint->name} added to favorites." : "{$endpoint->name} removed from favorites.");
    }
}
