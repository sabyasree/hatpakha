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
        // Read counter beacon (D54): outside the web group, so no session, cookie or CSRF token is involved.
        then: function () {
            \Illuminate\Support\Facades\Route::post('/v/{post}', \App\Http\Controllers\ViewController::class)
                ->whereNumber('post')->middleware(\Illuminate\Routing\Middleware\ThrottleRequests::class.':120,1');
            // D117: Amazon SES bounce/complaint reports via SNS (signed by Amazon; no session or CSRF involved).
            \Illuminate\Support\Facades\Route::post('/webhooks/ses', \App\Http\Controllers\SesWebhookController::class)
                ->middleware(\Illuminate\Routing\Middleware\ThrottleRequests::class.':60,1');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare/LiteSpeed: trust forwarded proto/host so HTTPS is detected.
        $middleware->trustProxies(at: '*');
        // docs/SEO.md §1 and docs/SECURITY.md §1
        $middleware->web(prepend: [\App\Http\Middleware\GuestPageCache::class, \App\Http\Middleware\CanonicalHost::class, \App\Http\Middleware\EnforceTrailingSlash::class]);   // D65: outermost; D125: one host
        $middleware->web(append: [\App\Http\Middleware\ViewAs::class, \App\Http\Middleware\SecurityHeaders::class]);   // D85: view as a writer (look only)
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
