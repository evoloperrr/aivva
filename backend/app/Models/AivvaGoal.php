<?php

namespace App\Models;

use App\Enums\GoalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AivvaGoal extends Model
{
    use HasUuids;

    protected $fillable = [
        'aivva_id', 'raw_direction', 'goal_type', 'structured', 'status',
        'progress', 'rejected', 'rejection_reason', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'structured' => 'array',
            'status' => GoalStatus::class,
            'rejected' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(AivvaPlan::class, 'goal_id');
    }
}
