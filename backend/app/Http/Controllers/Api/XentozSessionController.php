<?php

namespace App\Http\Controllers\Api;

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
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'xentozUserId' => ['required', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'emailVerified' => ['accepted'],
        ]);

        [$user, $link] = DB::transaction(function () use ($data): array {
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

        $expiresAt = now()->addMinutes((int) config('aivva.ue.token_ttl_minutes', 15));
        $token = $user->createToken('xentoz-unreal', ['aivva:read', 'aivva:runtime'], $expiresAt);
        $characters = $user->aivvas()->select(['id', 'name', 'status'])->get();

        return response()->json([
            'accessToken' => $token->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresAt' => $expiresAt->toIso8601String(),
            'xentozUserId' => $link->xentoz_user_id,
            'characters' => $characters,
        ]);
    }
}
