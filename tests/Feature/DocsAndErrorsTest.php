<?php

use Illuminate\Support\Facades\Route;

test('docs path redirects to scramble ui', function () {
    $this->withHeaders(['Accept' => 'text/html'])
        ->get('/docs')
        ->assertRedirect('/docs/api');
});

test('docs trailing slash redirects to scramble ui', function () {
    $this->withHeaders(['Accept' => 'text/html'])
        ->get('/docs/')
        ->assertRedirect('/docs/api');
});

test('scramble docs ui is registered and served', function () {
    expect(
        collect(Route::getRoutes())
            ->contains(fn ($route) => str_contains($route->uri(), 'docs/api'))
    )->toBeTrue();

    $this->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
        ->get('/docs/api')
        ->assertOk();
});

test('api 404 includes a correlation id', function () {
    $response = $this->getJson('/api/v1/this-route-does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'NOT_FOUND');

    $id = $response->json('meta.correlationId');
    expect($id)->toBeString()->not->toBeEmpty();
    $response->assertHeader('X-Correlation-ID', $id);
});

test('api 404 echoes request correlation id', function () {
    $this->withHeaders(['X-Correlation-ID' => '11111111-1111-1111-1111-111111111111'])
        ->getJson('/api/v1/this-route-does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('meta.correlationId', '11111111-1111-1111-1111-111111111111')
        ->assertHeader('X-Correlation-ID', '11111111-1111-1111-1111-111111111111');
});

test('html web 404 is not the api envelope', function () {
    $content = $this->withHeaders(['Accept' => 'text/html'])
        ->get('/this-web-page-does-not-exist')
        ->assertNotFound()
        ->getContent();

    expect($content)->not->toContain('"code":"NOT_FOUND"');
    expect($content)->not->toContain('"correlationId":null');
});

test('openapi documents every api v1 route with the full /api/v1 prefix', function () {
    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    expect($spec['openapi'] ?? null)->toBeString();

    $server = rtrim((string) ($spec['servers'][0]['url'] ?? ''), '/');
    expect($server)->not->toEndWith('/api');
    expect($server)->not->toEndWith('/api/v1');

    $paths = $spec['paths'] ?? [];
    expect($paths)->toBeArray()->not->toBeEmpty();

    $stripped = collect(array_keys($paths))
        ->filter(fn (string $path) => str_starts_with($path, '/v1/'))
        ->values()
        ->all();
    expect($stripped)->toBeEmpty();

    $missing = [];
    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();
        if (! str_starts_with($uri, 'api/v1')) {
            continue;
        }

        $path = '/'.$uri;
        if (! array_key_exists($path, $paths)) {
            $missing[] = $path;

            continue;
        }

        foreach ($route->methods() as $method) {
            $method = strtolower($method);
            if (in_array($method, ['head', 'options'], true)) {
                continue;
            }
            if (! array_key_exists($method, $paths[$path])) {
                $missing[] = strtoupper($method).' '.$path;
            }
        }
    }

    expect($missing)->toBeEmpty();
    expect($paths)->toHaveKey('/sanctum/csrf-cookie');
    expect($paths)->toHaveKey('/up');
});

test('openapi login has a cookie session body and a user object', function () {
    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    expect($spec['components']['securitySchemes'] ?? [])->toHaveKey('sanctumCookie');

    $login = $spec['paths']['/api/v1/auth/login']['post'] ?? null;
    expect($login)->toBeArray();

    $requestSchema = openApiSchema($spec, $login['requestBody']['content']['application/json']['schema'] ?? null);
    $props = $requestSchema['properties'] ?? [];
    expect($props)->toHaveKey('email');
    expect($props)->toHaveKey('password');

    $responseSchema = openApiSchema(
        $spec,
        $login['responses']['200']['content']['application/json']['schema']
            ?? $login['responses']['201']['content']['application/json']['schema']
            ?? null,
    );
    $user = openApiSchema(
        $spec,
        data_get($responseSchema, 'properties.data.properties.user')
            ?? data_get($responseSchema, 'properties.user')
            ?? data_get($responseSchema, 'properties.data'),
    );

    expect($user['type'] ?? null)->not->toBe('array');
    expect($user['properties'] ?? [])->toHaveKey('email');
});

test('openapi mutations that take json bodies declare requestBody', function () {
    $spec = $this->getJson('/docs/api.json')->assertOk()->json();
    $paths = $spec['paths'] ?? [];

    $expected = [
        ['put', '/api/v1/teams/me'],
        ['put', '/api/v1/submissions/{id}'],
        ['put', '/api/v1/admin/settings'],
        ['put', '/api/v1/admin/teams/{id}'],
        ['post', '/api/v1/submissions/{id}/files/uploads/{sessionId}/complete'],
        ['post', '/api/v1/auth/login'],
        ['post', '/api/v1/auth/register'],
        ['put', '/api/v1/users/me'],
        ['put', '/api/v1/judge/submissions/{id}/evaluation'],
        ['put', '/api/v1/admin/tracks/{id}'],
        ['put', '/api/v1/admin/judges/{id}'],
    ];

    $missingBodies = [];
    foreach ($expected as [$method, $path]) {
        if (! isset($paths[$path][$method]['requestBody'])) {
            $missingBodies[] = strtoupper($method).' '.$path;
        }
    }

    expect($missingBodies)->toBeEmpty();
});

/**
 * @param  array<string, mixed>  $spec
 * @return array<string, mixed>
 */
function openApiSchema(array $spec, mixed $node): array
{
    if (! is_array($node)) {
        return [];
    }

    if (isset($node['$ref']) && is_string($node['$ref'])) {
        $cur = $spec;
        foreach (explode('/', ltrim($node['$ref'], '#/')) as $segment) {
            if ($segment === '') {
                continue;
            }
            $cur = is_array($cur) ? ($cur[$segment] ?? null) : null;
        }

        return openApiSchema($spec, $cur);
    }

    if (isset($node['allOf']) && is_array($node['allOf'])) {
        $merged = [];
        foreach ($node['allOf'] as $part) {
            $merged = array_replace_recursive($merged, openApiSchema($spec, $part));
        }

        return $merged;
    }

    if (isset($node['oneOf'][0])) {
        return openApiSchema($spec, $node['oneOf'][0]);
    }

    return $node;
}
