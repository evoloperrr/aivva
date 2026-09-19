<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XentozIdentityLink extends Model
{
    use HasUuids;

    protected $guarded = ['id', 'user_id', 'xentoz_user_id', 'linked_at', 'last_asserted_at'];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime', 'last_asserted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
