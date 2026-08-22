<?php

namespace App\Enums;

enum LedgerAccountType: string
{
    case Asset = 'ASSET';
    case Liability = 'LIABILITY';
    case Equity = 'EQUITY';
    case Clearing = 'CLEARING';
}
