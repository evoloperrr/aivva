<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiProviderRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'aivva_id', 'conversation_id', 'provider', 'model', 'purpose',
        'input_tokens', 'output_tokens', 'cost_cents', 'latency_ms', 'status',
    ];
}
