<?php

namespace App\Support;

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

class SanctumCookieAuthSecurityStrategy extends MiddlewareAuthSecurityStrategy
{
    public function __construct()
    {
        parent::__construct(
            ['auth', 'auth:*'],
            SecurityScheme::apiKey('cookie', 'laravel_session')->as('sanctumCookie'),
        );
    }
}
