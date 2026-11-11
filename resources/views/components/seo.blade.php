@props(['seo'])
{{-- All <head> SEO tags come from App\Seo\Seo (docs/SEO.md §2). Do not add meta tags elsewhere. --}}
<title>{{ $seo->fullTitle() }}</title>
<meta name="description" content="{{ $seo->description }}">
<link rel="canonical" href="{{ $seo->canonical }}">
<meta name="robots" content="{{ $seo->robots() }}">
<meta property="og:site_name" content="{{ $seo->siteName }}">
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->shareTitle ?? $seo->title }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonical }}">
<meta property="og:image" content="{{ $seo->ogImage() }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $seo->imageAlt ?? ($seo->shareTitle ?? $seo->title) }}">
<meta property="og:locale" content="{{ $seo->lang === 'en' ? 'en_IN' : 'bn_IN' }}">
@if ($seo->publishedAt)<meta property="article:published_time" content="{{ $seo->publishedAt }}">@endif
<meta name="twitter:card" content="summary_large_image">
@if (filled($fbv = config('services.social.facebook_domain_verification')) && preg_match('/^[a-z0-9]{10,64}$/', $fbv))<meta name="facebook-domain-verification" content="{{ $fbv }}">@endif
@if (filled($fbapp = config('services.social.facebook_app_id')) && preg_match('/^\d{5,20}$/', (string) $fbapp))<meta property="fb:app_id" content="{{ $fbapp }}">@endif
<link rel="alternate" type="application/rss+xml" title="হাতপাখা" href="{{ \App\Support\Url::to('/feed') }}">
@if ($seo->jsonLd)
<script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! \App\Seo\JsonLd::graph($seo->jsonLd) !!}</script>
@endif
