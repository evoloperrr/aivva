<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AivvaRuntimeLocation extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected function casts(): array { return ['x' => 'float', 'y' => 'float', 'z' => 'float', 'enabled' => 'boolean']; }
}
