<?php

namespace App\Domain\Runtime;

use App\Enums\{ActionStatus, AivvaControlMode, AivvaStatus, RuntimeActionStatus, RuntimeActionType};
use App\Models\{Aivva, AivvaDailyBudget, AivvaRuntimeAction, AivvaRuntimeLocation};
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Support\Str;

class RuntimeActionService
{
    public function create(Aivva $aivva, RuntimeActionType $type, array $payload, string $initiatedBy = 'AI', ?string $sourceActionId = null): AivvaRuntimeAction
    {
        $this->validatePayload($aivva, $type, $payload);
        if ($initiatedBy === 'AI') {
            abort_unless(config('aivva.runtime.ai_control_enabled') && $aivva->control_mode === AivvaControlMode::AiTwin, 409, 'AI Twin does not control this AIVVA.');
        }
        if ($sourceActionId && $existing = AivvaRuntimeAction::query()->where('source_action_id', $sourceActionId)->first()) {
            return $existing;
        }
        $action = AivvaRuntimeAction::query()->create([
            'aivva_id' => $aivva->id, 'source_action_id' => $sourceActionId, 'type' => $type,
            'payload' => $payload, 'status' => RuntimeActionStatus::Requested,
            'correlation_id' => (string) Str::uuid(), 'initiated_by' => $initiatedBy,
            'expires_at' => now()->addSeconds((int) config('aivva.runtime.action_ttl_seconds', 120)),
        ]);
        $this->log($action, RuntimeActionStatus::Requested->value);
        return $action;
    }

    public function active(Aivva $aivva): ?AivvaRuntimeAction
    {
        return DB::transaction(function () use ($aivva) {
            $action = AivvaRuntimeAction::query()->where('aivva_id', $aivva->id)
                ->whereIn('status', [RuntimeActionStatus::Requested, RuntimeActionStatus::Executing])
                ->oldest()->lockForUpdate()->first();
            if (! $action) return null;
            if ($action->expires_at->isPast()) return $this->expire($action);
            if ($action->status === RuntimeActionStatus::Executing && $action->lease_expires_at?->isPast()) {
                $action->forceFill(['status' => RuntimeActionStatus::Requested, 'execution_id' => null,
                    'client_instance_id' => null, 'claimed_at' => null, 'lease_expires_at' => null,
                    'version' => $action->version + 1])->save();
                $this->log($action, RuntimeActionStatus::Requested->value, ['recovered' => true]);
            }
            return $action;
        });
    }

    public function claim(AivvaRuntimeAction $runtimeAction, string $executionId, string $clientInstanceId): AivvaRuntimeAction
    {
        return DB::transaction(function () use ($runtimeAction, $executionId, $clientInstanceId) {
            $action = AivvaRuntimeAction::query()->lockForUpdate()->findOrFail($runtimeAction->id);
            if ($action->status === RuntimeActionStatus::Executing && hash_equals((string) $action->execution_id, $executionId)) return $action;
            abort_unless($action->status === RuntimeActionStatus::Requested, 409, 'Action cannot be claimed from its current state.');
            if ($action->expires_at->isPast()) { $this->expire($action); abort(409, 'Action expired.'); }
            $action->forceFill(['status' => RuntimeActionStatus::Executing, 'execution_id' => $executionId,
                'client_instance_id' => $clientInstanceId, 'claimed_at' => now(),
                'started_at' => $action->started_at ?? now(),
                'lease_expires_at' => now()->addSeconds((int) config('aivva.runtime.action_lease_seconds', 30)),
                'version' => $action->version + 1])->save();
            $action->sourceAction?->forceFill(['status' => ActionStatus::Running])->save();
            $action->aivva()->update(['status' => $action->type === RuntimeActionType::MoveTo ? AivvaStatus::Traveling : AivvaStatus::Working]);
            $this->log($action, RuntimeActionStatus::Executing->value);
            return $action;
        });
    }

    public function acknowledge(AivvaRuntimeAction $runtimeAction, string $executionId, RuntimeActionStatus $status, array $result = [], ?string $failureCode = null): AivvaRuntimeAction
    {
        return DB::transaction(function () use ($runtimeAction, $executionId, $status, $result, $failureCode) {
            $action = AivvaRuntimeAction::query()->lockForUpdate()->findOrFail($runtimeAction->id);
            abort_unless(in_array($status, [RuntimeActionStatus::Completed, RuntimeActionStatus::Failed, RuntimeActionStatus::Cancelled], true), 422, 'Unsupported acknowledgement state.');
            if ($action->status === $status && hash_equals((string) $action->execution_id, $executionId)) return $action;
            abort_unless($action->status === RuntimeActionStatus::Executing, 409, 'Illegal runtime action transition.');
            abort_unless(hash_equals((string) $action->execution_id, $executionId), 409, 'Execution lease mismatch.');
            if ($status === RuntimeActionStatus::Failed) abort_unless($failureCode, 422, 'Failure code is required.');
            $action->forceFill(['status' => $status, 'result' => $result, 'failure_code' => $failureCode,
                'completed_at' => now(), 'lease_expires_at' => null, 'version' => $action->version + 1])->save();
            $this->finishSourceAction($action, $status, $result, $failureCode);
            $this->releaseAivva($action);
            $this->log($action, $status->value);
            return $action;
        });
    }

    public function cancel(AivvaRuntimeAction $runtimeAction): AivvaRuntimeAction
    {
        return DB::transaction(function () use ($runtimeAction) {
            $action = AivvaRuntimeAction::query()->lockForUpdate()->findOrFail($runtimeAction->id);
            if ($action->status === RuntimeActionStatus::Cancelled) return $action;
            abort_unless(in_array($action->status, [RuntimeActionStatus::Requested, RuntimeActionStatus::Executing], true), 409, 'Action cannot be cancelled from its current state.');
            $action->forceFill(['status' => RuntimeActionStatus::Cancelled, 'completed_at' => now(),
                'lease_expires_at' => null, 'version' => $action->version + 1])->save();
            $this->finishSourceAction($action, RuntimeActionStatus::Cancelled, [], 'CANCELLED');
            $this->releaseAivva($action);
            $this->log($action, RuntimeActionStatus::Cancelled->value);
            return $action;
        });
    }

    private function expire(AivvaRuntimeAction $action): AivvaRuntimeAction
    {
        $action->forceFill(['status' => RuntimeActionStatus::Expired, 'completed_at' => now(),
            'lease_expires_at' => null, 'failure_code' => 'ACTION_EXPIRED', 'version' => $action->version + 1])->save();
        $this->finishSourceAction($action, RuntimeActionStatus::Expired, [], 'ACTION_EXPIRED');
        $this->releaseAivva($action);
        $this->log($action, RuntimeActionStatus::Expired->value);
        return $action;
    }

    private function finishSourceAction(AivvaRuntimeAction $runtimeAction, RuntimeActionStatus $status, array $result, ?string $failureCode): void
    {
        $source = $runtimeAction->sourceAction;
        if (! $source || in_array($source->status, [ActionStatus::Completed, ActionStatus::Failed, ActionStatus::Cancelled], true)) return;
        $sourceStatus = match ($status) {
            RuntimeActionStatus::Completed => ActionStatus::Completed,
            RuntimeActionStatus::Cancelled => ActionStatus::Cancelled,
            default => ActionStatus::Failed,
        };
        $source->forceFill(['status' => $sourceStatus, 'result' => array_merge($result, [
            'runtime_action_id' => $runtimeAction->id, 'runtime_status' => $status->value,
            'failure_code' => $failureCode]), 'completed_at' => now()])->save();
        if ($status === RuntimeActionStatus::Completed) {
            $source->plan_id && $source->plan?->markStepDone();
            AivvaDailyBudget::todayFor($runtimeAction->aivva)->increment('actions_used');
        }
    }

    private function releaseAivva(AivvaRuntimeAction $action): void
    {
        $action->aivva()->update(['status' => AivvaStatus::Idle, 'current_action_id' => null,
            'next_scheduled_at' => now()->addSeconds((int) config('aivva.tick_seconds', 4)),
            'state_version' => DB::raw('state_version + 1')]);
    }

    private function validatePayload(Aivva $aivva, RuntimeActionType $type, array $payload): void
    {
        if ($type === RuntimeActionType::MoveTo) abort_unless(isset($payload['locationId']) && AivvaRuntimeLocation::query()->whereKey($payload['locationId'])->where('enabled', true)->exists(), 422, 'Invalid runtime location.');
        if (in_array($type, [RuntimeActionType::FaceTarget, RuntimeActionType::Interact], true)) abort_unless(isset($payload['targetAivvaId']) && Aivva::query()->whereKey($payload['targetAivvaId'])->exists() && $payload['targetAivvaId'] !== $aivva->id, 422, 'Invalid target AIVVA.');
        if ($type === RuntimeActionType::Say) abort_unless(isset($payload['text']) && is_string($payload['text']) && mb_strlen(trim($payload['text'])) > 0 && mb_strlen($payload['text']) <= 500, 422, 'Invalid dialogue text.');
    }

    private function log(AivvaRuntimeAction $action, string $status, array $extra = []): void
    {
        Log::info('AIVVA_ACTION', array_merge(['actionId' => $action->id, 'aivvaId' => $action->aivva_id,
            'type' => $action->type->value, 'status' => $status, 'executionId' => $action->execution_id,
            'correlationId' => $action->correlation_id], $extra));
    }
}
