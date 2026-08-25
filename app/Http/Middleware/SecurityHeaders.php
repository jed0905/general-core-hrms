<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {

        if (app()->environment('local')) {
            return $next($request);
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS (only if HTTPS is fully active)
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }



        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' https: 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' https: 'unsafe-inline'; " .
            "font-src 'self' https: data:; " .
            "img-src 'self' https: data:; " .
            "connect-src 'self' https: ws: wss:; " .
            "frame-src 'self' https:; " .
            "form-action 'self' https:;"
        );

        return $response;
    }
}
