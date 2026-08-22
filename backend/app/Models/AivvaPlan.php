<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaPlan extends Model
{
    use HasUuids;

    protected $fillable = ['aivva_id', 'goal_id', 'steps', 'current_step', 'status'];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(AivvaGoal::class, 'goal_id');
    }

    public function currentStep(): ?array
    {
        return $this->steps[$this->current_step] ?? null;
    }

    public function markStepDone(): void
    {
        $steps = $this->steps;
        if (isset($steps[$this->current_step])) {
            $steps[$this->current_step]['status'] = 'DONE';
            $this->steps = $steps;
        }
        $this->current_step++;
        if ($this->current_step >= count($steps)) {
            $this->status = 'COMPLETED';
        }
        $this->save();
    }
}
