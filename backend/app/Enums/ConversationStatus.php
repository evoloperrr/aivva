<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case Active = 'ACTIVE';
    case Completed = 'COMPLETED';
    case WaitingRetry = 'WAITING_RETRY';
    case PausedError = 'PAUSED_ERROR';
    case Ended = 'ENDED';
}
