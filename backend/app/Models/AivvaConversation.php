<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AivvaConversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'type', 'status', 'max_turns', 'turn_count', 'retry_count',
        'allow_settlement', 'seed_event', 'location_id', 'next_speaker_id',
        'last_error', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'allow_settlement' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Aivva::class, 'aivva_conversation_participants', 'conversation_id', 'aivva_id')
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function participantRows(): HasMany
    {
        return $this->hasMany(AivvaConversationParticipant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AivvaMessage::class, 'conversation_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function nextSpeaker(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'next_speaker_id');
    }

    public function counterpart(Aivva $aivva): ?Aivva
    {
        return $this->participants->first(fn (Aivva $other) => $other->id !== $aivva->id);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [ConversationStatus::Active, ConversationStatus::WaitingRetry], true);
    }
}
