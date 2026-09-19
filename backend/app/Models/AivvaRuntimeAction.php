<?php

namespace App\Models;

use App\Enums\RuntimeActionStatus;
use App\Enums\RuntimeActionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaRuntimeAction extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return ['type' => RuntimeActionType::class, 'status' => RuntimeActionStatus::class, 'payload' => 'array', 'result' => 'array', 'claimed_at' => 'datetime', 'lease_expires_at' => 'datetime', 'expires_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
    public function aivva(): BelongsTo { return $this->belongsTo(Aivva::class); }
    public function sourceAction(): BelongsTo { return $this->belongsTo(AivvaAction::class, 'source_action_id'); }
}
