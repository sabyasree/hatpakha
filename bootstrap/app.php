<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare/LiteSpeed: trust forwarded proto/host so HTTPS is detected.
        $middleware->trustProxies(at: '*');
        // docs/SEO.md §1 and docs/SECURITY.md §1
        $middleware->web(prepend: [\App\Http\Middleware\EnforceTrailingSlash::class]);
        $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
