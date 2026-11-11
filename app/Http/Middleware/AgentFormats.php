<?php

namespace App\Http\Middleware;

use App\Support\HtmlToMarkdown;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For AI agents (D126). Every public page points to /llms.txt in a Link header, and an agent that asks for
 * Markdown (Accept: text/markdown) gets the page's main content as Markdown instead of HTML.
 * The Markdown is never stored by any cache (private, no-store), so a browser can never be served it.
 */
class AgentFormats
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200 || $request->is(...GuestPageCache::PRIVATE)
            || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $response->headers->set('Link', '</llms.txt>; rel="describedby"; type="text/plain"', false);

        if (! self::wantsMarkdown($request)) {
            return $response;
        }
        $html = (string) $response->getContent();
        preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $m);
        $url = html_entity_decode($m[1] ?? $request->url());
        $markdown = HtmlToMarkdown::page($html, $url);
        if ($markdown === null) {
            return $response;
        }

        $response->setContent($markdown);
        $response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Vary', 'Accept');
        $response->headers->set('Link', '<'.$url.'>; rel="canonical"', false);

        return $response;
    }

    /** Markdown only when the agent puts it first (browsers never ask for it). */
    public static function wantsMarkdown(Request $request): bool
    {
        return in_array($request->getAcceptableContentTypes()[0] ?? null, ['text/markdown', 'text/x-markdown'], true);
    }
}
