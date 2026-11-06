<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Post;
use App\Support\ContentAudit;
use App\Support\Slug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes content-audit SUGGESTIONS for published posts (nothing is hidden or removed here).
 * Admins review and apply them in /desk → লেখা (filter: অডিট). Posts already decided are left alone.
 *   php artisan hatpakha:audit
 */
class AuditContent extends Command
{
    protected $signature = 'hatpakha:audit {--connection=legacy : Old WordPress DB, used to read old categories (optional)}
        {--apply= : Apply the OWNER-APPROVED suggestions of these labels, e.g. remove,merge,noindex}
        {--by= : Email of the admin recording the decision (required with --apply)}';

    protected $description = 'Suggest keep / improve / merge / noindex / remove for every published post';

    public function handle(): int
    {
        $oldCats = $this->oldCategories();
        $desk = Author::where('slug', 'hatpakha-desk')->value('id');
        $uploads = is_dir(public_path('wp-content/uploads/2016'));
        if (! $uploads) {
            $this->warn('Uploads folder not found here: missing-image checks are skipped.');
        }

        $posts = Post::published()->with('authors')->get();
        $dups = ContentAudit::duplicates($posts);
        $counts = array_fill_keys(array_keys(ContentAudit::LABELS), 0);
        $reasons = [];

        foreach ($posts as $post) {
            if ($post->audit_decision) {
                continue; // an admin already decided: never re-open it
            }
            $r = ContentAudit::assess($post, $oldCats, $dups[$post->id] ?? null, $desk, $uploads);
            $post->forceFill(['audit_label' => $r['label'], 'audit_reasons' => $r['reasons'] ?: null, 'audit_merge_into_id' => $r['merge_into']])->saveQuietly();
            $counts[$r['label']]++;
            foreach ($r['reasons'] as $why) {
                $key = preg_replace('/[০-৯\d]+/u', 'N', $why);
                $reasons[$key] = ($reasons[$key] ?? 0) + 1;
            }
        }

        $this->table(['Suggestion', 'Posts'], collect($counts)->map(fn ($n, $l) => [ContentAudit::LABELS[$l]." ({$l})", $n])->values()->all());
        arsort($reasons);
        $this->table(['Reason', 'Posts'], collect($reasons)->map(fn ($n, $w) => [$w, $n])->values()->all());
        $this->tierBacklog($posts);

        if ($labels = array_filter(array_map('trim', explode(',', (string) $this->option('apply'))))) {
            return $this->applyApproved($labels);
        }
        $this->info('Nothing was changed on the site. Review in /desk → লেখা → filter "অডিট".');

        return self::SUCCESS;
    }

    /**
     * Applies suggestions the owner approved (docs/AUDIT-SUMMARY.md). Logged per post, reversible,
     * and recorded under a named admin, exactly like pressing "সুপারিশ মানুন" in /desk.
     */
    private function applyApproved(array $labels): int
    {
        $admin = \App\Models\User::where('email', (string) $this->option('by'))->first();
        if (! $admin || ! $admin->hasRole(\App\Enums\Role::Admin)) {
            $this->error('--by must be the email of an admin (php artisan hatpakha:role --list).');

            return self::FAILURE;
        }
        $bad = array_diff($labels, ['remove', 'merge', 'noindex']);
        if ($bad) {
            $this->error('Only remove, merge and noindex can be applied: '.implode(', ', $bad));

            return self::FAILURE;
        }
        $todo = Post::published()->whereIn('audit_label', $labels)->whereNull('audit_decision')->get();
        if (! $this->confirm("Apply {$todo->count()} approved suggestions (".implode(', ', $labels).') as '.$admin->email.'?', true)) {
            return self::FAILURE;
        }
        foreach ($todo as $p) {
            ContentAudit::apply($p, $admin);
        }
        $this->info("Applied {$todo->count()} decisions. Undo any of them in /desk (status back to প্রকাশিত, remove the redirect, untick noindex).");

        return self::SUCCESS;
    }

    /** Pending posts → backlog tiers A/B/C (shown in /desk; nothing is closed automatically). */
    private function tierBacklog($published): void
    {
        $norm = fn ($p) => mb_strtolower(preg_replace('/[\s\p{P}]+/u', '', $p->title)).'|'.($p->authors->first()?->id ?? 0);
        $publishedKeys = $published->mapWithKeys(fn ($p) => [$norm($p) => true])->all();
        $pending = Post::where('status', 'pending')->with('authors')->get();
        $dups = ContentAudit::duplicates($pending->map(function ($p) {
            $p->published_at ??= $p->submitted_at ?? $p->created_at;

            return $p;
        }));
        $batch = $pending->groupBy(fn ($p) => $p->authors->first()?->id)->map->count()->filter(fn ($n) => $n >= 50)->keys()->first();

        $counts = ['A' => 0, 'B' => 0, 'C' => 0];
        foreach ($pending as $p) {
            $r = ContentAudit::tier($p, $publishedKeys, $batch ? (int) $batch : null, $dups);
            $p->published_at = null; // only used for ordering above; never saved
            Post::whereKey($p->id)->update(['backlog_tier' => $r['tier'], 'audit_reasons' => $r['reasons'] ? json_encode($r['reasons'], JSON_UNESCAPED_UNICODE) : null]);
            $counts[$r['tier']]++;
        }
        $this->table(['Pending tier', 'Posts'], collect($counts)->map(fn ($n, $t) => [ContentAudit::TIERS[$t], $n])->values()->all());
    }

    /** @return array<int,array<int,string>> */
    private function oldCategories(): array
    {
        try {
            $wp = DB::connection($this->option('connection'));
            $rows = $wp->table('term_relationships as tr')->join('term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->join('terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.taxonomy', 'category')->get(['tr.object_id', 't.slug']);
        } catch (\Throwable) {
            $this->warn('Old WordPress DB not reachable: old-category checks are skipped.');

            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->object_id][] = Slug::normalize((string) $r->slug);
        }

        return $out;
    }
}
