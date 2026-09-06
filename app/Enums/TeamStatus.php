<?php

namespace App\Enums;

enum TeamStatus: string
{
    case PendingActivation = 'pending_activation';
    case Registered = 'registered';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
