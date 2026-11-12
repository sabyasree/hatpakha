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
            // D127: the service worker, outside the web group: no session, cookie or page cache; never stored by Cloudflare.
            \Illuminate\Support\Facades\Route::get('/sw.js', [\App\Http\Controllers\AppController::class, 'serviceWorker']);
            // D132: a tapped notification is counted (kind only; no session, no person).
            \Illuminate\Support\Facades\Route::post('/push/opened', [\App\Http\Controllers\AppController::class, 'opened'])
                ->middleware(\Illuminate\Routing\Middleware\ThrottleRequests::class.':30,1');
            // D117: Amazon SES bounce/complaint reports via SNS (signed by Amazon; no session or CSRF involved).
            \Illuminate\Support\Facades\Route::post('/webhooks/ses', \App\Http\Controllers\SesWebhookController::class)
                ->middleware(\Illuminate\Routing\Middleware\ThrottleRequests::class.':60,1');
            // D164: Brevo's reports of hard bounces and spam complaints (the secret token in the address is the proof).
            \Illuminate\Support\Facades\Route::post('/webhooks/brevo/{token}', \App\Http\Controllers\BrevoWebhookController::class)
                ->where('token', '[A-Za-z0-9_-]{20,128}')->middleware(\Illuminate\Routing\Middleware\ThrottleRequests::class.':120,1');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare/LiteSpeed: trust forwarded proto/host so HTTPS is detected.
        $middleware->trustProxies(at: '*');
        // RFC 8058 one-click unsubscribe: mail apps POST to the signed link without a form token (the signature is the proof).
        $middleware->validateCsrfTokens(except: ['newsletter/unsubscribe/*']);
        // docs/SEO.md §1 and docs/SECURITY.md §1
        $middleware->web(prepend: [\App\Http\Middleware\GuestPageCache::class, \App\Http\Middleware\CanonicalHost::class, \App\Http\Middleware\EnforceTrailingSlash::class]);   // D65: outermost; D125: one host
        $middleware->web(append: [\App\Http\Middleware\ViewAs::class, \App\Http\Middleware\SecurityHeaders::class, \App\Http\Middleware\AgentFormats::class]);   // D85: view as a writer (look only); D126: Markdown for agents
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
