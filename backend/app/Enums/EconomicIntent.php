<?php

namespace App\Enums;

enum EconomicIntent: string
{
    case RequestService = 'REQUEST_SERVICE';
    case AskRequirements = 'ASK_REQUIREMENTS';
    case SubmitOffer = 'SUBMIT_OFFER';
    case CounterOffer = 'COUNTER_OFFER';
    case AcceptOffer = 'ACCEPT_OFFER';
    case DeclineOffer = 'DECLINE_OFFER';
    case CancelNegotiation = 'CANCEL_NEGOTIATION';
    case Discover = 'DISCOVER';
    case CreateWork = 'CREATE_WORK';
    case Deliver = 'DELIVER';
    case Wait = 'WAIT';
}
