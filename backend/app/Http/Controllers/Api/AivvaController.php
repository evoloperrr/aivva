<?php

namespace App\Http\Controllers\Api;

use App\Domain\Aivva\AivvaService;
use App\Http\Controllers\Controller;
use App\Http\Resources\AivvaResource;
use App\Models\Aivva;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AivvaController extends Controller
{
    public function __construct(private readonly AivvaService $aivvas) {}

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->aivvas()->with(['profile', 'currentLocation.district', 'wallet', 'trustScore', 'currentGoal'])->latest()->get();

        return response()->json(['data' => AivvaResource::collection($items)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'personality' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:40'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'max:40'],
            'work_preferences' => ['nullable', 'array'],
            'work_preferences.*' => ['string', 'max:40'],
            'risk_tolerance' => ['nullable', 'in:low,moderate,high'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'portrait_seed' => ['nullable', 'string', 'max:80'],
            'home_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'autonomy_level' => ['nullable', 'integer', 'min:0', 'max:4'],
            'max_per_transaction' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'daily_spend_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $aivva = $this->aivvas->create($request->user(), $data);

        return response()->json(['data' => new AivvaResource($aivva)], 201);
    }

    public function show(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeOwner($request->user(), $aivva);

        return response()->json(['data' => new AivvaResource($aivva)]);
    }

    public function activate(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeOwner($request->user(), $aivva);

        return response()->json(['data' => new AivvaResource($this->aivvas->activate($aivva))]);
    }

    public function pause(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeOwner($request->user(), $aivva);

        return response()->json(['data' => new AivvaResource($this->aivvas->pause($aivva))]);
    }

    public function recall(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeOwner($request->user(), $aivva);

        return response()->json(['data' => new AivvaResource($this->aivvas->recallHome($aivva))]);
    }

    public function stopSpending(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeOwner($request->user(), $aivva);

        return response()->json(['data' => new AivvaResource($this->aivvas->stopSpending($aivva))]);
    }

    public function permissions(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeOwner($request->user(), $aivva);
        $data = $request->validate([
            'autonomy_level' => ['sometimes', 'integer', 'min:0', 'max:4'],
            'max_per_transaction' => ['sometimes', 'integer', 'min:0'],
            'daily_spend_limit' => ['sometimes', 'integer', 'min:0'],
            'can_travel' => ['sometimes', 'boolean'],
            'can_socialize' => ['sometimes', 'boolean'],
            'can_create' => ['sometimes', 'boolean'],
            'can_transact' => ['sometimes', 'boolean'],
            'autonomous_interaction' => ['sometimes', 'boolean'],
            'require_approval_above' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json(['data' => new AivvaResource($this->aivvas->updatePermissions($aivva, $data))]);
    }

    public function cancelGoal(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeOwner($request->user(), $aivva);

        return response()->json(['data' => new AivvaResource($this->aivvas->cancelGoal($aivva))]);
    }

    public function tick(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeOwner($request->user(), $aivva);

        return response()->json(['tick' => $this->aivvas->tick($aivva), 'data' => new AivvaResource($aivva->fresh())]);
    }

    public function live(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeOwner($request->user(), $aivva);

        $due = $aivva->status->isActive()
            && $aivva->current_goal_id
            && ($aivva->next_scheduled_at === null || $aivva->next_scheduled_at->lte(now()));

        $tick = $due ? $this->aivvas->tick($aivva) : ['ok' => true, 'idle' => true];
        $fresh = $aivva->fresh();

        return response()->json([
            'tick' => $tick,
            'data' => new AivvaResource($fresh),
            'activity' => $fresh->activityLogs()->latest()->limit(80)->get()->map(fn ($log) => [
                'id' => $log->id,
                'clock' => $log->clock(),
                'kind' => $log->kind,
                'headline' => $log->headline,
                'body' => $log->body,
                'meta' => $log->meta,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function meetup(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeOwner($request->user(), $aivva);

        $data = $request->validate([
            'target_aivva_id' => ['required', 'uuid', 'exists:aivvas,id'],
            'name' => ['required', 'string', 'max:60'],
            'x' => ['required', 'numeric', 'min:0', 'max:1000'],
            'y' => ['required', 'numeric', 'min:0', 'max:640'],
        ]);

        abort_if($data['target_aivva_id'] === $aivva->id, 422, 'Cannot meet itself.');

        $target = Aivva::query()->findOrFail($data['target_aivva_id']);

        $result = $this->aivvas->createMeetup($aivva, $target, $data['name'], (float) $data['x'], (float) $data['y']);

        return response()->json([
            'data' => new AivvaResource($result['initiator']),
            'target' => new AivvaResource($result['target']),
        ], 201);
    }

    private function authorizeOwner(User $user, Aivva $aivva): void
    {
        abort_unless($aivva->owner_id === $user->id || $user->is_admin, 403, 'Not your AIVVA.');
    }
}
