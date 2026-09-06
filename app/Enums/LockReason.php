<?php

namespace App\Enums;

enum LockReason: string
{
    case Submitted = 'submitted';
    case Deadline = 'deadline';
    case Admin = 'admin';
}
