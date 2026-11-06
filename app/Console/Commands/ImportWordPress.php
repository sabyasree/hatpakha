<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Section;
use App\Models\Tag;
use App\Models\User;
use App\Enums\Role;
use App\Support\Slug;
use App\Support\WpContent;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Imports the old WordPress site (docs/ARCHITECTURE.md → Migration, docs/DB-AUDIT.md).
 * Idempotent: every record is matched on its legacy WordPress ID, so it can be re-run
 * for the final delta before DNS cutover. Reads ONLY from the "legacy" connection.
 */
class ImportWordPress extends Command
{
    protected $signature = 'hatpakha:import-wp {--connection=legacy} {--limit=0 : Import only N posts (testing)} {--pages : Only import/refresh static pages} {--overwrite-edited : Also overwrite posts edited in /desk (owner decision only)}';

    protected $description = 'Import users, authors, sections, tags, posts (all statuses) and comments from the old WordPress database';

    /** Old category slug → new section slug. Unlisted categories fall back to "social". */
    private const SECTION_MAP = [
        'poetry' => 'poetry', 'sher-o-shayari' => 'sher-o-shayari',
        'short-stories' => 'short-stories', 'fiction' => 'short-stories',
        'literature' => 'literature', 'books' => 'literature', 'reviews' => 'literature',
        'mythology' => 'literature', 'interviews' => 'literature',
        'editorial' => 'editorial', 'travel' => 'travel', 'translated' => 'translated',
        'inspiration' => 'inspiration', 'life' => 'inspiration', 'lifestyle' => 'inspiration',
        'social' => 'social', 'health' => 'social', 'technology' => 'social',
    ];

    /** When a post has several categories, the first match in this order wins. */
    private const SECTION_PRIORITY = ['poetry', 'sher-o-shayari', 'short-stories', 'travel', 'translated', 'literature', 'editorial', 'inspiration', 'social'];

    private const STATUS_MAP = ['publish' => 'published', 'pending' => 'pending', 'draft' => 'draft', 'future' => 'scheduled', 'private' => 'archived'];

    private ConnectionInterface $wp;

    /** @var array<int,int> */ private array $userMap = [];
    /** @var array<int,int> */ private array $authorByWpUser = [];
    /** @var array<int,int> */ private array $authorByTerm = [];
    /** @var array<int,int> */ private array $tagMap = [];
    /** @var array<int,string> */ private array $catSlug = [];
    /** @var array<string,int> */ private array $sectionId = [];

    public function handle(): int
    {
        $this->wp = DB::connection($this->option('connection'));
        $this->call('db:seed', ['--force' => true]);
        $this->sectionId = Section::pluck('id', 'slug')->all();

        if ($this->option('pages')) {
            $this->importPages();

            return self::SUCCESS;
        }

        $this->importSections();
        $this->importTags();
        $this->importUsers();
        $this->importCoAuthors();
        $this->importPosts();
        $this->importComments();
        $this->importPages();
        $this->refreshCounts();

        $this->newLine();
        $this->table(['Table', 'Rows'], collect(['users', 'authors', 'sections', 'tags', 'posts', 'pages', 'comments', 'redirects'])
            ->map(fn ($t) => [$t, DB::table($t)->count()])->all());
        $this->table(['Post status', 'Rows'], Post::query()->selectRaw('status, count(*) c')->groupBy('status')->get()->map(fn ($r) => [$r->status, $r->c])->all());

        return self::SUCCESS;
    }

    private function importSections(): void
    {
        $cats = $this->wp->table('terms as t')->join('term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')
            ->where('tt.taxonomy', 'category')->get(['t.term_id', 't.slug']);
        foreach ($cats as $c) {
            $old = Slug::normalize($c->slug);
            $this->catSlug[$c->term_id] = $old;
            $new = self::SECTION_MAP[$old] ?? 'social';
            if ($old !== $new) {
                // Merged category: keep the old URL working (docs/SEO.md §1).
                Redirect::updateOrCreate(['from_path' => "/category/{$old}/"], ['to_path' => "/category/{$new}/", 'status_code' => 301]);
            }
        }
        $this->info('Sections mapped: '.count($cats).' categories → '.count($this->sectionId).' sections');
    }

    private function importTags(): void
    {
        $rows = $this->wp->table('terms as t')->join('term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')
            ->where('tt.taxonomy', 'post_tag')->get(['tt.term_taxonomy_id', 't.term_id', 't.slug', 't.name']);
        foreach ($rows as $r) {
            $slug = Slug::normalize($r->slug) ?: 'tag-'.$r->term_id;
            $tag = Tag::updateOrCreate(['legacy_term_id' => $r->term_id], ['slug' => $this->uniqueSlug(Tag::class, $slug, $r->term_id), 'name' => html_entity_decode($r->name)]);
            $this->tagMap[$r->term_taxonomy_id] = $tag->id;
        }
        $this->info('Tags: '.count($rows));
    }

    private function importUsers(): void
    {
        $roles = $this->wp->table('usermeta')->where('meta_key', $this->wp->getTablePrefix().'capabilities')->pluck('meta_value', 'user_id');
        $bios = $this->wp->table('usermeta')->where('meta_key', 'description')->pluck('meta_value', 'user_id');
        $postAuthors = $this->wp->table('posts')->where('post_type', 'post')->distinct()->pluck('post_author')->flip();

        $this->wp->table('users')->orderBy('ID')->chunk(500, function ($rows) use ($roles, $bios, $postAuthors) {
            foreach ($rows as $u) {
                $email = strtolower(trim($u->user_email)) ?: "wp-user-{$u->ID}@invalid.hatpakha.local";
                if (User::where('email', $email)->where('legacy_wp_id', '!=', $u->ID)->exists()) {
                    $email = "wp-user-{$u->ID}+dup@invalid.hatpakha.local";
                }
                $role = $this->mapRole((string) ($roles[$u->ID] ?? ''), isset($postAuthors[$u->ID]));
                // forceFill: legacy_wp_id / legacy_password are intentionally NOT mass-assignable.
                $user = User::firstOrNew(['legacy_wp_id' => $u->ID]);
                $user->forceFill([
                    'legacy_wp_id' => $u->ID,
                    'name' => html_entity_decode($u->display_name ?: $u->user_login),
                    'email' => $email,
                    // Keep a role an admin already changed in the new system; otherwise map it.
                    'role' => $user->exists ? $user->role : $role,
                    // Legacy hash is stored as-is (never through the "hashed" cast) until first login.
                    'legacy_password' => $user->password ? null : ($u->user_pass ?: null),
                    'created_at' => $u->user_registered,
                ])->save();
                $this->userMap[$u->ID] = $user->id;

                if (isset($postAuthors[$u->ID])) {
                    $isDesk = (int) $u->ID === 1 || str_contains(strtolower($u->display_name), 'hatpakha');
                    $author = $isDesk
                        ? Author::where('slug', 'hatpakha-desk')->first()
                        : Author::updateOrCreate(['legacy_wp_user_id' => $u->ID], [
                            'user_id' => $user->id,
                            'slug' => $this->uniqueSlug(Author::class, Slug::normalize($u->user_nicename) ?: 'writer-'.$u->ID, $u->ID, 'legacy_wp_user_id'),
                            'display_name' => html_entity_decode($u->display_name ?: $u->user_login),
                            'bio' => isset($bios[$u->ID]) ? strip_tags($bios[$u->ID]) : null,
                        ]);
                    $this->authorByWpUser[$u->ID] = $author->id;
                }
            }
        });
        $this->info('Users: '.count($this->userMap).', author bylines: '.count($this->authorByWpUser));
    }

    private function importCoAuthors(): void
    {
        $terms = $this->wp->table('terms as t')->join('term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')
            ->where('tt.taxonomy', 'author')->get(['tt.term_taxonomy_id', 't.term_id', 't.slug', 't.name']);
        $nicenameToWpUser = $this->wp->table('users')->pluck('ID', 'user_nicename');
        $guestNames = $this->wp->table('posts as p')->join('postmeta as m', 'm.post_id', '=', 'p.ID')
            ->where('p.post_type', 'guest-author')->where('m.meta_key', 'cap-display_name')->pluck('m.meta_value', 'p.post_name');

        foreach ($terms as $t) {
            $nicename = preg_replace('/^cap-/', '', $t->slug);
            if (isset($nicenameToWpUser[$nicename]) && isset($this->authorByWpUser[$nicenameToWpUser[$nicename]])) {
                $this->authorByTerm[$t->term_taxonomy_id] = $this->authorByWpUser[$nicenameToWpUser[$nicename]];
                continue;
            }
            if (isset($nicenameToWpUser[$nicename])) {
                // A registered user who only appears through Co-Authors: give them a byline.
                $wpUserId = $nicenameToWpUser[$nicename];
                $user = User::where('legacy_wp_id', $wpUserId)->first();
                $author = Author::updateOrCreate(['legacy_wp_user_id' => $wpUserId], [
                    'user_id' => $user?->id,
                    'slug' => $this->uniqueSlug(Author::class, Slug::normalize($nicename), $wpUserId, 'legacy_wp_user_id'),
                    'display_name' => $user?->name ?? html_entity_decode($t->name),
                ]);
                $this->authorByWpUser[$wpUserId] = $author->id;
                $this->authorByTerm[$t->term_taxonomy_id] = $author->id;
                continue;
            }
            $author = Author::updateOrCreate(['legacy_coauthor_term_id' => $t->term_id], [
                'slug' => $this->uniqueSlug(Author::class, Slug::normalize($nicename) ?: 'guest-'.$t->term_id, $t->term_id, 'legacy_coauthor_term_id'),
                'display_name' => html_entity_decode($guestNames[$t->slug] ?? $t->name),
                'is_guest' => true,
            ]);
            $this->authorByTerm[$t->term_taxonomy_id] = $author->id;
        }
        $this->info('Co-Author terms: '.count($terms));
    }

    private function importPosts(): void
    {
        $q = $this->wp->table('posts')->where('post_type', 'post')->whereIn('post_status', array_keys(self::STATUS_MAP))->orderBy('ID');
        if ($limit = (int) $this->option('limit')) {
            $q->limit($limit);
        }
        $bar = $this->output->createProgressBar((clone $q)->count());

        $q->chunk(200, function ($rows) use ($bar) {
            $ids = $rows->pluck('ID')->all();
            $meta = $this->wp->table('postmeta')->whereIn('post_id', $ids)
                ->whereIn('meta_key', ['_thumbnail_id', '_yoast_wpseo_title', '_yoast_wpseo_metadesc'])->get()->groupBy('post_id');
            $rels = $this->wp->table('term_relationships as tr')->join('term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->whereIn('tr.object_id', $ids)->orderBy('tr.term_order')->get(['tr.object_id', 'tt.taxonomy', 'tt.term_taxonomy_id', 'tt.term_id'])->groupBy('object_id');
            $thumbIds = $meta->flatten()->where('meta_key', '_thumbnail_id')->pluck('meta_value')->filter()->all();
            $thumbs = $this->wp->table('postmeta')->whereIn('post_id', $thumbIds)->whereIn('meta_key', ['_wp_attached_file', '_wp_attachment_metadata'])->get()->groupBy('post_id');

            DB::transaction(function () use ($rows, $meta, $rels, $thumbs, $bar) {
                foreach ($rows as $p) {
                    $this->importPost($p, $meta->get($p->ID, collect()), $rels->get($p->ID, collect()), $thumbs);
                    $bar->advance();
                }
            });
        });
        $bar->finish();
        $this->newLine();
        if ($this->skippedEdited) {
            $this->warn("{$this->skippedEdited} posts edited in /desk were kept as they are (use --overwrite-edited to replace them).");
        }
    }

    private int $skippedEdited = 0;

    public static function editedInDesk(int $legacyId): bool
    {
        return Post::withTrashed()->where('legacy_wp_id', $legacyId)->whereNotNull('edited_in_desk_at')->exists();
    }

    private function importPost(object $p, $meta, $rels, $thumbs): void
    {
        // Never overwrite an editor's work: posts saved in /desk keep their current version (Phase 2).
        if (! $this->option('overwrite-edited') && self::editedInDesk((int) $p->ID)) {
            $this->skippedEdited++;

            return;
        }

        $m = $meta->pluck('meta_value', 'meta_key');
        $title = trim(html_entity_decode($p->post_title, ENT_QUOTES | ENT_HTML5));
        $sample = $title.' '.mb_substr(strip_tags($p->post_content), 0, 2000);
        // Bengali letters (not the shared danda "।", which Bengali also uses from the Devanagari block).
        $isBengali = (bool) preg_match('/[\x{0985}-\x{09B9}]/u', $sample);
        $isDevanagari = ! $isBengali && preg_match('/[\x{0905}-\x{0939}]/u', $sample);
        $language = $isBengali ? 'bn' : ($isDevanagari ? 'hi' : 'en');

        $catSlugs = $rels->where('taxonomy', 'category')->map(fn ($r) => self::SECTION_MAP[$this->catSlug[$r->term_id] ?? ''] ?? null)->filter()->all();
        $byCategory = collect(self::SECTION_PRIORITY)->first(fn ($s) => in_array($s, $catSlugs, true));
        // English-language pieces go to the English section, except shayari written in Latin script.
        $sectionSlug = $language === 'en' && $byCategory !== 'sher-o-shayari' ? 'english' : ($byCategory ?? 'social');
        $format = in_array($sectionSlug, ['poetry', 'sher-o-shayari'], true) ? 'poem' : 'prose';

        $slug = Slug::normalize((string) $p->post_name);
        $slug = $this->uniqueSlug(Post::class, $slug !== '' ? $slug : 'wp-'.$p->ID, $p->ID, 'legacy_wp_id');

        $seoTitle = $m['_yoast_wpseo_title'] ?? null;
        $seoDesc = $m['_yoast_wpseo_metadesc'] ?? null;

        [$imgPath, $imgW, $imgH] = $this->featuredImage($m['_thumbnail_id'] ?? null, $thumbs);
        $status = self::STATUS_MAP[$p->post_status];
        $date = ($p->post_date_gmt && ! str_starts_with($p->post_date_gmt, '0000')) ? $p->post_date_gmt : $p->post_date;

        $post = Post::withTrashed()->updateOrCreate(['legacy_wp_id' => $p->ID], [
            'slug' => $slug,
            'title' => $title !== '' ? $title : '(শিরোনামহীন)',
            'excerpt' => $p->post_excerpt ? strip_tags(html_entity_decode($p->post_excerpt)) : null,
            'body_html' => self::autop(WpContent::convert((string) $p->post_content, $this->attachmentImages(...))),
            'format' => $format,
            'language' => $language,
            'status' => $status,
            'submitted_at' => $status === 'pending' ? $date : null,
            'published_at' => in_array($status, ['published', 'scheduled', 'archived'], true) ? $date : null,
            'section_id' => $this->sectionId[$sectionSlug] ?? null,
            'featured_image_path' => $imgPath,
            'featured_image_width' => $imgW,
            'featured_image_height' => $imgH,
            // Yoast templates like "%%title%%" are not real titles; leave them for AI + editor.
            'seo_title' => ($seoTitle && ! str_contains($seoTitle, '%%')) ? mb_substr($seoTitle, 0, 250) : null,
            'seo_description' => ($seoDesc && ! str_contains($seoDesc, '%%')) ? mb_substr($seoDesc, 0, 320) : null,
        ]);
        $post->forceFill(['created_at' => $p->post_date, 'updated_at' => $p->post_modified])->saveQuietly();

        $authorIds = $rels->where('taxonomy', 'author')->map(fn ($r) => $this->authorByTerm[$r->term_taxonomy_id] ?? null)->filter()->unique()->values();
        if ($authorIds->isEmpty()) {
            $authorIds = collect([$this->authorByWpUser[$p->post_author] ?? Author::where('slug', 'hatpakha-desk')->value('id')]);
        }
        $post->authors()->sync($authorIds->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])->all());
        $post->tags()->sync($rels->where('taxonomy', 'post_tag')->map(fn ($r) => $this->tagMap[$r->term_taxonomy_id] ?? null)->filter()->unique()->all());
    }

    /** @return array{0:?string,1:?int,2:?int} */
    private function featuredImage(?string $thumbId, $thumbs): array
    {
        if (! $thumbId || ! isset($thumbs[$thumbId])) {
            return [null, null, null];
        }
        $tm = $thumbs[$thumbId]->pluck('meta_value', 'meta_key');
        $file = $tm['_wp_attached_file'] ?? null;
        if (! $file || str_contains($file, '..') || ! preg_match('/\.(jpe?g|png|gif|webp)$/i', $file)) {
            return [null, null, null];
        }
        // Serialized PHP from WordPress: never allow object instantiation (docs/SECURITY.md §4).
        $md = @unserialize((string) ($tm['_wp_attachment_metadata'] ?? ''), ['allowed_classes' => false]);

        return ['wp-content/uploads/'.ltrim($file, '/'), is_array($md) ? ($md['width'] ?? null) : null, is_array($md) ? ($md['height'] ?? null) : null];
    }

    private function importComments(): void
    {
        $postMap = Post::withTrashed()->whereNotNull('legacy_wp_id')->pluck('id', 'legacy_wp_id');
        $count = 0;
        $this->wp->table('comments')->where('comment_approved', '1')->whereIn('comment_type', ['', 'comment'])->orderBy('comment_ID')
            ->chunk(500, function ($rows) use ($postMap, &$count) {
                foreach ($rows as $c) {
                    if (! isset($postMap[$c->comment_post_ID])) {
                        continue;
                    }
                    Comment::updateOrCreate(['legacy_wp_id' => $c->comment_ID], [
                        'post_id' => $postMap[$c->comment_post_ID],
                        'user_id' => $this->userMap[$c->user_id] ?? null,
                        'author_name' => mb_substr(html_entity_decode(strip_tags($c->comment_author)), 0, 190) ?: 'পাঠক',
                        'body' => trim(html_entity_decode(strip_tags($c->comment_content))),
                        'status' => 'approved',
                        'created_at' => $c->comment_date_gmt,
                    ]);
                    $count++;
                }
            });
        $this->info("Comments: {$count}");
    }

    private function refreshCounts(): void
    {
        DB::table('authors')->update(['published_count' => DB::raw(
            "(select count(*) from author_post ap join posts p on p.id = ap.post_id where ap.author_id = authors.id and p.status = 'published' and p.deleted_at is null)"
        )]);
    }

    private function mapRole(string $caps, bool $hasPosts): Role
    {
        $role = match (true) {
            str_contains($caps, '"administrator"') => Role::Admin,
            str_contains($caps, '"editor"') => Role::Editor,
            str_contains($caps, '"author"'), str_contains($caps, '"contributor"') => Role::Writer,
            default => Role::Reader,
        };

        return ($hasPosts && $role === Role::Reader) ? Role::Writer : $role;
    }

    /** Ensures uniqueness; a clash gets "-{legacyId}" appended. Re-runs keep the same slug. */
    private function uniqueSlug(string $model, string $slug, int|string $legacyId, string $legacyCol = 'legacy_term_id'): string
    {
        $slug = mb_substr($slug, 0, 380);
        $clash = $model::query()->when(method_exists($model, 'bootSoftDeletes'), fn ($q) => $q->withTrashed())
            ->where('slug', $slug)->where(fn ($q) => $q->whereNull($legacyCol)->orWhere($legacyCol, '!=', $legacyId))->exists();

        return $clash ? "{$slug}-{$legacyId}" : $slug;
    }

    /**
     * Old WordPress pages that hold plugin screens (login, cart, …): their URLs 301 to the home page.
     * Everything else published is imported as a Page at the same URL.
     */
    private const SYSTEM_PAGES = ['connections', 'edit-post', 'authors', 'user', 'login', 'register', 'members', 'logout',
        'account', 'password-reset', 'home-4', 'tds-checkout', 'tds-my-account', 'tds-login-register'];

    /**
     * Pages the editor rewrote for the new magazine live in database/content/pages/{slug}.html
     * (front matter: title, seo_description, noindex, footer, sort). See docs/PAGES-REVIEW.md.
     * An existing page is never overwritten, so edits made in /desk survive a re-import.
     */
    private function importPages(): void
    {
        $count = 0;
        foreach (self::SYSTEM_PAGES as $slug) {
            Redirect::updateOrCreate(['from_path' => "/{$slug}/"], ['to_path' => '/', 'status_code' => 301]);
        }

        $rows = $this->wp->table('posts')->where('post_type', 'page')->where('post_status', 'publish')
            ->whereNotIn('post_name', self::SYSTEM_PAGES)->get(['ID', 'post_name', 'post_title', 'post_content', 'post_date', 'post_modified']);

        foreach ($rows as $p) {
            $slug = Slug::normalize((string) $p->post_name);
            if ($slug === '' || Page::where('legacy_wp_id', $p->ID)->orWhere('slug', $slug)->exists()) {
                continue;
            }
            $revised = self::revisedPage($slug);
            Page::create([
                'slug' => $slug,
                'legacy_wp_id' => $p->ID,
                'title' => $revised['title'] ?? trim(html_entity_decode($p->post_title, ENT_QUOTES | ENT_HTML5)),
                'body_html' => $revised['body'] ?? self::autop(WpContent::convert((string) $p->post_content)),
                'seo_description' => $revised['seo_description'] ?? null,
                'noindex' => (bool) ($revised['noindex'] ?? true), // un-reviewed old pages stay out of Google
                'show_in_footer' => (bool) ($revised['footer'] ?? false),
                'sort' => (int) ($revised['sort'] ?? 100),
            ]);
            $count++;
        }
        $this->info("Pages: {$count} new");
    }

    /** @return array<string,string>|null front matter + 'body' from database/content/pages/{slug}.html */
    public static function revisedPage(string $slug): ?array
    {
        $file = database_path('content/pages/'.basename($slug).'.html');
        if (! is_file($file)) {
            return null;
        }
        $raw = (string) file_get_contents($file);
        $meta = [];
        if (preg_match('/\A---\n(.*?)\n---\n/s', $raw, $m)) {
            foreach (explode("\n", $m[1]) as $line) {
                if (str_contains($line, ':')) {
                    [$k, $v] = array_map('trim', explode(':', $line, 2));
                    $meta[$k] = $v === 'true' ? '1' : ($v === 'false' ? '0' : $v);
                }
            }
            $raw = substr($raw, strlen($m[0]));
        }

        return $meta + ['body' => trim($raw)];
    }

    /**
     * Gallery attachment IDs → image paths (for [gallery ids="…"]).
     *
     * @param  array<int,int>  $ids
     * @return array<int,array{src:string,alt:string}>
     */
    private function attachmentImages(array $ids): array
    {
        $files = $this->wp->table('postmeta')->whereIn('post_id', $ids)->where('meta_key', '_wp_attached_file')->pluck('meta_value', 'post_id');
        $alts = $this->wp->table('postmeta')->whereIn('post_id', $ids)->where('meta_key', '_wp_attachment_image_alt')->pluck('meta_value', 'post_id');
        $out = [];
        foreach ($ids as $id) {
            $file = $files[$id] ?? null;
            if ($file && ! str_contains($file, '..') && preg_match('/\.(jpe?g|png|gif|webp)$/i', $file)) {
                $out[] = ['src' => '/wp-content/uploads/'.ltrim($file, '/'), 'alt' => (string) ($alts[$id] ?? '')];
            }
        }

        return $out;
    }

    /** Minimal port of WordPress wpautop(): blank lines → paragraphs, single newlines → <br>. */
    public static function autop(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return '';
        }
        if (preg_match('/<p[\s>]/i', $text)) {
            return $text; // already has paragraphs (block editor content)
        }
        $blocks = preg_split('/\n\s*\n/', $text);
        $out = [];
        foreach ($blocks as $b) {
            $b = trim($b);
            if ($b === '') {
                continue;
            }
            $out[] = preg_match('/^<(h[1-6]|ul|ol|blockquote|figure|table|div|hr|pre)/i', $b)
                ? $b
                : '<p>'.nl2br($b, false).'</p>';
        }

        return implode("\n", $out);
    }
}
