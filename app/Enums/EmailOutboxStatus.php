<?php

namespace App\Enums;

enum EmailOutboxStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Poison = 'poison';
}
