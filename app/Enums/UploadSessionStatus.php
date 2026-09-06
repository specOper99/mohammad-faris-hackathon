<?php

namespace App\Enums;

enum UploadSessionStatus: string
{
    case Initiated = 'initiated';
    case Completed = 'completed';
    case Aborted = 'aborted';
    case Expired = 'expired';
}
