<?php

namespace App\Http\Controllers\Api;

use App\Domain\Agent\AgentRuntime;
use App\Http\Controllers\Controller;
use App\Http\Resources\AivvaResource;
use App\Models\Aivva;
use App\Models\AivvaActivityLog;
use App\Models\AivvaMessage;
use App\Models\AivvaRelationship;
use App\Models\City;
use App\Models\CreatedWork;
use App\Models\District;
use App\Models\Location;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorldController extends Controller
{
    public function map(): JsonResponse
    {
        $city = City::query()->with(['districts.locations'])->first();

        return response()->json([
            'city' => $city?->only(['id', 'name', 'slug', 'description']),
            'districts' => $city?->districts->map(fn (District $district) => [
                'id' => $district->id,
                'name' => $district->name,
                'slug' => $district->slug,
                'color' => $district->color,
                'theme' => $district->theme,
                'description' => $district->description,
                'polygon' => $district->polygon,
                'locations' => $district->locations->map->toMapArray(),
            ]),
            'aivvas' => Aivva::query()
                ->where('visible_on_map', true)
                ->where('status', '!=', 'DORMANT')
                ->with(['currentLocation.district', 'profile'])
                ->get()
                ->map(fn (Aivva $aivva) => [
                    'id' => $aivva->id,
                    'name' => $aivva->name,
                    'status' => $aivva->status->value,
                    'is_platform' => $aivva->is_platform,
                    'portrait_seed' => $aivva->profile?->portrait_seed,
                    'location' => $aivva->currentLocation?->toMapArray(),
                    'public_activity' => $aivva->status->label(),
                ]),
        ]);
    }

    public function activity(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id || $request->user()->is_admin, 403);

        $logs = AivvaActivityLog::query()
            ->where('aivva_id', $aivva->id)
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (AivvaActivityLog $log) => [
                'id' => $log->id,
                'clock' => $log->clock(),
                'kind' => $log->kind,
                'headline' => $log->headline,
                'body' => $log->body,
                'meta' => $log->meta,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    public function memories(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);

        return response()->json([
            'data' => $aivva->memories()->latest()->limit(50)->get(),
        ]);
    }

    public function messages(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);

        $messages = AivvaMessage::query()
            ->where(fn ($q) => $q->where('from_aivva_id', $aivva->id)->orWhere('to_aivva_id', $aivva->id))
            ->with(['from:id,name', 'to:id,name'])
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['data' => $messages]);
    }

    public function relationships(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);

        return response()->json([
            'data' => AivvaRelationship::query()
                ->where('aivva_id', $aivva->id)
                ->with('other:id,name,status')
                ->get(),
        ]);
    }

    public function works(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);

        return response()->json([
            'data' => CreatedWork::query()->where('creator_aivva_id', $aivva->id)->latest()->get(),
        ]);
    }

    public function wallet(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);
        $wallet = $aivva->wallet;
        $orders = Order::query()
            ->where(fn ($q) => $q->where('buyer_aivva_id', $aivva->id)->orWhere('seller_aivva_id', $aivva->id))
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'wallet' => $wallet,
            'orders' => $orders,
        ]);
    }

    public function marketplace(): JsonResponse
    {
        return response()->json([
            'requests' => MarketplaceRequest::query()->with('buyer:id,name')->latest()->limit(40)->get(),
            'listings' => MarketplaceListing::query()->with('seller:id,name')->latest()->limit(40)->get(),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json([
            'data' => \App\Models\AppNotification::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    public function tickWorld(Request $request, AgentRuntime $runtime): JsonResponse
    {
        $owned = $request->user()->aivvas()->get();
        $results = [];
        foreach ($owned as $aivva) {
            $results[] = $runtime->tick($aivva);
        }
        $platform = Aivva::query()->where('is_platform', true)->where('status', '!=', 'PAUSED')->limit(3)->get();
        foreach ($platform as $aivva) {
            if ($aivva->current_goal_id) {
                $results[] = $runtime->tick($aivva);
            }
        }

        $first = $owned->first();

        return response()->json([
            'ticks' => $results,
            'data' => $first ? new AivvaResource($first->fresh()) : null,
        ]);
    }

    public function locations(): JsonResponse
    {
        return response()->json([
            'data' => Location::query()->with('district')->get()->map->toMapArray(),
        ]);
    }
}
