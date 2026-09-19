<?php

namespace App\Enums;

enum RuntimeActionStatus: string
{
    case Requested = 'REQUESTED';
    case Executing = 'EXECUTING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled, self::Expired], true);
    }
}
