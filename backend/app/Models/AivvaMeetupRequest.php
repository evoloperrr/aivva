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
}
