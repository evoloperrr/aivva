<?php

namespace App\Models;

use App\Enums\AutonomyLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaPermission extends Model
{
    protected $fillable = [
        'aivva_id', 'autonomy_level', 'max_per_transaction', 'daily_spend_limit',
        'daily_ai_budget_cents', 'daily_token_budget', 'daily_action_budget',
        'require_approval_above', 'can_travel', 'can_socialize', 'can_create',
        'can_transact', 'autonomous_interaction', 'blocked_aivva_ids',
        'approval_required_actions',
    ];

    protected function casts(): array
    {
        return [
            'autonomy_level' => AutonomyLevel::class,
            'can_travel' => 'boolean',
            'can_socialize' => 'boolean',
            'can_create' => 'boolean',
            'can_transact' => 'boolean',
            'autonomous_interaction' => 'boolean',
            'blocked_aivva_ids' => 'array',
            'approval_required_actions' => 'array',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }

    public function isBlocked(string $aivvaId): bool
    {
        return in_array($aivvaId, $this->blocked_aivva_ids ?? [], true);
    }
}
