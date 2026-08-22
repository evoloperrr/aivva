<?php

namespace App\Enums;

enum EscrowStatus: string
{
    case Locked = 'LOCKED';
    case Settled = 'SETTLED';
    case Refunded = 'REFUNDED';
    case Disputed = 'DISPUTED';
}
