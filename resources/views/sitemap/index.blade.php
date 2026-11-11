{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($files as [$f, $lastmod])
<sitemap><loc>{{ url($f) }}</loc>@if ($lastmod)<lastmod>{{ $lastmod->toAtomString() }}</lastmod>@endif</sitemap>
@endforeach
</sitemapindex>
