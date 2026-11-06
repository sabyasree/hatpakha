<?php

namespace App\Console\Commands;

use App\Support\Slug;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Zero-SEO-loss check (PLAN.md §5, docs/SEO.md §1): collects every URL the old WordPress site had and
 * checks each against the new site. Every old URL must answer 200 or 301 to a page that answers 200.
 *
 *   php artisan hatpakha:url-audit --base=https://new.hatpakha.com --sitemap=https://hatpakha.com/sitemap.xml
 *   php artisan hatpakha:url-audit --file=gsc-pages.csv     (Search Console → Pages → Export)
 *
 * Writes storage/app/url-audit.csv. Read-only on both sites (HEAD/GET requests only).
 */
class UrlAudit extends Command
{
    protected $signature = 'hatpakha:url-audit
        {--base= : Site to check (default APP_URL)}
        {--sitemap=* : Old sitemap/index URL(s) to read}
        {--file=* : CSV/TXT with one old URL per line (first column), e.g. a Search Console export}
        {--no-db : Do not derive URLs from the legacy WordPress database}
        {--connection=legacy}
        {--concurrency=8}
        {--limit=0 : Check only N URLs (testing)}';

    protected $description = 'Check that every old WordPress URL works (200) or redirects (301) on the new site';

    /** @var array<string,string> path(+query) → source */
    private array $urls = [];

    public function handle(): int
    {
        $base = rtrim($this->option('base') ?: config('app.url'), '/');

        if (! $this->option('no-db')) {
            $this->fromDatabase(DB::connection($this->option('connection')));
        }
        foreach ((array) $this->option('sitemap') as $sm) {
            $this->fromSitemap($sm);
        }
        foreach ((array) $this->option('file') as $f) {
            $this->fromFile($f);
        }

        $paths = array_keys($this->urls);
        if ($limit = (int) $this->option('limit')) {
            $paths = array_slice($paths, 0, $limit);
        }
        $this->info(count($paths)." old URLs to check against {$base}");

        $rows = [];
        $bar = $this->output->createProgressBar(count($paths));
        foreach (array_chunk($paths, max(1, (int) $this->option('concurrency'))) as $chunk) {
            foreach ($this->checkChunk($base, $chunk) as $path => $r) {
                $rows[] = ['old' => $path, 'source' => $this->urls[$path]] + $r;
            }
            $bar->advance(count($chunk));
        }
        $bar->finish();
        $this->newLine(2);

        $out = storage_path('app/url-audit.csv');
        $fh = fopen($out, 'w');
        fputcsv($fh, ['old_url', 'source', 'status', 'location', 'final_status', 'verdict']);
        foreach ($rows as $r) {
            fputcsv($fh, [$r['old'], $r['source'], $r['status'], $r['location'], $r['final'], $r['verdict']]);
        }
        fclose($fh);

        $summary = collect($rows)->groupBy('source')->map(fn ($g, $src) => [
            $src, $g->count(), $g->where('verdict', 'ok')->count(), $g->where('verdict', 'redirect')->count(), $g->where('verdict', 'broken')->count(),
        ])->values()->all();
        $this->table(['Source', 'URLs', '200', '301 → 200', 'Broken'], $summary);
        $broken = collect($rows)->where('verdict', 'broken');
        if ($broken->isNotEmpty()) {
            $this->warn('Broken examples (first 25):');
            $this->table(['Old URL', 'Status', 'Location', 'Final'], $broken->take(25)->map(fn ($r) => [mb_substr(rawurldecode($r['old']), 0, 70), $r['status'], mb_substr(rawurldecode((string) $r['location']), 0, 50), $r['final']])->all());
        }
        $this->line("Full report: {$out}");

        return $broken->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    private function add(string $url, string $source): void
    {
        $p = parse_url(trim($url));
        if (! $p || (isset($p['host']) && ! preg_match('/(^|\.)hatpakha\.com$/i', $p['host']))) {
            return;
        }
        $path = $p['path'] ?? '/';
        // Store a canonical encoded form so the same URL from two sources is checked once.
        $path = implode('/', array_map(fn ($s) => rawurlencode(rawurldecode($s)), explode('/', $path)));
        $key = $path.(isset($p['query']) ? '?'.$p['query'] : '');
        $this->urls[$key] ??= $source;
    }

    private function fromDatabase($wp): void
    {
        $posts = $wp->table('posts')->where('post_type', 'post')->where('post_status', 'publish')->get(['ID', 'post_name']);
        foreach ($posts as $p) {
            $slug = Slug::normalize((string) $p->post_name);
            if ($slug === '') {
                continue;
            }
            $this->add("/{$slug}/", 'db: post');
            $this->add("/{$slug}/amp/", 'db: amp');
            $this->add("/{$slug}/feed/", 'db: comment feed');
            $this->add("/?p={$p->ID}", 'db: ?p=ID');
        }
        foreach ($wp->table('posts')->where('post_type', 'page')->where('post_status', 'publish')->pluck('post_name') as $slug) {
            $this->add('/'.Slug::normalize((string) $slug).'/', 'db: page');
        }
        $terms = $wp->table('terms as t')->join('term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')
            ->whereIn('tt.taxonomy', ['category', 'post_tag'])->where('tt.count', '>', 0)->get(['t.slug', 'tt.taxonomy']);
        foreach ($terms as $t) {
            $base = $t->taxonomy === 'category' ? 'category' : 'tag';
            $this->add("/{$base}/".Slug::normalize((string) $t->slug).'/', "db: {$base}");
        }
        $authors = $wp->table('users as u')->join('posts as p', 'p.post_author', '=', 'u.ID')
            ->where('p.post_type', 'post')->where('p.post_status', 'publish')->distinct()->pluck('u.user_nicename');
        foreach ($authors as $n) {
            $this->add('/author/'.Slug::normalize((string) $n).'/', 'db: author');
        }
        foreach (['/feed/', '/comments/feed/', '/page/2/', '/category/poetry/page/2/', '/2016/', '/2016/03/', '/2016/03/15/', '/?s=kobita', '/?cat=1', '/?page_id=47', '/?author=1', '/wp-login.php', '/xmlrpc.php'] as $u) {
            $this->add($u, 'db: patterns');
        }
    }

    private function fromSitemap(string $url, int $depth = 0): void
    {
        if ($depth > 2) {
            return;
        }
        $xml = Http::timeout(30)->withUserAgent('HatpakhaMigrationCheck/1.0')->get($url)->body();
        preg_match_all('~<loc>\s*([^<\s]+)\s*</loc>~', $xml, $m);
        $name = basename(parse_url($url, PHP_URL_PATH) ?: $url);
        foreach ($m[1] as $loc) {
            $loc = html_entity_decode($loc);
            if (preg_match('~\.xml(\?|$)~', $loc)) {
                $this->fromSitemap($loc, $depth + 1);
            } elseif (! preg_match('~/wp-content/~', $loc)) {
                $this->add($loc, 'sitemap: '.preg_replace('/\d*\.xml$/', '', $name));
            }
        }
    }

    private function fromFile(string $file): void
    {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $first = str_getcsv($line)[0] ?? '';
            if (str_starts_with($first, 'http') || str_starts_with($first, '/')) {
                $this->add($first, 'file: '.basename($file));
            }
        }
    }

    /**
     * @param  array<int,string>  $paths
     * @return array<string,array{status:int,location:?string,final:?int,verdict:string}>
     */
    private function checkChunk(string $base, array $paths): array
    {
        $first = $this->fetchAll(array_combine($paths, array_map(fn ($p) => $base.$p, $paths)));

        // Follow redirects (up to 3 hops), all chains of this chunk in parallel.
        $current = [];
        foreach ($first as $p => $r) {
            if (in_array($r['status'], [301, 308], true) && $r['location']) {
                $current[$p] = $this->absolute($base, $r['location']);
            }
        }
        $final = [];
        for ($hop = 0; $hop < 3 && $current; $hop++) {
            $next = [];
            foreach ($this->fetchAll($current) as $p => $r) {
                if (in_array($r['status'], [301, 308], true) && $r['location']) {
                    $next[$p] = $this->absolute($base, $r['location']);
                } else {
                    $final[$p] = $r['status'];
                }
            }
            $current = $next;
        }

        $out = [];
        foreach ($paths as $p) {
            $status = $first[$p]['status'];
            $loc = $first[$p]['location'];
            $fin = $final[$p] ?? null;
            $verdict = match (true) {
                $status === 200 => 'ok',
                in_array($status, [301, 308], true) && $fin === 200 => 'redirect',
                // Deliberate 410s for WordPress attack paths count as correct.
                $status === 410 && (bool) preg_match('~^/(wp-login\.php|xmlrpc\.php)~', $p) => 'ok',
                default => 'broken',
            };
            $out[$p] = ['status' => $status, 'location' => $loc, 'final' => $fin, 'verdict' => $verdict];
        }

        return $out;
    }

    /**
     * GET many URLs in parallel (no redirect following). 502/503/504 and timeouts are retried,
     * so a deploy's few seconds of maintenance mode never show up as "broken".
     *
     * @param  array<string,string>  $urls  key → absolute URL
     * @return array<string,array{status:int,location:?string}>
     */
    private function fetchAll(array $urls): array
    {
        $result = [];
        for ($attempt = 0; $attempt < 4 && $urls; $attempt++) {
            if ($attempt > 0) {
                sleep(5 * $attempt);
            }
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($key) => $pool->as((string) $key)->timeout(30)->withUserAgent('HatpakhaMigrationCheck/1.0')->withoutRedirecting()->get($urls[$key]),
                array_keys($urls)
            ));
            $retry = [];
            foreach ($urls as $key => $url) {
                $r = $responses[$key] ?? null;
                $status = $r instanceof \Illuminate\Http\Client\Response ? $r->status() : 0;
                if (in_array($status, [0, 500, 502, 503, 504], true) && $attempt < 3) {
                    $retry[$key] = $url;

                    continue;
                }
                $result[$key] = ['status' => $status, 'location' => $r instanceof \Illuminate\Http\Client\Response ? ($r->header('Location') ?: null) : null];
            }
            $urls = $retry;
        }

        return $result;
    }

    private function absolute(string $base, string $loc): string
    {
        return str_starts_with($loc, 'http') ? $loc : $base.$loc;
    }
}
