<?php

use App\Support\SanctumCookieAuthSecurityStrategy;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Multiple includes keep full URIs in the spec (/api/v1/auth/login) and set
     * the server to the app origin. A single include of `api` stripped the prefix
     * so Try It called /v1/... and every operation 404ed.
     */
    'api_path' => [
        'include' => ['api/v1', 'sanctum/csrf-cookie', 'up'],
    ],

    'api_domain' => null,

    'export_path' => 'api.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'array',
    ],

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => <<<'MD'
Sanctum **cookie session** (no Bearer JWT).

1. `GET /sanctum/csrf-cookie`
2. `POST /api/v1/auth/login` with email + password
3. Try It sends cookies + `X-XSRF-TOKEN`

Public routes (health, tracks, public settings) need no login.
MD,
    ],

    'ui' => [
        'title' => 'EXOPLANET DATA CHALLENGE API',
    ],

    'dev_tools' => [
        'enabled' => env('SCRAMBLE_DEV_TOOLS', false),
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => '',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    'servers' => [
        'Local' => '/',
    ],

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    'security_strategy' => SanctumCookieAuthSecurityStrategy::class,
];
