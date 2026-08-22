<?php

namespace App\Models;

use App\Enums\AivvaStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Aivva extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'owner_id', 'name', 'slug', 'status',
        'current_goal_id', 'current_plan_id', 'current_action_id',
        'current_location_id', 'destination_location_id', 'home_location_id',
        'energy', 'life_points', 'world_minutes',
        'last_activity_at', 'next_scheduled_at', 'activated_at', 'paused_at',
        'is_platform', 'visible_on_map',
    ];

    protected function casts(): array
    {
        return [
            'status' => AivvaStatus::class,
            'energy' => 'integer',
            'life_points' => 'integer',
            'world_minutes' => 'integer',
            'last_activity_at' => 'datetime',
            'next_scheduled_at' => 'datetime',
            'activated_at' => 'datetime',
            'paused_at' => 'datetime',
            'is_platform' => 'boolean',
            'visible_on_map' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(AivvaProfile::class);
    }

    public function permissions(): HasOne
    {
        return $this->hasOne(AivvaPermission::class);
    }

    public function currentGoal(): BelongsTo
    {
        return $this->belongsTo(AivvaGoal::class, 'current_goal_id');
    }

    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(AivvaPlan::class, 'current_plan_id');
    }

    public function currentAction(): BelongsTo
    {
        return $this->belongsTo(AivvaAction::class, 'current_action_id');
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function homeLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'home_location_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(AivvaGoal::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(AivvaActivityLog::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(AivvaMemory::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'owner_id')->where('owner_type', 'aivva');
    }

    public function trustScore(): HasOne
    {
        return $this->hasOne(TrustScore::class);
    }

    public function messagesOut(): HasMany
    {
        return $this->hasMany(AivvaMessage::class, 'from_aivva_id');
    }

    public function messagesIn(): HasMany
    {
        return $this->hasMany(AivvaMessage::class, 'to_aivva_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(MarketplaceListing::class, 'seller_aivva_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AivvaAction::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(OwnerChat::class);
    }

    public function worldClock(): string
    {
        $hours = intdiv($this->world_minutes, 60) % 24;
        $minutes = $this->world_minutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function advanceWorldMinutes(int $minutes): void
    {
        $this->world_minutes = ($this->world_minutes + max(0, $minutes)) % (24 * 60);
        $this->save();
    }

    public function isPaused(): bool
    {
        return $this->status === AivvaStatus::Paused;
    }

    public function canAct(): bool
    {
        return $this->status->isActive();
    }
}
