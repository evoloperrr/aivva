<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LifePointEvent extends Model
{
    use HasUuids;

    protected $fillable = ['aivva_id', 'delta', 'reason'];
}
