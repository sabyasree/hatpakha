<?php

namespace App\Ai;

use App\Models\Post;
use Anthropic\Client;
use Illuminate\Support\Facades\DB;

/**
 * D296 (owner, 2026-11-25: "move the overnight jobs and make less cost"): AI work nobody is waiting for goes to
 * Anthropic's Message Batches API, which costs half and answers within hours (usually far sooner). The jobs keep their
 * own rules: each one builds its request the same way as when it runs at once (`request()`), and applies the answer the
 * same way (`apply()`), so a batch answer can never do more than a direct one. Every hour: queued work is sent as one
 * batch, and finished batches are read back. Work a person is waiting for (a new submission, a button pressed in the
 * desk) still runs at once.
 */
final class Batch
{
    /** Tests: fn (array $requests) => [customId => ['data' => ?array, 'usage' => [...]] ] instead of the API. */
    public static ?\Closure $fake = null;

    public const MAX_PER_BATCH = 500;

    public const JOBS = ['proof', 'backlog_screen', 'seo_missing', 'topics'];

    /** Ids of pieces already waiting in a batch for this job (so the hourly shift does not queue them twice). */
    public static function waitingIds(string $job): array
    {
        return DB::table('ai_batch_items')->where('job', $job)->whereIn('status', ['queued', 'sent'])->pluck('post_ids')
            ->flatMap(fn ($j) => (array) json_decode((string) $j, true))->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public static function queue(string $job, array $postIds, array $request): void
    {
        DB::table('ai_batch_items')->insert(['job' => $job, 'post_ids' => json_encode(array_values($postIds)),
            'request' => json_encode($request, JSON_UNESCAPED_UNICODE), 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Hourly: send what is queued, read what has ended. @return array{sent:int, done:int} */
    public static function run(): array
    {
        if (Claude::blocked()) {
            return ['sent' => 0, 'done' => 0];
        }

        return ['done' => self::collect(), 'sent' => self::submit()];
    }

    public static function submit(): int
    {
        $items = DB::table('ai_batch_items')->where('status', 'queued')->orderBy('id')->limit(self::MAX_PER_BATCH)->get()
            ->filter(fn ($i) => Team::blockedFor((string) (json_decode($i->request, true)['purpose'] ?? '')) === null)->values();
        if ($items->isEmpty()) {
            return 0;
        }
        $requests = $items->map(function ($i) {
            $r = json_decode($i->request, true);

            return ['customId' => 'i'.$i->id, 'params' => [
                'model' => $r['model'], 'maxTokens' => (int) $r['max'],
                'system' => Claude::systemBlocks($r['purpose'], $r['system']),
                'messages' => [['role' => 'user', 'content' => $r['user']]],
                'outputConfig' => ['format' => ['type' => 'json_schema', 'schema' => $r['schema']]],
            ]];
        })->all();
        if (self::$fake) {
            $id = 'fake_'.uniqid();
            cache()->put('batch-fake:'.$id, (self::$fake)($requests), 3600);
        } else {
            try {
                \App\Support\DbRelease::now();
                $id = (new Client(apiKey: (string) config('ai.anthropic.key')))->messages->batches->create(requests: $requests)->id;
            } catch (\Throwable $e) {
                report($e);

                return 0;   // stays queued; tried again next hour
            }
        }
        DB::table('ai_batch_items')->whereIn('id', $items->pluck('id'))->update(['batch_id' => $id, 'status' => 'sent', 'updated_at' => now()]);

        return $items->count();
    }

    public static function collect(): int
    {
        $done = 0;
        foreach (DB::table('ai_batch_items')->where('status', 'sent')->distinct()->pluck('batch_id') as $batchId) {
            $results = self::results((string) $batchId);
            if ($results === null) {
                continue;   // still working
            }
            foreach (DB::table('ai_batch_items')->where('batch_id', $batchId)->where('status', 'sent')->get() as $item) {
                $res = $results['i'.$item->id] ?? null;
                $r = json_decode($item->request, true);
                $ids = (array) json_decode($item->post_ids, true);
                if ($res && isset($res['usage'])) {
                    $u = $res['usage'];
                    Claude::log('anthropic', $r['model'], $r['purpose'], count($ids) === 1 ? (int) $ids[0] : null, $u, ReviewService::cost($r['model'], $u) / 2);   // half price
                }
                $data = $res['data'] ?? null;
                rescue(fn () => self::apply($item->job, $ids, $r, $data, $res['error'] ?? ($data ? null : 'no answer')), null, true);
                DB::table('ai_batch_items')->where('id', $item->id)->update(['status' => $data ? 'done' : 'failed', 'error' => $data ? null : mb_substr((string) ($res['error'] ?? 'no answer'), 0, 200), 'updated_at' => now()]);
                $done++;
            }
        }
        // Old finished rows are not needed after a month.
        DB::table('ai_batch_items')->whereIn('status', ['done', 'failed'])->where('updated_at', '<', now()->subDays(30))->delete();

        return $done;
    }

    /** @return array<string, array{data:?array, usage?:array, error?:string}>|null null while the batch is still working */
    private static function results(string $batchId): ?array
    {
        if (str_starts_with($batchId, 'fake_')) {
            return cache()->get('batch-fake:'.$batchId);
        }
        try {
            $client = new Client(apiKey: (string) config('ai.anthropic.key'));
            if ($client->messages->batches->retrieve($batchId)->processingStatus !== 'ended') {
                return null;
            }
            $out = [];
            foreach ($client->messages->batches->resultsStream($batchId) as $row) {
                $res = $row->result;
                if ($res->type !== 'succeeded') {
                    $out[$row->customID] = ['data' => null, 'error' => $res->type];

                    continue;
                }
                $m = $res->message;
                $u = $m->usage;
                $usage = ['in' => (int) $u->inputTokens, 'out' => (int) $u->outputTokens, 'cache_read' => (int) $u->cacheReadInputTokens, 'cache_write' => (int) $u->cacheCreationInputTokens];
                $data = null;
                if (! in_array($m->stopReason, ['refusal', 'max_tokens'], true)) {
                    foreach ($m->content as $block) {
                        if ($block->type === 'text') {
                            $data = json_decode($block->text, true);
                            break;
                        }
                    }
                }
                $out[$row->customID] = ['data' => is_array($data) ? $data : null, 'usage' => $usage, 'error' => is_array($data) ? null : ($m->stopReason ?? 'invalid JSON')];
            }

            return $out;
        } catch (\Throwable $e) {
            report($e);

            return null;   // tried again next hour
        }
    }

    /** The job's own apply step: exactly what a direct answer would do. */
    private static function apply(string $job, array $ids, array $request, ?array $data, ?string $err): void
    {
        $posts = Post::with('section')->whereKey($ids)->get();
        match ($job) {
            'proof' => $posts->each(fn (Post $p) => in_array($p->status, ['published', 'scheduled', 'archived'], true) ? null : Proofreader::apply($p, $data, $err)),
            'backlog_screen' => $posts->each(fn (Post $p) => $p->relaunch_worth === null ? BacklogScreen::apply($p, $data, $err) : null),
            'seo_missing' => $posts->each(fn (Post $p) => $data ? SeoExpert::answer($p, $data, $err, (array) ($request['candidates'] ?? []), onlyMissing: true) : null),
            'topics' => $data ? TopicSorter::applyAnswers($posts->all(), $data)
                : Post::whereKey($ids)->where('topics_by', 'queued')->update(['topics_by' => null]),   // not answered: sorted again later
            default => null,
        };
    }

    /**
     * The hourly shift's overnight work, queued instead of run at once (D296). Each job picks exactly the pieces it
     * picked before, minus those already waiting. @return int requests queued
     */
    public static function queueOvernight(): int
    {
        if (Claude::blocked()) {
            return 0;
        }
        $n = 0;
        // Proofreader (D50): old unpublished pieces (new submissions are proofread at once when they arrive).
        $wait = self::waitingIds('proof');
        foreach (Post::where('status', 'pending')->whereNull('proof_at')->where(fn ($q) => $q->whereNotNull('submitted_by')->orWhere('relaunch_status', 'yes'))
            ->whereNotIn('id', $wait ?: [0])->orderBy('backlog_tier')->limit(50)->get() as $p) {
            if (is_array($r = Proofreader::request($p))) {
                self::queue('proof', [$p->id], $r);
                $n++;
            }
        }
        // D104: old submissions read once (worth asking the writer or not).
        $wait = self::waitingIds('backlog_screen');
        foreach (\App\Support\RelaunchLetters::pieces()->whereNull('relaunch_worth')->where('relaunch_worth_by_owner', false)
            ->whereNotIn('posts.id', $wait ?: [0])->orderByDesc('submitted_at')->limit(100)->get() as $p) {
            if (is_array($r = BacklogScreen::request($p))) {
                self::queue('backlog_screen', [$p->id], $r);
                $n++;
            }
        }
        // SEO expert (D46): old published pieces, filling only what is missing.
        $wait = self::waitingIds('seo_missing');
        foreach (Post::published()->whereNull('seo_done_at')->when(! SeoExpert::automatic(), fn ($q) => $q->whereNull('seo_proposal'))
            ->whereNotIn('id', $wait ?: [0])->orderByDesc('published_at')->limit(50)->get() as $p) {
            if (is_array($r = SeoExpert::request($p))) {
                self::queue('seo_missing', [$p->id], $r);
                $n++;
            }
        }
        // D293: topic pages, once the owner has started the sorting.
        if (TopicSorter::started()) {
            $n += self::queueTopics();
        }

        return $n;
    }

    /** Every published piece not yet sorted, twenty to a request. */
    public static function queueTopics(): int
    {
        $n = 0;
        TopicSorter::waiting()->with('section')->orderByDesc('published_at')->chunkById(TopicSorter::BATCH, function ($chunk) use (&$n) {
            $posts = $chunk->all();
            self::queue('topics', $chunk->pluck('id')->all(), TopicSorter::request($posts));
            Post::whereKey($chunk->pluck('id'))->whereNull('topics_by')->update(['topics_by' => 'queued']);
            $n++;
        });

        return $n;
    }

    /** For the desk: how much is waiting. */
    public static function counts(): array
    {
        return DB::table('ai_batch_items')->whereIn('status', ['queued', 'sent'])->selectRaw('job, count(*) as n')->groupBy('job')->pluck('n', 'job')->all();
    }
}
