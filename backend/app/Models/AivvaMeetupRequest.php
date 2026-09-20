<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaMeetupRequest extends Model
{
    use HasUuids;

    public const PENDING = 'PENDING';
    public const ACCEPTED = 'ACCEPTED';
    public const DECLINED = 'DECLINED';
    public const CANCELLED = 'CANCELLED';
    public const EXPIRED = 'EXPIRED';

    protected $fillable = ['from_aivva_id', 'to_aivva_id', 'proposed_location_id', 'status', 'expires_at', 'responded_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    public function fromAivva(): BelongsTo { return $this->belongsTo(Aivva::class, 'from_aivva_id'); }
    public function toAivva(): BelongsTo { return $this->belongsTo(Aivva::class, 'to_aivva_id'); }

    public function isAccepted(): bool { return $this->status === self::ACCEPTED && ! $this->expires_at->isPast(); }

    /**
     * The single source of truth for "do these two AIVVAs have standing
     * consent to interact right now" — an ACCEPTED, unexpired request in
     * either direction between them. Used both by the scene endpoint
     * (visibility) and by RuntimeActionService::validatePayload (whether a
     * FACE_TARGET/INTERACT action is actually allowed to be created), so
     * "who can see whom" and "who can act on whom" can never drift apart.
     */
    public static function hasAcceptedBetween(string $aivvaIdA, string $aivvaIdB): bool
    {
        return self::query()
            ->where('status', self::ACCEPTED)
            ->where('expires_at', '>', now())
            ->where(function ($query) use ($aivvaIdA, $aivvaIdB) {
                $query->where(fn ($q) => $q->where('from_aivva_id', $aivvaIdA)->where('to_aivva_id', $aivvaIdB))
                    ->orWhere(fn ($q) => $q->where('from_aivva_id', $aivvaIdB)->where('to_aivva_id', $aivvaIdA));
            })
            ->exists();
    }
}
