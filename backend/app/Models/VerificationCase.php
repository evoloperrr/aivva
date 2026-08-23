<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VerificationCase extends Model
{
    use HasUuids;

    protected $fillable = [
        'claim', 'subject_type', 'subject_id', 'confidence', 'report', 'status',
    ];

    protected function casts(): array
    {
        return [
            'report' => 'array',
        ];
    }
}
