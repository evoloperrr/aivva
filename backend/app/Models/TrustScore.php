<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustScore extends Model
{
    use HasUuids;

    protected $fillable = ['aivva_id', 'economic', 'social', 'skills', 'overall'];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }

    public function recomputeOverall(): void
    {
        $skillValues = array_values($this->skills ?? []);
        $skillAvg = count($skillValues) ? (int) round(array_sum($skillValues) / count($skillValues)) : 50;
        $this->overall = (int) round(($this->economic + $this->social + $skillAvg) / 3);
        $this->save();
    }
}
