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

    /** D290: Hatpakha's Wikidata entry (made by the owner, 2026-11-25), so Google ties the site to it. */
    public const WIKIDATA = 'https://www.wikidata.org/wiki/Q142281102';

    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => Url::to('/').'#org',
            'name' => 'Hatpakha',
            'alternateName' => ['হাতপাখা', 'Hatpakha Bengali Magazine', 'হাতপাখা পত্রিকা'],
            'description' => 'An online Bengali literary magazine: poems, stories, essays and travel writing. বাংলা অনলাইন সাহিত্য পত্রিকা।',
            'foundingDate' => '2012',
            'url' => Url::to('/'),
            'logo' => asset('images/logo.png'),
            'sameAs' => array_values(array_filter([self::WIKIDATA, config('services.social.facebook'), config('services.social.instagram'), config('services.social.whatsapp')], fn ($u) => is_string($u) && str_starts_with($u, 'https://'))) ?: null,
            // D126 (keeps D89): how to reach the desk is the contact form, never a public address or phone number.
            'contactPoint' => [
                ['@type' => 'ContactPoint', 'contactType' => 'editorial', 'url' => Url::to('/contact-us'), 'availableLanguage' => ['bn', 'en']],
                ['@type' => 'ContactPoint', 'contactType' => 'submissions', 'url' => Url::to('/submissions'), 'availableLanguage' => ['bn', 'en']],
            ],
        ];
    }

    public static function periodical(): array
    {
        return [
            '@type' => 'Periodical',
            '@id' => Url::to('/').'#periodical',
            'name' => 'হাতপাখা',
            'alternateName' => ['Hatpakha', 'Hatpakha Magazine', 'Hatpakha Bengali Magazine'],
            'description' => 'বাংলা অনলাইন সাহিত্য পত্রিকা: কবিতা, গল্প, প্রবন্ধ, ভ্রমণকথা। An online Bengali literary magazine.',
            'genre' => 'Literature',
            'startDate' => '2012',
            'issn' => self::ISSN,
            'sameAs' => [self::WIKIDATA],
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

    /** D97: a themed collection page: the pieces as an ordered list. */
    public static function collection(\App\Models\Collection $c, \Illuminate\Support\Collection $posts): array
    {
        return array_filter([
            '@type' => 'CollectionPage',
            '@id' => $c->url().'#collection',
            'name' => $c->title,
            'description' => $c->intro,
            'url' => $c->url(),
            'isPartOf' => ['@id' => Url::to('/').'#website'],
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $posts->count(),
                'itemListElement' => $posts->values()->map(fn ($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $p->url(), 'name' => $p->title])->all(),
            ],
        ]);
    }

    /** D301: a "জেনে রাখুন" page: an Article by the house; a list page also as a DefinedTermSet of its entries. */
    public static function janunPage(\App\Models\JanunPage $p): array
    {
        $nodes = [[
            '@type' => 'Article', '@id' => $p->url().'#article', 'headline' => $p->title, 'description' => (string) $p->seo_description,
            'inLanguage' => 'bn', 'url' => $p->url(), 'mainEntityOfPage' => $p->url(),
            'datePublished' => $p->publish_at?->toIso8601String(), 'dateModified' => $p->modifiedAt()?->toIso8601String(),
            'author' => ['@id' => Url::to('/').'#org'], 'publisher' => ['@id' => Url::to('/').'#org'],
        ]];
        if ($p->kind === 'poem' && filled($p->body['poet'] ?? '')) {   // D302: the poem itself, by its poet; our page carries the translation
            $nodes[0]['about'] = ['@type' => 'CreativeWork', 'name' => trim(explode(':', $p->title)[0]), 'inLanguage' => 'bn', 'author' => ['@type' => 'Person', 'name' => (string) $p->body['poet']],
                'workTranslation' => ['@type' => 'CreativeWork', 'inLanguage' => 'en', 'translator' => ['@id' => Url::to('/').'#org'], 'url' => $p->url()]];
        }
                if ($p->kind === 'list') {
            $nodes[] = ['@type' => 'DefinedTermSet', 'name' => $p->title, 'url' => $p->url(),
                'hasDefinedTerm' => collect((array) ($p->body['entries'] ?? []))->take(200)->map(fn ($e) => ['@type' => 'DefinedTerm', 'name' => (string) $e['term'], 'description' => (string) $e['meaning']])->values()->all()];
        }

        return $nodes;
    }

    /** D308: a festival's yearly page (built from checked times): an Article by the magazine, no Event markup (a holiday is not an event to attend). */
    public static function festivalPage(string $headline, string $url, $modified): array
    {
        return ['@type' => 'Article', '@id' => $url.'#article', 'headline' => $headline, 'inLanguage' => 'bn', 'url' => $url, 'mainEntityOfPage' => $url,
            'dateModified' => $modified ? \Carbon\Carbon::parse($modified)->toIso8601String() : null,
            'author' => ['@id' => Url::to('/').'#org'], 'publisher' => ['@id' => Url::to('/').'#org']];
    }

    /** D317: a free lesson for learners of Bengali. */
    public static function learningResource(string $name, string $url, string $teaches): array
    {
        return ['@type' => 'LearningResource', '@id' => $url.'#lesson', 'name' => $name, 'url' => $url, 'inLanguage' => 'bn', 'teaches' => $teaches,
            'educationalLevel' => 'Beginner', 'learningResourceType' => 'Lesson', 'isAccessibleForFree' => true,
            'audience' => ['@type' => 'EducationalAudience', 'educationalRole' => 'student'],
            'author' => ['@id' => Url::to('/').'#org'], 'publisher' => ['@id' => Url::to('/').'#org']];
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
