<?php

namespace App\Enums;

enum GoalStatus: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case Paused = 'PAUSED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case Rejected = 'REJECTED';
}
