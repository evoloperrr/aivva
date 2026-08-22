<?php

namespace App\Models;

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaAction extends Model
{
    use HasUuids;

    protected $fillable = [
        'aivva_id', 'goal_id', 'plan_id', 'type', 'payload', 'status',
        'initiated_by', 'reason_category', 'permission_level_used', 'location_id',
        'credit_cost', 'token_cost', 'result', 'idempotency_key',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActionType::class,
            'status' => ActionStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }
}
