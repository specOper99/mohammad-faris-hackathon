<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = strtolower((string) $request->header('Accept-Language', 'en'));
        $locale = str_starts_with($header, 'ar') ? 'ar' : 'en';
        app()->setLocale($locale);

        return $next($request);
    }
}
