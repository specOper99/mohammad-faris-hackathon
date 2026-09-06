<?php

namespace App\Enums;

enum OneTimeTokenPurpose: string
{
    case EmailActivation = 'email_activation';
    case PasswordReset = 'password_reset';
    case TeamInvitation = 'team_invitation';
}
