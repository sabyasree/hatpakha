<?php

namespace App\Ai;

use App\Models\EditorTask;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * The analyst (D54): monthly reading insights from our own privacy-friendly counter (no Google tools
 * needed). Numbers are computed here; the AI only turns them into a few plain observations.
 */
final class Analyst
{
    public static function stats(\Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        $rows = DB::table('post_views')->whereBetween('day', [$from->toDateString(), $to->toDateString()]);
        $tot = (clone $rows)->selectRaw('COALESCE(SUM(views),0) v, COALESCE(SUM(search),0) s, COALESCE(SUM(social),0) so, COALESCE(SUM(internal),0) i')->first();
        $top = (clone $rows)->select('post_id', DB::raw('SUM(views) v'))->groupBy('post_id')->orderByDesc('v')->limit(10)->get();
        $titles = Post::with('section')->whereIn('id', $top->pluck('post_id'))->get()->keyBy('id');
        $sections = (clone $rows)->join('posts', 'posts.id', '=', 'post_views.post_id')->join('sections', 'sections.id', '=', 'posts.section_id')
            ->select('sections.name_bn', DB::raw('SUM(views) v'))->groupBy('sections.name_bn')->orderByDesc('v')->limit(6)->pluck('v', 'name_bn');
        $old = (clone $rows)->join('posts', 'posts.id', '=', 'post_views.post_id')->where('posts.published_at', '<', $from->copy()->subYears(2))
            ->select('post_id', DB::raw('SUM(views) v'))->groupBy('post_id')->orderByDesc('v')->limit(5)->get();
        $newQuiet = Post::published()->whereBetween('published_at', [$from, $to])
            ->whereNotIn('id', (clone $rows)->select('post_id')->groupBy('post_id')->havingRaw('SUM(views) >= 10'))->limit(10)->pluck('title');

        return [
            'views' => (int) $tot->v, 'search' => (int) $tot->s, 'social' => (int) $tot->so, 'internal' => (int) $tot->i,
            'top' => $top->map(fn ($r) => ['post' => (int) $r->post_id, 'title' => $titles[$r->post_id]->title ?? '?', 'views' => (int) $r->v])->all(),
            'sections' => $sections->map(fn ($v) => (int) $v)->all(),
            'old' => $old->map(fn ($r) => ['post' => (int) $r->post_id, 'title' => Post::find($r->post_id)?->title ?? '?', 'views' => (int) $r->v])->all(),
            'new_quiet' => $newQuiet->all(),
        ];
    }

    public static function monthly(?\Carbon\CarbonInterface $month = null): ?EditorTask
    {
        $month ??= now('Asia/Kolkata')->subMonthNoOverflow()->startOfMonth();
        $key = 'insights:'.$month->format('Y-m');
        if (EditorTask::where('type', 'insights')->where('key', $key)->exists()) {
            return null;
        }
        $s = self::stats($month, $month->copy()->endOfMonth());
        if ($s['views'] === 0) {
            return null;
        }
        [$d] = Claude::json('analyst',
            'You are the analyst of a Bengali literary web magazine. From the numbers given (DATA), write 3-5 short, plain Bengali observations and one practical '
            .'suggestion each (e.g. share an old favourite again, more of a section readers like). Use only the numbers given; do not invent figures.',
            json_encode($s, JSON_UNESCAPED_UNICODE),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['points'], 'properties' => ['points' => ['type' => 'array', 'items' => ['type' => 'string']]]],
            (string) config('ai.anthropic.tags_model'), 1500);
        $s['points'] = array_slice(array_map(fn ($p) => mb_substr(trim((string) $p), 0, 400), (array) ($d['points'] ?? [])), 0, 5);

        return EditorTask::create(['type' => 'insights', 'key' => $key, 'status' => 'open', 'title' => __('পাঠকের হিসাব: ').$month->format('m/Y'), 'payload' => $s]);
    }
}
