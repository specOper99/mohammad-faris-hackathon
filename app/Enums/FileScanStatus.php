<?php

namespace App\Enums;

enum FileScanStatus: string
{
    case Skipped = 'skipped';
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
}
