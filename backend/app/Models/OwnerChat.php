<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerChat extends Model
{
    use HasUuids;

    protected $fillable = ['aivva_id', 'role', 'body', 'intent', 'meta'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }
}
