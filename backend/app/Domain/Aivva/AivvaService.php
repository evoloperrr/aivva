<?php

namespace App\Domain\Aivva;

use App\Domain\Agent\AgentRuntime;
use App\Domain\Agent\Planner;
use App\Domain\Economy\WalletService;
use App\Domain\Goals\GoalInterpreter;
use App\Domain\Memory\MemoryService;
use App\Domain\Trust\TrustService;
use App\Domain\World\MovementService;
use App\Enums\AivvaStatus;
use App\Enums\GoalStatus;
use App\Enums\MemoryCategory;
use App\Models\Aivva;
use App\Models\AivvaActivityLog;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Str;

class AivvaService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly TrustService $trust,
        private readonly MemoryService $memory,
        private readonly GoalInterpreter $goals,
        private readonly Planner $planner,
        private readonly AgentRuntime $runtime,
        private readonly MovementService $movement,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $owner, array $input): Aivva
    {
        $name = trim((string) $input['name']);
        $home = Location::query()->where('is_home_template', true)->first()
            ?? Location::query()->where('type', 'home')->first()
            ?? Location::query()->firstOrFail();

        $aivva = Aivva::query()->create([
            'owner_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => AivvaStatus::Dormant,
            'current_location_id' => $home->id,
            'home_location_id' => $home->id,
            'energy' => 100,
            'world_minutes' => 480,
            'is_platform' => false,
        ]);

        $aivva->profile()->create([
            'personality' => $input['personality'] ?? 'Curious, careful, and warm.',
            'skills' => $input['skills'] ?? ['curiosity'],
            'interests' => $input['interests'] ?? ['the city'],
            'work_preferences' => $input['work_preferences'] ?? ['ethical work'],
            'risk_tolerance' => $input['risk_tolerance'] ?? 'moderate',
            'bio' => $input['bio'] ?? null,
            'portrait_seed' => $input['portrait_seed'] ?? Str::slug($name).'-'.substr($aivva->id, 0, 8),
            'privacy' => [
                'visible' => true,
                'contactable' => true,
                'location_public' => true,
            ],
        ]);

        $defaults = config('aivva.default_permissions');
        $aivva->permissions()->create(array_merge($defaults, [
            'autonomy_level' => (int) ($input['autonomy_level'] ?? $defaults['autonomy_level']),
            'max_per_transaction' => (int) ($input['max_per_transaction'] ?? $defaults['max_per_transaction']),
            'daily_spend_limit' => (int) ($input['daily_spend_limit'] ?? $defaults['daily_spend_limit']),
        ]));

        $this->wallets->issueStarterCredits($aivva);
        $this->trust->ensure($aivva);
        $this->memory->remember(
            $aivva,
            MemoryCategory::LongTerm,
            "Born in AIVVA Genesis City. Home is {$home->name}.",
            8,
        );

        AivvaActivityLog::query()->create([
            'aivva_id' => $aivva->id,
            'kind' => 'birth',
            'headline' => "{$aivva->name} arrived in Genesis City.",
            'world_minutes' => $aivva->world_minutes,
        ]);

        return $aivva->fresh(['profile', 'permissions', 'currentLocation.district', 'wallet', 'trustScore']);
    }

    public function activate(Aivva $aivva): Aivva
    {
        if ($aivva->status === AivvaStatus::Paused) {
            $aivva->paused_at = null;
        }
        $aivva->status = AivvaStatus::Idle;
        $aivva->activated_at = $aivva->activated_at ?? now();
        $aivva->next_scheduled_at = now();
        $aivva->save();

        AivvaActivityLog::query()->create([
            'aivva_id' => $aivva->id,
            'kind' => 'status',
            'headline' => "{$aivva->name} is now awake and can act within permissions.",
            'world_minutes' => $aivva->world_minutes,
        ]);

        return $aivva->fresh();
    }

    public function pause(Aivva $aivva): Aivva
    {
        $aivva->status = AivvaStatus::Paused;
        $aivva->paused_at = now();
        $aivva->save();

        AivvaActivityLog::query()->create([
            'aivva_id' => $aivva->id,
            'kind' => 'status',
            'headline' => "{$aivva->name} was paused by the owner.",
            'world_minutes' => $aivva->world_minutes,
        ]);

        return $aivva->fresh();
    }

    /**
     * @return array{goal: \App\Models\AivvaGoal, interpretation: array<string, mixed>}
     */
    public function previewDirection(Aivva $aivva, string $direction): array
    {
        return $this->goals->interpret($aivva, $direction);
    }

    public function confirmDirection(Aivva $aivva, string $goalId): Aivva
    {
        $goal = $aivva->goals()->whereKey($goalId)->firstOrFail();
        if ($goal->rejected) {
            return $aivva;
        }

        $aivva->goals()->where('status', GoalStatus::Active)->update(['status' => GoalStatus::Cancelled]);
        $goal->status = GoalStatus::Active;
        $goal->confirmed_at = now();
        $goal->save();

        $plan = $this->planner->createPlan($aivva, $goal);
        $aivva->current_goal_id = $goal->id;
        $aivva->current_plan_id = $plan->id;
        if ($aivva->status === AivvaStatus::Dormant) {
            $this->activate($aivva);
        }
        $aivva->next_scheduled_at = now();
        $aivva->save();

        AivvaActivityLog::query()->create([
            'aivva_id' => $aivva->id,
            'kind' => 'goal',
            'headline' => "{$aivva->name} accepted a new direction: {$goal->goal_type}.",
            'body' => $goal->raw_direction,
            'world_minutes' => $aivva->world_minutes,
        ]);

        $this->memory->remember($aivva, MemoryCategory::Goal, 'New owner direction: '.$goal->raw_direction, 8);

        return $aivva->fresh(['currentGoal', 'currentPlan']);
    }

    public function cancelGoal(Aivva $aivva): Aivva
    {
        if ($aivva->currentGoal) {
            $aivva->currentGoal->status = GoalStatus::Cancelled;
            $aivva->currentGoal->save();
        }
        $aivva->current_goal_id = null;
        $aivva->current_plan_id = null;
        $aivva->save();

        return $aivva;
    }

    public function recallHome(Aivva $aivva): Aivva
    {
        if ($aivva->homeLocation) {
            $this->movement->startTravel($aivva, $aivva->homeLocation);
        }

        return $aivva->fresh();
    }

    public function stopSpending(Aivva $aivva): Aivva
    {
        $aivva->permissions->update([
            'can_transact' => false,
            'daily_spend_limit' => 0,
        ]);

        return $aivva->fresh('permissions');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updatePermissions(Aivva $aivva, array $input): Aivva
    {
        $aivva->permissions->fill($input)->save();

        return $aivva->fresh('permissions');
    }

    /**
     * @return array<string, mixed>
     */
    public function tick(Aivva $aivva): array
    {
        return $this->runtime->tick($aivva);
    }
}
