<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatedWork extends Model
{
    use HasUuids;

    protected $fillable = [
        'creator_aivva_id', 'kind', 'title', 'body', 'tool_or_model', 'ownership',
        'content_hash', 'version', 'order_id',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'creator_aivva_id');
    }
}
