<?php

namespace App\Domain\Agent;

use App\Enums\ActionType;
use App\Enums\AivvaStatus;
use App\Models\Aivva;
use App\Models\AivvaDailyBudget;
use App\Models\AivvaPermission;
use App\Models\Location;

class ActionValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{allowed: bool, reason: ?string, needs_approval: bool}
     */
    public function validate(Aivva $aivva, ActionType $type, array $payload = []): array
    {
        if ($aivva->status === AivvaStatus::Paused) {
            return $this->deny('Paused AIVVA cannot act.');
        }
        if ($aivva->status === AivvaStatus::Dormant) {
            return $this->deny('Activate this AIVVA before it can act.');
        }

        $permissions = $aivva->permissions;
        if (! $permissions instanceof AivvaPermission) {
            return $this->deny('Missing permission record.');
        }

        $budget = AivvaDailyBudget::todayFor($aivva);
        if ($budget->actions_used >= $permissions->daily_action_budget) {
            return $this->deny('Daily action budget exhausted.');
        }
        if ($budget->tokens_used >= $permissions->daily_token_budget) {
            return $this->deny('Daily token budget exhausted.');
        }

        $level = $permissions->autonomy_level;

        if (in_array($type, [ActionType::Travel, ActionType::RecallHome], true)) {
            if (! $permissions->can_travel || ! $level->canTravel()) {
                return $this->deny('Travel is not permitted at the current autonomy level.');
            }
            if (isset($payload['location_id']) && ! Location::query()->whereKey($payload['location_id'])->exists()) {
                return $this->deny('Destination is not a valid location.');
            }
        }

        if (in_array($type, [ActionType::Contact, ActionType::SendMessage], true)) {
            if (! $permissions->can_socialize || ! $level->canTalk()) {
                return $this->deny('Social actions are not permitted.');
            }
            $targetId = $payload['target_aivva_id'] ?? null;
            if ($targetId && $permissions->isBlocked((string) $targetId)) {
                return $this->deny('Target AIVVA is blocked.');
            }
            if (! $permissions->autonomous_interaction && ($payload['initiated_by'] ?? 'AI') === 'AI') {
                return $this->deny('Autonomous interaction is disabled.');
            }
        }

        if ($type === ActionType::CreateContent && (! $permissions->can_create || ! $level->canWork())) {
            return $this->deny('Creating work is not permitted.');
        }

        $spend = (int) ($payload['amount'] ?? $payload['credit_cost'] ?? 0);
        $economic = in_array($type, [
            ActionType::SubmitOffer,
            ActionType::AcceptOffer,
            ActionType::Negotiate,
            ActionType::DeliverWork,
        ], true);

        if ($economic && $spend > 0) {
            if (! $permissions->can_transact || ! $level->canSpend()) {
                return $this->deny('Economic actions are not permitted at the current autonomy level.');
            }
            if ($spend > $permissions->max_per_transaction) {
                return $this->deny('Transaction exceeds the per-transaction limit.');
            }
            if ($budget->spend_used + $spend > $permissions->daily_spend_limit) {
                return $this->deny('Transaction exceeds the remaining daily spend limit.');
            }
            $wallet = $aivva->wallet;
            if ($wallet && $type !== ActionType::DeliverWork && $wallet->available_balance < $spend && ($payload['role'] ?? 'seller') === 'buyer') {
                return $this->deny('Insufficient credits.');
            }
            if ($spend >= $permissions->require_approval_above) {
                return ['allowed' => false, 'reason' => 'This action needs owner approval.', 'needs_approval' => true];
            }
        }

        $alwaysApprove = $permissions->approval_required_actions ?? [];
        if (in_array($type->value, $alwaysApprove, true)) {
            return ['allowed' => false, 'reason' => 'This action type requires owner approval.', 'needs_approval' => true];
        }

        return ['allowed' => true, 'reason' => null, 'needs_approval' => false];
    }

    /**
     * @return array{allowed: bool, reason: string, needs_approval: bool}
     */
    private function deny(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason, 'needs_approval' => false];
    }
}
