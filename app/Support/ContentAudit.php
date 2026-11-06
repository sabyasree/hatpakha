<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Redirect;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Phase 2 content audit (PLAN.md §5). Suggests a label per published post with plain reasons.
 * SUGGESTIONS ONLY: nothing changes until an admin applies it in /desk (CLAUDE.md §3).
 *
 * Severity order: remove > noindex > merge > improve > keep.
 */
final class ContentAudit
{
    public const LABELS = ['keep' => 'রাখুন', 'improve' => 'উন্নত করুন', 'merge' => 'একত্র করুন', 'noindex' => 'Google থেকে লুকান', 'remove' => 'সরান (410)'];

    private const RANK = ['keep' => 0, 'improve' => 1, 'merge' => 2, 'noindex' => 3, 'remove' => 4];

    private const SPAM = '/\b(casino|betting|sportsbook|payday loan|crypto ?currency|forex|viagra|cialis|escort|coupon code|promo code|discount code|essay writing service|buy followers)\b/i';

    /** Old WordPress categories that were not literature (docs/CATEGORY-MAP.md). */
    private const OFF_TOPIC_CATEGORIES = ['videos', 'amazing-facts', 'game', 'quiz', 'coupon', 'entertainment'];

    /**
     * @param  array<int,array<int,string>>  $oldCategories  legacy WP post id → old category slugs (optional)
     * @return array{label:string,reasons:array<int,string>,merge_into:?int}
     */
    public static function assess(Post $post, array $oldCategories = [], ?int $duplicateOf = null, ?int $deskAuthorId = null, ?bool $uploadsPresent = null): array
    {
        $label = 'keep';
        $reasons = [];
        $flag = function (string $l, string $why) use (&$label, &$reasons) {
            $reasons[] = $why;
            if (self::RANK[$l] > self::RANK[$label]) {
                $label = $l;
            }
        };

        $html = (string) $post->body_html;
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text));
        $hasMedia = str_contains($html, '<img') || preg_match('~youtube|youtu\.be~', $html);

        if (preg_match(self::SPAM, $post->title.' '.$text)) {
            $flag('remove', 'বিজ্ঞাপন/স্প্যামের শব্দ আছে');
        }
        if (preg_match('~Download or read online|bangla ?e-?books?|ebooks?classics|blogspot\.com[^"]*\.pdf~i', $html.' '.$post->title)) {
            $flag('remove', 'অন্যের প্রকাশিত বই ডাউনলোডের লিংক (স্বত্ব লঙ্ঘনের ঝুঁকি)');
        }
        $hasPdf = (bool) preg_match('~href="[^"]+\.pdf"~i', $html);
        if ($hasPdf && $words < 30) {
            $flag('improve', 'লেখা শুধু PDF ফাইলে: পাতায় লেখাটি টাইপ করে দিলে পাঠক ও Google পড়তে পারবে');
        }
        if ($words < 5 && ! $hasMedia && ! $hasPdf) {
            $flag('remove', 'লেখা প্রায় ফাঁকা');
        }

        $isDesk = $deskAuthorId && $post->relationLoaded('authors') && $post->authors->contains('id', $deskAuthorId);
        if ($isDesk && $post->language === 'en' && $post->published_at && $post->published_at->year >= 2024) {
            $flag('noindex', 'ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয়');
        }
        $old = $oldCategories[$post->legacy_wp_id] ?? [];
        if ($off = array_intersect($old, self::OFF_TOPIC_CATEGORIES)) {
            $flag('noindex', 'পুরনো বিভাগ: '.implode(', ', $off).' (সাহিত্যের বাইরে)');
        }
        $lines = preg_split('~<br\s*/?>|</p>~i', $html);
        $lines = array_filter(array_map(fn ($l) => trim(strip_tags($l)), $lines), fn ($l) => $l !== '');
        $looksLikeVerse = count($lines) >= 4 && array_sum(array_map('mb_strlen', $lines)) / count($lines) < 60;
        if ($post->format === 'prose' && ! $looksLikeVerse && $words > 0 && $words < 80 && ! $hasMedia) {
            $flag('noindex', "খুব ছোট গদ্য ({$words} শব্দ)");
        }
        if ($hasMedia && $words < 40 && preg_match('~youtube|youtu\.be~', $html)) {
            $flag('noindex', 'শুধু ভিডিও, লেখা প্রায় নেই');
        }

        if ($duplicateOf) {
            $flag('merge', "একই শিরোনাম ও লেখকের আরেকটি লেখা আছে (#{$duplicateOf})");
        }

        if ($uploadsPresent) {
            preg_match_all('~<img\b[^>]*\ssrc="([^"]+)"~i', $html, $m);
            $missing = 0;
            foreach ($m[1] as $src) {
                if (($rel = Images::relative(html_entity_decode($src))) && ! is_file(public_path($rel))) {
                    $missing++;
                }
            }
            if ($missing) {
                $flag('improve', "{$missing}টি ছবি পাওয়া যাচ্ছে না");
            }
        }
        if (mb_strlen($post->title) > 110) {
            $flag('improve', 'শিরোনাম খুব লম্বা');
        }

        return ['label' => $label, 'reasons' => $reasons, 'merge_into' => $label === 'merge' ? $duplicateOf : null];
    }

    public const TIERS = ['A' => 'A: সাম্প্রতিক (২০২২–)', 'B' => 'B: বাছাই-বাদ (ফাঁকা/নকল/পরীক্ষা)', 'C' => 'C: পুরনো, ভালো হতে পারে'];

    /**
     * Pending backlog tier with reasons (PLAN.md "Real backlog"). Suggestion only.
     *
     * @param  array<int,true>  $publishedKeys  normalised "title|author" of published posts
     * @return array{tier:string,reasons:array<int,string>}
     */
    public static function tier(Post $post, array $publishedKeys, ?int $batchAuthorId, array $pendingDuplicates): array
    {
        $reasons = [];
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $post->body_html))));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text));
        $authorId = $post->authors->first()?->id;
        $key = mb_strtolower(preg_replace('/[\s\p{P}]+/u', '', $post->title)).'|'.($authorId ?? 0);

        $cleanup = false;
        if ($words < 30) {
            $reasons[] = "খুব ছোট বা ফাঁকা ({$words} শব্দ)";
            $cleanup = true;
        }
        if (isset($publishedKeys[$key])) {
            $reasons[] = 'একই শিরোনামে এই লেখকের লেখা আগেই প্রকাশিত';
            $cleanup = true;
        }
        if (isset($pendingDuplicates[$post->id])) {
            $reasons[] = "একই লেখা আবার জমা পড়েছে (#{$pendingDuplicates[$post->id]})";
            $cleanup = true;
        }
        if (preg_match('/^\s*(test|testing|asdf|demo|untitled|পরীক্ষা)\b/iu', $post->title)) {
            $reasons[] = 'পরীক্ষামূলক লেখা বলে মনে হচ্ছে';
            $cleanup = true;
        }
        if ($batchAuthorId && $authorId === $batchAuthorId) {
            $reasons[] = 'একই লেখকের অনেক লেখা জমা আছে: একসঙ্গে বিচার করুন';
        }

        $year = ($post->submitted_at ?? $post->created_at)?->year ?? 0;
        $tier = $cleanup ? 'B' : ($year >= 2022 ? 'A' : 'C');
        if ($tier === 'A') {
            $reasons[] = 'সাম্প্রতিক জমা: এখনই পড়ে দেখার মতো';
        }

        return ['tier' => $tier, 'reasons' => $reasons];
    }

    /** Duplicate detection: same normalised title + same first author → later post points to earlier. */
    public static function duplicates(Collection $posts): array
    {
        $seen = [];
        $dup = [];
        $body = fn ($p) => mb_substr(preg_replace('/[\s\p{P}]+/u', '', html_entity_decode(strip_tags((string) $p->body_html))), 0, 600);
        foreach ($posts->sortBy('published_at') as $p) {
            $key = mb_strtolower(preg_replace('/[\s\p{P}]+/u', '', $p->title)).'|'.($p->authors->first()?->id ?? 0);
            if (str_starts_with($key, '|') || mb_strlen($key) < 4) {
                continue;
            }
            // Same title AND (nearly) the same text: a generic title like "Editorial" alone is not a duplicate.
            foreach ($seen[$key] ?? [] as [$id, $text]) {
                similar_text($text, $body($p), $pct);
                if ($text !== '' && $pct >= 90) {
                    $dup[$p->id] = $id;

                    continue 2;
                }
            }
            $seen[$key][] = [$p->id, $body($p)];
        }

        return $dup;
    }

    /** Applies a confirmed suggestion. Admin only (checked by the caller). Every change is logged and reversible. */
    public static function apply(Post $post, User $by): void
    {
        $label = $post->audit_label;
        match ($label) {
            'noindex' => $post->forceFill(['noindex' => true])->saveQuietly(),
            'remove' => self::retire($post, null),
            'merge' => self::retire($post, $post->audit_merge_into_id ? Post::find($post->audit_merge_into_id) : null),
            default => null,
        };
        $post->forceFill(['audit_decision' => 'applied', 'audit_decided_at' => now(), 'audit_decided_by' => $by->id])->saveQuietly();
        AuditLog::record('post.audit_applied', $post, ['label' => $label, 'by' => $by->id]);
    }

    public static function dismiss(Post $post, User $by): void
    {
        $post->forceFill(['audit_decision' => 'dismissed', 'audit_decided_at' => now(), 'audit_decided_by' => $by->id])->saveQuietly();
        AuditLog::record('post.audit_dismissed', $post, ['label' => $post->audit_label, 'by' => $by->id]);
    }

    /** Archive the post; its URL answers 301 to the merge target, or 410 Gone. Undo = set status back + delete redirect. */
    private static function retire(Post $post, ?Post $target): void
    {
        $path = '/'.$post->slug.'/';
        $post->forceFill(['status' => 'archived'])->saveQuietly();
        Redirect::updateOrCreate(['from_path' => $path], $target
            ? ['to_path' => '/'.$target->slug.'/', 'status_code' => 301]
            : ['to_path' => null, 'status_code' => 410]);
    }
}
