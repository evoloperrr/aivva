<?php

namespace App\Http\Resources;

use App\Domain\World\MovementService;
use App\Models\Aivva;
use App\Models\AivvaDailyBudget;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Aivva */
class AivvaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'profile', 'permissions', 'currentGoal', 'currentPlan',
            'currentLocation.district.city', 'destinationLocation.district',
            'homeLocation.district', 'wallet', 'trustScore',
        ]);

        $wallet = $this->wallet;
        $today = AivvaDailyBudget::todayFor($this->resource);
        $earnedToday = (int) Order::query()
            ->where('seller_aivva_id', $this->id)
            ->where('status', 'SETTLED')
            ->whereDate('updated_at', now()->toDateString())
            ->sum('amount');

        $movement = app(MovementService::class)->locationProgress($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'world_clock' => $this->worldClock(),
            'energy' => $this->energy,
            'life_points' => $this->life_points,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'next_scheduled_at' => $this->next_scheduled_at?->toIso8601String(),
            'profile' => $this->profile,
            'permissions' => $this->permissions,
            'goal' => $this->currentGoal,
            'plan' => $this->currentPlan,
            'location' => $this->currentLocation?->toMapArray(),
            'home' => $this->homeLocation?->toMapArray(),
            'movement' => $movement,
            'wallet' => [
                'available' => $wallet?->available_balance ?? 0,
                'held' => $wallet?->held_balance ?? 0,
                'currency' => $wallet?->currency ?? 'AIVVA_CREDITS',
                'earned_today' => $earnedToday,
                'spent_today' => $today->spend_used,
            ],
            'trust' => $this->trustScore,
            'budgets' => [
                'actions_used' => $today->actions_used,
                'actions_limit' => $this->permissions?->daily_action_budget,
                'tokens_used' => $today->tokens_used,
                'spend_used' => $today->spend_used,
                'spend_limit' => $this->permissions?->daily_spend_limit,
            ],
        ];
    }
}
