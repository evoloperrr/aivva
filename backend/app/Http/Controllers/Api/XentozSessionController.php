<?php

namespace App\Http\Controllers\Api;

use App\Domain\Aivva\AivvaService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\XentozIdentityLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class XentozSessionController extends Controller
{
    public function __construct(private readonly AivvaService $aivvas) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'xentozUserId' => ['required', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'emailVerified' => ['accepted'],
        ]);

        [$user, $link] = $this->resolveIdentity($data);

        $expiresAt = now()->addMinutes((int) config('aivva.runtime.token_ttl_minutes', 15));
        $token = $user->createToken('xentoz-runtime', ['aivva:read', 'aivva:runtime'], $expiresAt);
        $characters = $user->aivvas()->select(['id', 'name', 'status'])->get();

        return response()->json([
            'accessToken' => $token->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresAt' => $expiresAt->toIso8601String(),
            'xentozUserId' => $link->xentoz_user_id,
            'characters' => $characters,
        ]);
    }

    public function provision(Request $request): JsonResponse
    {
        $data = $request->validate([
            'xentozUserId' => ['required', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'emailVerified' => ['accepted'],
            'displayName' => ['required', 'string', 'min:2', 'max:32', 'regex:/^[\\pL\\pN][\\pL\\pN \'-]*$/u'],
        ]);

        [$user] = $this->resolveIdentity($data);

        [$aivva, $created] = DB::transaction(function () use ($user, $data): array {
            $existing = $user->aivvas()->orderBy('created_at')->lockForUpdate()->first();
            if ($existing) {
                return [$existing, false];
            }

            $aivva = $this->aivvas->create($user, [
                'name' => trim($data['displayName']),
                'personality' => 'Curious, careful, and warm.',
                'skills' => ['curiosity'],
                'interests' => ['the city'],
            ]);
            $this->aivvas->activate($aivva);

            return [$aivva, true];
        }, 3);

        return response()->json([
            'aivva' => [
                'id' => $aivva->id,
                'displayName' => $aivva->name,
                'controlMode' => $aivva->control_mode->value,
                'status' => $aivva->status->value,
                'appearance' => $aivva->profile?->appearance,
            ],
            'created' => $created,
        ], $created ? 201 : 200);
    }

    /**
     * Resolves the immutable owner link from an assertion signed by Xentoz's
     * authenticated server. Browser clients cannot choose or submit an owner id.
     *
     * @param array<string, mixed> $data
     * @return array{0: User, 1: XentozIdentityLink}
     */
    private function resolveIdentity(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $link = XentozIdentityLink::query()->where('xentoz_user_id', $data['xentozUserId'])->lockForUpdate()->first();
            if ($link) {
                $user = User::query()->findOrFail($link->user_id);
                abort_unless(strcasecmp($user->email, $data['email']) === 0, 409, 'Xentoz identity assertion conflicts with the existing link.');
                $link->last_asserted_at = now();
                $link->save();
                return [$user, $link];
            }

            $user = User::query()->where('email', strtolower($data['email']))->lockForUpdate()->first();
            if (! $user) {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => strtolower($data['email']),
                    'password' => Hash::make(Str::random(64)),
                ]);
                $user->email_verified_at = now();
                $user->save();
            }
            abort_if(XentozIdentityLink::query()->where('user_id', $user->id)->exists(), 409, 'This AIVVA owner is already linked to another Xentoz identity.');

            $link = new XentozIdentityLink();
            $link->user_id = $user->id;
            $link->xentoz_user_id = $data['xentozUserId'];
            $link->linked_at = now();
            $link->last_asserted_at = now();
            $link->save();
            return [$user, $link];
        }, 3);
    }
}
