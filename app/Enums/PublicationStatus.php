<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Private = 'private';
    case Approved = 'approved';
    case Published = 'published';
}
