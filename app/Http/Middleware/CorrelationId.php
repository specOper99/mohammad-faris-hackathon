<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get('X-Correlation-ID') ?: (string) Str::uuid();
        $request->headers->set('X-Correlation-ID', $id);
        $request->attributes->set('correlation_id', $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $id);

        return $response;
    }
}
