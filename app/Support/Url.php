<?php

namespace App\Support;

/** Absolute URL with the site's trailing-slash policy (docs/SEO.md §1). Laravel's url() strips it. */
final class Url
{
    public static function to(string $path): string
    {
        $u = rtrim(url($path), '/');
        // A file (sitemap.xml, feed.rss…) keeps no slash; only the path is checked, so "hatpakha.com" itself gets "/" too.
        $p = (string) parse_url($u, PHP_URL_PATH);

        return preg_match('/\.[a-z0-9]{2,5}$/i', $p) ? $u : $u.'/';
    }
}
