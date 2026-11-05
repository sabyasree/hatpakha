<?php

namespace App\Support;

/** Absolute URL with the site's trailing-slash policy (docs/SEO.md §1). Laravel's url() strips it. */
final class Url
{
    public static function to(string $path): string
    {
        $u = rtrim(url($path), '/');

        return preg_match('/\.[a-z0-9]{2,5}$/i', $u) ? $u : $u.'/';
    }
}
