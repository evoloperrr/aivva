<?php

namespace App\Domain\Agent;

use App\Domain\Chat\PeerConversationService;
use App\Domain\World\MovementService;
use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Enums\AivvaStatus;
use App\Enums\GoalStatus;
use App\Models\Aivva;
use App\Models\AivvaAction;
use App\Models\AivvaActivityLog;
use App\Models\AivvaDailyBudget;
use Illuminate\Support\Str;

class AgentRuntime
{
    public function __construct(
        private readonly Planner $planner,
        private readonly ActionValidator $validator,
        private readonly ActionExecutor $executor,
        private readonly MovementService $movement,
        private readonly PeerConversationService $conversations,
    ) {}

    /**
     * One bounded iteration. Never an uncontrolled reasoning loop.
     *
     * @return array<string, mixed>
     */
    public function tick(Aivva $aivva): array
    {
        $aivva->load(['profile', 'permissions', 'currentGoal', 'currentPlan', 'currentLocation', 'destinationLocation', 'wallet', 'homeLocation']);

        if ($aivva->status === AivvaStatus::Paused) {
            return ['ok' => false, 'reason' => 'Paused AIVVA cannot act.', 'aivva_id' => $aivva->id];
        }
        if ($aivva->status === AivvaStatus::Dormant) {
            return ['ok' => false, 'reason' => 'AIVVA is dormant.', 'aivva_id' => $aivva->id];
        }

        $pending = $this->conversations->pendingFor($aivva);
        if ($pending) {
            $turn = $this->conversations->processTurn($pending, $aivva);
            $this->scheduleNext($aivva->fresh());

            return ['ok' => (bool) ($turn['ok'] ?? false), 'peer_turn' => $turn, 'aivva_id' => $aivva->id];
        }

        $travel = $this->movement->completeIfDue($aivva);
        $aivva->refresh()->load(['currentGoal', 'currentPlan', 'currentLocation', 'permissions', 'profile', 'wallet']);

        if ($travel && $travel->status === 'TRAVELING') {
            $aivva->status = AivvaStatus::Traveling;
            $aivva->save();

            return [
                'ok' => true,
                'waiting' => 'travel',
                'arrives_at' => $travel->arrives_at->toIso8601String(),
                'aivva_id' => $aivva->id,
            ];
        }

        if ($travel && $travel->status === 'ARRIVED' && $travel->completed_at?->gte(now()->subSeconds(2))) {
            $location = $aivva->currentLocation;
            $this->log($aivva, null, 'arrive', "{$aivva->name} arrived at {$location?->name}.");
        }

        $budget = AivvaDailyBudget::todayFor($aivva);
        $permissions = $aivva->permissions;
        if ($permissions && $budget->actions_used >= $permissions->daily_action_budget) {
            $aivva->status = AivvaStatus::Idle;
            $aivva->next_scheduled_at = now()->addHour();
            $aivva->save();

            return ['ok' => false, 'reason' => 'Daily action budget exhausted.', 'aivva_id' => $aivva->id];
        }

        // Same backoff as the action budget above. Without this early return, a
        // token-exhausted AIVVA kept creating a fresh plan, immediately getting
        // blocked on the same exhausted budget inside ActionValidator, marking
        // that step done, and repeating every tick forever — spamming "blocked"
        // logs without ever executing anything, so world_minutes (and the
        // displayed clock) never advanced either.
        if ($permissions && $budget->tokens_used >= $permissions->daily_token_budget) {
            $aivva->status = AivvaStatus::Idle;
            $aivva->next_scheduled_at = now()->addHour();
            $aivva->save();

            return ['ok' => false, 'reason' => 'Daily token budget exhausted.', 'aivva_id' => $aivva->id];
        }

        if (! $aivva->currentGoal || $aivva->currentGoal->status !== GoalStatus::Active) {
            $aivva->status = AivvaStatus::Idle;
            $aivva->save();

            return ['ok' => true, 'idle' => true, 'aivva_id' => $aivva->id];
        }

        // A plan that finished every step (status COMPLETED) means the goal is
        // done — finish it here. Without this check, "not ACTIVE" below can't
        // tell "just finished" apart from "never had one", so it kept building
        // a fresh plan for the same already-finished goal and running it again,
        // forever (visible as the same trip/plan repeating every few minutes).
        if ($aivva->currentPlan && $aivva->currentPlan->status === 'COMPLETED') {
            $aivva->currentGoal->status = GoalStatus::Completed;
            $aivva->currentGoal->progress = 100;
            $aivva->currentGoal->save();
            $aivva->status = AivvaStatus::Idle;
            $aivva->save();
            $this->log($aivva, null, 'goal', "{$aivva->name} completed the current direction.", true);

            return ['ok' => true, 'completed_goal' => true, 'aivva_id' => $aivva->id];
        }

        if (! $aivva->currentPlan || $aivva->currentPlan->status !== 'ACTIVE') {
            $aivva->status = AivvaStatus::Planning;
            $aivva->save();
            $plan = $this->planner->createPlan($aivva, $aivva->currentGoal);
            $aivva->current_plan_id = $plan->id;
            $aivva->save();
            $this->log($aivva, null, 'plan', "{$aivva->name} created a plan with ".count($plan->steps).' steps.');
            $aivva->refresh()->load('currentPlan');
        }

        $step = $aivva->currentPlan?->currentStep();
        if (! $step) {
            $aivva->currentGoal->status = GoalStatus::Completed;
            $aivva->currentGoal->progress = 100;
            $aivva->currentGoal->save();
            $aivva->status = AivvaStatus::Idle;
            $aivva->save();
            $this->log($aivva, null, 'goal', "{$aivva->name} completed the current direction.", true);

            return ['ok' => true, 'completed_goal' => true, 'aivva_id' => $aivva->id];
        }

        $type = ActionType::from($step['type']);
        $payload = $step['payload'] ?? [];
        $decision = $this->validator->validate($aivva, $type, $payload);

        $action = AivvaAction::query()->create([
            'aivva_id' => $aivva->id,
            'goal_id' => $aivva->current_goal_id,
            'plan_id' => $aivva->current_plan_id,
            'type' => $type,
            'payload' => $payload,
            'status' => ActionStatus::Pending,
            'initiated_by' => 'AI',
            'reason_category' => $aivva->currentGoal->goal_type,
            'permission_level_used' => $permissions?->autonomy_level->value,
            'location_id' => $aivva->current_location_id,
            'idempotency_key' => (string) Str::uuid(),
            'started_at' => now(),
        ]);

        $aivva->current_action_id = $action->id;
        $aivva->last_activity_at = now();
        $aivva->save();

        if (! $decision['allowed']) {
            $action->status = $decision['needs_approval'] ? ActionStatus::WaitingApproval : ActionStatus::Rejected;
            $action->result = $decision;
            $action->completed_at = now();
            $action->save();
            if ($decision['needs_approval']) {
                $aivva->status = AivvaStatus::WaitingApproval;
                $aivva->save();
                $this->log($aivva, $action, 'approval', "{$aivva->name} is waiting for owner approval.", true);
            } else {
                $this->log($aivva, $action, 'blocked', $decision['reason'] ?? 'Action rejected.');
                $aivva->currentPlan?->markStepDone();
                $this->scheduleNext($aivva);
            }

            return ['ok' => false, 'reason' => $decision['reason'], 'needs_approval' => $decision['needs_approval'], 'aivva_id' => $aivva->id];
        }

        $action->status = ActionStatus::Running;
        $action->save();

        $result = $this->executor->execute($aivva->fresh(['profile', 'permissions', 'currentGoal', 'wallet', 'currentLocation', 'homeLocation']), $action);
        $failed = (bool) ($result['failed'] ?? false);

        $action->status = $failed ? ActionStatus::Failed : ActionStatus::Completed;
        $action->result = $result;
        $action->completed_at = now();
        $action->save();

        $this->log(
            $aivva->fresh(),
            $action,
            $result['kind'] ?? 'action',
            $result['headline'] ?? $action->type->value,
            (bool) ($result['notify'] ?? false),
            $result['body'] ?? null,
            $result['meta'] ?? [],
        );

        $budget->increment('actions_used');
        if (! ($result['async'] ?? false)) {
            $aivva->currentPlan?->fresh()?->markStepDone();
        } else {
            // Travel completes on a later tick; still advance the plan so we do not loop travel.
            $aivva->currentPlan?->fresh()?->markStepDone();
        }

        $this->scheduleNext($aivva->fresh());

        return [
            'ok' => ! $failed,
            'action' => $action->type->value,
            'result' => $result,
            'aivva_id' => $aivva->id,
        ];
    }

    private function scheduleNext(Aivva $aivva): void
    {
        if ($aivva->status === AivvaStatus::WaitingApproval) {
            return;
        }
        if ($aivva->status !== AivvaStatus::Traveling) {
            $aivva->status = AivvaStatus::Idle;
        }
        $aivva->next_scheduled_at = now()->addSeconds((int) config('aivva.tick_seconds', 4));
        $aivva->save();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function log(
        Aivva $aivva,
        ?AivvaAction $action,
        string $kind,
        string $headline,
        bool $notify = false,
        ?string $body = null,
        array $meta = [],
    ): void {
        AivvaActivityLog::query()->create([
            'aivva_id' => $aivva->id,
            'action_id' => $action?->id,
            'kind' => $kind,
            'headline' => $headline,
            'body' => $body,
            'world_minutes' => $aivva->world_minutes,
            'meta' => $meta,
            'notify_owner' => $notify,
        ]);
    }
}
