<?php

namespace App\Http\Controllers\Api;

use App\Enums\AivvaControlMode;
use App\Http\Controllers\Controller;
use App\Models\Aivva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AivvaRuntimeController extends Controller
{
    public function show(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeRuntime($request, $aivva);
        $aivva->load(['owner', 'profile', 'permissions', 'currentLocation.district', 'destinationLocation.district']);
        $link = $aivva->owner->xentozIdentityLink;

        return response()->json(['data' => [
            'id' => $aivva->id,
            'ownerUserId' => $link?->xentoz_user_id,
            'displayName' => $aivva->name,
            'controlMode' => $aivva->control_mode->value,
            'appearance' => $aivva->profile?->appearance ?? [],
            'animationProfile' => $aivva->profile?->animation_profile,
            'personality' => [
                'summary' => $aivva->profile?->personality,
                'skills' => $aivva->profile?->skills ?? [],
                'interests' => $aivva->profile?->interests ?? [],
            ],
            'state' => ['status' => $aivva->status->value, 'energy' => $aivva->energy],
            'location' => ['current' => $aivva->currentLocation?->toMapArray(), 'destination' => $aivva->destinationLocation?->toMapArray()],
            'activity' => ['lastActivityAt' => $aivva->last_activity_at?->toIso8601String(), 'nextScheduledAt' => $aivva->next_scheduled_at?->toIso8601String()],
            'aiPermissions' => $this->aiPermissions($aivva),
            'version' => $aivva->state_version,
        ]]);
    }

    public function controlMode(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeRuntime($request, $aivva);
        $data = $request->validate(['controlMode' => ['required', 'in:HUMAN,AI_TWIN']]);
        if ($data['controlMode'] === AivvaControlMode::AiTwin->value && ! config('aivva.runtime.ai_control_enabled')) {
            return response()->json(['message' => 'AI Twin control is disabled.'], 503);
        }
        $aivva->control_mode = AivvaControlMode::from($data['controlMode']);
        $aivva->state_version++;
        $aivva->save();
        return response()->json(['data' => ['id' => $aivva->id, 'controlMode' => $aivva->control_mode->value, 'version' => $aivva->state_version]]);
    }

    public function scene(Request $request, Aivva $aivva): JsonResponse
    {
        $this->authorizeRuntime($request, $aivva);
        $characters = Aivva::query()->with('profile')->where('visible_on_map', true)
            ->where(fn ($query) => $query->whereKey($aivva->id)->orWhere('is_platform', true))
            ->limit(12)->get()->map(fn (Aivva $character) => [
                'id' => $character->id,
                'displayName' => $character->name,
                'controlMode' => $character->control_mode->value,
                'appearance' => $character->profile?->appearance ?? [],
                'state' => $character->status->value,
                'version' => $character->state_version,
            ])->values();
        return response()->json(['data' => ['characters' => $characters]]);
    }

    private function authorizeRuntime(Request $request, Aivva $aivva): void
    {
        abort_unless(config('aivva.runtime.enabled'), 503, 'AIVVA runtime is disabled.');
        abort_unless($request->user()?->tokenCan('aivva:runtime'), 403, 'Runtime scope required.');
        abort_unless($aivva->owner_id === $request->user()?->id, 403, 'Not your AIVVA.');
    }

    /** @return array<string, bool> */
    private function aiPermissions(Aivva $aivva): array
    {
        $permissions = $aivva->permissions;
        return [
            'movement' => (bool) $permissions?->allow_movement,
            'socialInteraction' => (bool) $permissions?->allow_social_interaction,
            'publicChat' => (bool) $permissions?->allow_public_chat,
            'privateChat' => (bool) $permissions?->allow_private_chat,
            'purchases' => (bool) $permissions?->allow_purchases,
            'wallet' => (bool) $permissions?->allow_wallet && (bool) config('aivva.ue.wallet_enabled'),
            'creation' => (bool) $permissions?->allow_creation,
            'work' => (bool) $permissions?->allow_work,
            'invites' => (bool) $permissions?->allow_invites,
        ];
    }
}
