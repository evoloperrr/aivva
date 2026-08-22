<?php

namespace App\Http\Controllers\Api;

use App\Domain\Marketplace\MarketplaceService;
use App\Http\Controllers\Controller;
use App\Models\Aivva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MarketplaceController extends Controller
{
    public function __construct(private readonly MarketplaceService $market) {}

    public function storeRequest(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:40'],
            'budget_min' => ['required', 'integer', 'min:1'],
            'budget_max' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $created = $this->market->createRequest($aivva->load('permissions'), $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $created], 201);
    }

    public function storeListing(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:40'],
            'price' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $created = $this->market->createListing($aivva->load('permissions'), $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $created], 201);
    }
}
