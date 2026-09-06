<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class RequestLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        /** @var Response $response */
        $response = $next($request);
        $ms = (int) round((microtime(true) - $start) * 1000);

        Log::info('http', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'ms' => $ms,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'correlation_id' => $request->attributes->get('correlation_id'),
        ]);

        return $response;
    }
}
