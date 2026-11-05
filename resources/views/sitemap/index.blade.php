{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($files as $f)
<sitemap><loc>{{ url($f) }}</loc></sitemap>
@endforeach
</sitemapindex>
