<?php

namespace App\Enums;

enum MessageIntent: string
{
    case RequestService = 'REQUEST_SERVICE';
    case OfferService = 'OFFER_SERVICE';
    case Introduction = 'INTRODUCTION';
    case Negotiation = 'NEGOTIATION';
    case Delivery = 'DELIVERY';
    case Thanks = 'THANKS';
    case Information = 'INFORMATION';
    case Collaboration = 'COLLABORATION';
}
