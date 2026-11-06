<?php

namespace App\Seo;

use App\Models\Author;
use App\Models\Issue;
use App\Models\Post;
use App\Support\Url;

/** The only place JSON-LD is built (docs/SEO.md §3). Never hand-write it in templates. */
final class JsonLd
{
    public const ISSN = '2321-3035';

    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => Url::to('/').'#org',
            'name' => 'Hatpakha',
            'alternateName' => 'হাতপাখা',
            'url' => Url::to('/'),
            'logo' => asset('images/logo.png'),
        ];
    }

    public static function periodical(): array
    {
        return [
            '@type' => 'Periodical',
            '@id' => Url::to('/').'#periodical',
            'name' => 'হাতপাখা',
            'alternateName' => 'Hatpakha Magazine',
            'issn' => self::ISSN,
            'inLanguage' => 'bn',
            'url' => Url::to('/'),
            'publisher' => ['@id' => Url::to('/').'#org'],
        ];
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => Url::to('/').'#website',
            'url' => Url::to('/'),
            'name' => 'হাতপাখা',
            'inLanguage' => 'bn',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => Url::to('/search').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function person(Author $a): array
    {
        return array_filter([
            '@type' => 'Person',
            '@id' => $a->url().'#person',
            'name' => $a->publicName(),
            'alternateName' => $a->display_name_bn ? $a->display_name : null,
            'url' => $a->url(),
            'description' => $a->bio ? mb_substr(strip_tags($a->bio), 0, 300) : null,
            'image' => $a->avatar_path ? url($a->avatar_path) : null,
            'homeLocation' => $a->city ? ['@type' => 'Place', 'name' => $a->city] : null,
            'sameAs' => array_values(array_filter((array) $a->links, fn ($u) => is_string($u) && str_starts_with($u, 'https://'))) ?: null,
        ]);
    }

    public static function post(Post $p): array
    {
        $isPoem = $p->isPoem();

        return array_filter([
            '@type' => $isPoem ? 'CreativeWork' : 'Article',
            '@id' => $p->url().'#work',
            'headline' => mb_substr($p->title, 0, 110),
            'name' => $p->title,
            'genre' => $isPoem ? 'Poetry' : ($p->section?->name_en ?? null),
            'inLanguage' => $p->language,
            'url' => $p->url(),
            'mainEntityOfPage' => $p->url(),
            'datePublished' => $p->published_at?->toAtomString(),
            'dateModified' => $p->updated_at?->toAtomString(),
            'author' => $p->authors->map(fn (Author $a) => self::person($a))->values()->all(),
            'publisher' => ['@id' => Url::to('/').'#org'],
            'isPartOf' => $p->issue ? self::issue($p->issue) : ['@id' => Url::to('/').'#periodical'],
            'image' => $p->hasLargeImage() ? asset($p->featured_image_path) : null,
            'wordCount' => count(preg_split('/\s+/u', trim(strip_tags($p->body_html)), -1, PREG_SPLIT_NO_EMPTY)),
        ], fn ($v) => $v !== null && $v !== []);
    }

    public static function issue(Issue $i): array
    {
        return array_filter([
            '@type' => 'PublicationIssue',
            '@id' => $i->url().'#issue',
            'name' => $i->title,
            'url' => $i->url(),
            'datePublished' => $i->published_at?->toDateString(),
            'isPartOf' => ['@id' => Url::to('/').'#periodical'],
        ]);
    }

    /** @param array<int,array{0:string,1:string}> $crumbs [name, url] */
    public static function breadcrumbs(array $crumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn ($c, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1],
            ])->all(),
        ];
    }

    /** @param array<int,array> $nodes */
    public static function graph(array $nodes): string
    {
        return json_encode(['@context' => 'https://schema.org', '@graph' => array_values($nodes)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
