<?php

namespace App\Seo;

use App\Support\Url;

/**
 * Everything a page needs in <head> (docs/SEO.md §2). Every controller builds one;
 * the layout renders it via <x-seo>. Defaults are safe (canonical = current URL).
 */
final class Seo
{
    public string $siteName = 'হাতপাখা · Hatpakha';

    public function __construct(
        public string $title,
        public string $description = '',
        public ?string $canonical = null,
        public bool $noindex = false,
        public ?string $image = null,
        public string $type = 'website',
        public string $lang = 'bn',
        /** @var array<int,array> */
        public array $jsonLd = [],
        public ?string $publishedAt = null,
    ) {
        $this->canonical ??= Url::to(request()->getPathInfo());
        $this->description = mb_substr(trim(preg_replace('/\s+/u', ' ', $this->description)), 0, 155);
    }

    public function fullTitle(): string
    {
        return str_contains($this->title, 'হাতপাখা') ? $this->title : $this->title.' | হাতপাখা';
    }

    public function robots(): string
    {
        return $this->noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large';
    }

    public function ogImage(): string
    {
        return $this->image ?? asset('images/og-default.png');
    }
}
