<?php

namespace App\Ai;

use App\Models\EditorTask;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Models\Setting;

/**
 * The strategist (D63): monthly (or on the owner's request) market and competitor research on the web,
 * read together with our own numbers and the trend scout's news, turned into a short strategy memo with
 * priorities for the team. The owner reads and decides; the current priorities are shared with the
 * editor, marketing, copywriter and editor-in-chief so the month's work follows them.
 */
final class Strategist
{
    public static function facts(): string
    {
        $month = now()->subDays(30);
        $s = Analyst::stats($month, now());
        $lines = [
            'Our last 30 days: '.$s['views'].' reads (search '.$s['search'].', social '.$s['social'].', inside site '.$s['internal'].')',
            'Most read: '.collect($s['top'])->take(5)->map(fn ($t) => $t['title'].' ('.$t['views'].')')->implode('; '),
            'Sections read most: '.collect($s['sections'])->map(fn ($v, $k) => "$k $v")->implode(', '),
            'Submissions received in 30 days: '.Post::whereNotNull('submitted_by')->where('submitted_at', '>=', $month)->count()
                .'; published in 30 days: '.Post::published()->where('published_at', '>=', $month)->count()
                .'; old submissions still waiting: '.Post::where('status', 'pending')->count(),
            'Newsletter readers (confirmed sign-ups): '.NewsletterSubscriber::mailable()->count(),
        ];
        if ($t = Knowledge::trends()) {
            $lines[] = 'Trend scout ('.$t['at'].'): '.collect($t['items'])->pluck('title')->implode('; ');
        }
        $lines[] = 'Our current positioning (docs/STRATEGY.md): edited, fast and kind for writers; one good piece in a few minutes for readers; '
            .'the care of a little magazine with the speed of the internet and the reach of social media. Competitors we watch: Pratilipi, '
            .'পরবাস, কৌরব, কল্পবিশ্ব, Facebook writing groups, print শারদীয়া annuals, Bengali audio apps.';

        return implode("\n", $lines);
    }

    public static function run(): ?string
    {
        [$d, $err] = Claude::search('strategy',
            'You are the strategist of Hatpakha (হাতপাখা), a Bengali literary web magazine (since 2012, free to publish, edited, ISSN). '
            .'All campaigns are ONLINE ONLY (site, newsletter, social media, online contests, e-books); never propose anything physical. '
            .'Research on the web what Bengali literary platforms and magazines (and Bengali readers and writers online) are doing now: new features, '
            .'formats that grow, campaigns, events in the next two months. Then read our own numbers (DATA) and write a short strategy memo for the owner '
            .'and the editor, in simple Bengali: what changed in the market (with source URLs), where we are strong or weak, and 3-6 concrete priorities '
            .'for the next month, each assigned to a team member (editor, marketing, copywriter, designer, seo, contest, archivist, analyst, owner) '
            .'with a measurable check. Be honest; never invent numbers; do not suggest paying for ads or anything against free publishing. '
            .'Answer ONLY with JSON: {"summary":"...","market":[{"point":"...","url":"https://..."}],"strengths":["..."],"weaknesses":["..."],'
            .'"priorities":[{"what":"...","who":"editor","check":"..."}]}',
            self::facts(), 8);
        if (! $d) {
            return $err ?? 'no answer';
        }
        $clip = fn ($v, $n) => mb_substr(trim(strip_tags((string) $v)), 0, $n);
        $memo = [
            'summary' => $clip($d['summary'] ?? '', 1200),
            'market' => collect((array) ($d['market'] ?? []))->filter(fn ($m) => is_array($m) && preg_match('~^https://~i', (string) ($m['url'] ?? '')))
                ->take(8)->map(fn ($m) => ['point' => $clip($m['point'] ?? '', 300), 'url' => mb_substr((string) $m['url'], 0, 300)])->values()->all(),
            'strengths' => collect((array) ($d['strengths'] ?? []))->take(5)->map(fn ($x) => $clip($x, 240))->all(),
            'weaknesses' => collect((array) ($d['weaknesses'] ?? []))->take(5)->map(fn ($x) => $clip($x, 240))->all(),
            'priorities' => collect((array) ($d['priorities'] ?? []))->filter(fn ($p) => is_array($p) && filled($p['what'] ?? null))->take(6)
                ->map(fn ($p) => ['what' => $clip($p['what'], 300), 'who' => $clip($p['who'] ?? 'owner', 20), 'check' => $clip($p['check'] ?? '', 200)])->values()->all(),
            'at' => now('Asia/Kolkata')->format('d/m/Y'),
        ];
        if ($memo['summary'] === '' || ! $memo['priorities']) {
            return 'empty memo';
        }
        Setting::put('knowledge.strategy', json_encode($memo, JSON_UNESCAPED_UNICODE));
        EditorTask::create(['type' => 'strategy', 'status' => 'open', 'key' => 'strategy:'.now()->format('Y-m-d'),
            'title' => __('কৌশলবিদের পরিকল্পনা: ').$memo['at'], 'payload' => $memo]);

        return null;
    }

    public static function monthly(): ?string
    {
        return EditorTask::where('type', 'strategy')->where('created_at', '>=', now()->startOfMonth())->exists() ? null : self::run();
    }

    public static function current(): ?array
    {
        $raw = Setting::get('knowledge.strategy');
        $m = $raw ? json_decode($raw, true) : null;

        return is_array($m) && ! empty($m['priorities']) ? $m : null;
    }

    /** The owner's "এখনই নতুন পরিকল্পনা" button: done by the scheduler within a minute (web search takes time). */
    public static function request(): void
    {
        Setting::put('strategy.requested', now()->toIso8601String());
    }

    public static function runIfRequested(): ?string
    {
        if (! Setting::get('strategy.requested')) {
            return null;
        }
        Setting::where('key', 'strategy.requested')->delete();

        return self::run();
    }
}
