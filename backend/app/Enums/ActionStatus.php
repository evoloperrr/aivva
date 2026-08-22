<?php

namespace App\Enums;

enum ActionStatus: string
{
    case Pending = 'PENDING';
    case Validated = 'VALIDATED';
    case Running = 'RUNNING';
    case Completed = 'COMPLETED';
    case Rejected = 'REJECTED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
    case WaitingApproval = 'WAITING_APPROVAL';
}
