<?php

namespace App\Enums;

enum ConversationMessageType: string
{
    case Text = 'TEXT';
    case Offer = 'OFFER';
    case Request = 'REQUEST';
    case SystemEvent = 'SYSTEM_EVENT';
}
