<?php

namespace App\Support;

use App\Models\Post;
use App\Models\ReaderPrompt;
use App\Models\SocialPost;
use Illuminate\Support\Carbon;

/**
 * The marketing head (D182, docs/MARKETING.md): plans each day, gives every post a format and a time, and sends it
 * out by itself (owner, 2026-11-13: "I want fully automatic"). One switch stops all of it: the automatic-posting
 * switch on the marketing page.
 *
 * A day on the page:
 *   09:05  the day's ধাঁধা as a card ("answer in the comments"; Wednesday: a word from the word game). Facebook.
 *          Next morning we comment the answer under it, and the new card names yesterday's answer.
 *   13:30  a reel: yesterday's riddle with a five-second countdown and the answer, or a line from a new piece
 *          appearing phrase by phrase. Facebook and Instagram.
 *   19:35  the piece of the day as a line card, or a poem as slides (the marketing expert's text). Both.
 *   পাঠকের কলম posts (Monday's theme, Sunday's picks) go out late morning as cards.
 *
 * Fixed wording here uses only real data (the riddle, the word's clue, the piece's own line): nothing is invented.
 * A post waits up to WAIT_HOURS for its picture; after that it goes as plain text with its link (a reel is dropped).
 */
final class MarketingPlan
{
    public const FB_CAP = 4;      // a day, whatever is planned

    public const IG_CAP = 3;

    public const WAIT_HOURS = 3;

    public const WINDOW_HOURS = 18;

    private const TZ = 'Asia/Kolkata';

    /** The evening before (and again in the morning, harmlessly): the fixed posts of one day. Returns how many were made. */
    public static function plan(?Carbon $day = null): int
    {
        $day = ($day ?? now(self::TZ)->addDay())->copy()->timezone(self::TZ)->startOfDay();
        $date = $day->toDateString();
        $at = fn (int $h, int $m) => $day->copy()->setTime($h, $m)->utc();
        $before = $day->copy()->subDay();
        $old = WordGame::number($before) >= 1 ? WordGame::riddle($before)['words'][0] : null;   // yesterday's riddle, once the games have begun
        $made = 0;

        // Morning: the riddle (Wednesday: a word).
        if ($day->dayOfWeekIso === 3 && ($w = collect(WordGame::day($day)['words'])->last())) {
            $made += self::quiz($date.':word', 'word', 'শব্দ ধরুন', 'আজকের শব্দ', $w['clue'], $w['answer'], Url::to('/khela'), $at(9, 5), $day, 'কোন শব্দ? কমেন্টে লিখুন। ঠিক উত্তর কাল সকালে।', null);
        } else {
            $r = WordGame::riddle($day)['words'][0];
            $made += self::quiz($date.':riddle', 'riddle', 'আজকের ধাঁধা', 'আজকের ধাঁধা', $r['clue'], $r['answer'], Url::to('/khela/dhadha'), $at(9, 5), $day, 'উত্তর কমেন্টে লিখুন। ঠিক উত্তর কাল সকালে।', $old['answer'] ?? null);
        }

        // Midday: a reel. A line from a new piece on Tuesday, Thursday and Saturday; else yesterday's riddle with its answer.
        $line = in_array($day->dayOfWeekIso, [2, 4, 6], true) || ! $old ? self::lineReel($date.':reel', $at(13, 30)) : 0;
        if ($line) {
            $made += $line;
        } elseif ($old) {
            $url = Url::to('/khela/dhadha');
            $made += self::make($date.':reel', [
                'kind' => 'riddle', 'format' => 'reel', 'title' => 'রিল: ধাঁধা ('.Bn::date($day).')', 'slot_at' => $at(13, 30),
                'design' => ['reel' => ['t' => 'riddle', 'chip' => 'ধাঁধা', 'clue' => $old['clue'], 'letters' => WordGame::letters($old['answer'])]],
                'texts' => [
                    'facebook' => "পাঁচ সেকেন্ডে পারবেন?\n\nগতকালের ধাঁধা, উত্তর রিলের শেষে। আজকের নতুন ধাঁধা এখানে:\n\n".$url,
                    'instagram' => "পাঁচ সেকেন্ডে পারবেন? উত্তর রিলের শেষে।\n\nরোজ একটি নতুন ধাঁধা: hatpakha.com/khela (লিংক বায়োতে)\n\n#হাতপাখা #ধাঁধা #বাংলাধাঁধা #বাংলা #বাংলাসাহিত্য",
                    'whatsapp' => '', 'link' => $url,
                ],
            ]);
        }

        return $made;
    }

    /** A question card: the clue, empty boxes for the letters, the answer as our comment the next morning. */
    private static function quiz(string $key, string $kind, string $chip, string $name, string $clue, string $answer, string $url, Carbon $slot, Carbon $day, string $note, ?string $yesterday): int
    {
        $then = $yesterday ? "\n\nগতকালের উত্তর: {$yesterday}" : '';

        return self::make($key, [
            'kind' => $kind, 'format' => 'card', 'title' => $name.' ('.Bn::date($day).')', 'slot_at' => $slot,
            'design' => ['slides' => [['t' => $kind, 'chip' => $chip, 'clue' => $clue, 'n' => count(WordGame::letters($answer)), 'note' => $note]]],
            'comment' => "উত্তর: {$answer}। ঠিক বলেছিলেন?\nআজকের নতুন খেলা: ".$url,
            'comment_at' => $day->copy()->addDay()->setTime(8, 30)->utc(),
            'texts' => [
                'facebook' => "{$name}\n\n{$clue}\n\n{$note}{$then}\n\nঅক্ষর সাজিয়ে খেলুন:\n\n".$url,
                'whatsapp' => "{$name}: {$clue} পারবেন? খেলুন: ".$url,
                'instagram' => '', 'link' => $url,
            ],
        ]);
    }

    /** A reel from the newest piece post that has a line and has not been a reel yet. */
    private static function lineReel(string $key, Carbon $slot): int
    {
        if (SocialPost::where('plan_key', $key)->exists()) {
            return 0;
        }
        $from = SocialPost::with('subject')->whereIn('kind', ['piece', 'archive'])->where('created_at', '>=', now()->subDays(10))->latest()->limit(30)->get()
            ->first(fn (SocialPost $s) => filled($s->texts['quote'] ?? null) && empty($s->texts['reeled']) && $s->subject instanceof Post && $s->subject->isLive());
        if (! $from) {
            return 0;
        }
        $post = $from->subject;
        $card = Studio::line($post, (string) $from->texts['quote']);
        $by = $card['by'] !== '' ? "— {$card['by']}, “{$post->title}”" : "“{$post->title}”";
        $from->forceFill(['texts' => ['reeled' => 1] + (array) $from->texts])->save();

        return self::make($key, [
            'kind' => 'piece', 'format' => 'reel', 'title' => 'রিল: '.$post->title, 'slot_at' => $slot,
            'subject_type' => $post->getMorphClass(), 'subject_id' => $post->getKey(),
            'design' => ['reel' => ['t' => 'line'] + $card],
            'texts' => [
                'facebook' => "“{$card['quote']}”\n\n{$by}\n\nপুরো লেখা:\n\n".$post->url(),
                'instagram' => "“{$card['quote']}”\n{$by}\n\nপুরো লেখা: hatpakha.com (লিংক বায়োতে)\n\n#হাতপাখা #বাংলাসাহিত্য #বাংলা #বাংলাকবিতা #বাংলাগল্প",
                'whatsapp' => '',
            ],
        ]);
    }

    private static function make(string $key, array $attrs): int
    {
        if (SocialPost::where('plan_key', $key)->exists()) {
            return 0;
        }
        (new SocialPost)->forceFill(['plan_key' => $key] + $attrs)->save();   // forceFill: these columns are newer than the model's cached list in some processes

        return 1;
    }

    /**
     * A post made elsewhere (the marketing expert's piece post, পাঠকের কলম) gets its format, its card and its time:
     * a poem with stanzas becomes slides, a piece with a line becomes a line card, পাঠকের কলম a text card.
     */
    public static function dress(SocialPost $s): SocialPost
    {
        $texts = (array) $s->texts;
        $subject = $s->subject;
        $set = [];
        if (in_array($s->kind, ['piece', 'archive'], true) && $subject instanceof Post) {
            $stanzas = array_values(array_filter((array) ($texts['slides'] ?? []), fn ($x) => is_string($x) && trim($x) !== ''));
            if (count($stanzas) >= 2) {
                $set = ['format' => 'carousel', 'design' => ['slides' => Studio::poem($subject, $stanzas)]];
            } elseif (filled($texts['quote'] ?? null)) {
                $set = ['format' => 'card', 'design' => ['slides' => [Studio::line($subject, (string) $texts['quote'])]]];
            }
        } elseif ($s->kind === 'kolom') {
            $set = ['format' => 'card', 'design' => ['slides' => [['t' => 'text', 'chip' => 'পাঠকের কলম', 'title' => trim(\Illuminate\Support\Str::after((string) $s->title, ':')), 'text' => (string) ($texts['quote'] ?? ''), 'cta' => 'hatpakha.com/kolom']]]];
            $set['slot_at'] = max(now()->addMinutes(5), now(self::TZ)->setTime(11, 30)->utc());
        }
        if (! isset($set['slot_at']) && $s->kind !== 'weekly' && ! ($subject instanceof Post && ! $subject->isLive())) {
            $set['slot_at'] = self::evening();
        }
        if ($set) {
            $s->forceFill($set)->save();
        }

        return $s;
    }

    /** The next free evening time: 19:35, then 21:15, then the next day. */
    public static function evening(): Carbon
    {
        $day = now(self::TZ)->startOfDay();
        for ($i = 0; $i < 14; $i++) {
            foreach ([[19, 35], [21, 15]] as [$h, $m]) {
                $slot = $day->copy()->addDays($i)->setTime($h, $m)->utc();
                if ($slot->gt(now()->addMinutes(20)) && ! SocialPost::where('slot_at', $slot)->exists()) {
                    return $slot;
                }
            }
        }

        return now()->addDay();
    }

    /** Every five minutes: what is due goes out. Returns how many posts went somewhere. */
    public static function run(): int
    {
        rescue(fn () => InstagramPage::finishReels(), null, false);
        rescue(fn () => self::comments(), null, false);
        if (! FacebookPage::auto() || ! (FacebookPage::ready() || InstagramPage::ready())) {
            return 0;
        }
        // Posts nobody gave a time (older ones, or a piece that has only now gone live) join the evening queue.
        foreach (SocialPost::with('subject')->where('status', 'ready')->whereNull('slot_at')->whereNull('fb_scheduled_at')->whereNull('fb_post_id')->where('kind', '!=', 'weekly')
            ->where('created_at', '>=', now()->subDays(14))->oldest()->limit(5)->get() as $loose) {
            if (FacebookPage::postable($loose) === null) {
                $loose->forceFill(['slot_at' => self::evening()])->save();
            }
        }
        $sent = 0;
        foreach (SocialPost::with('subject')->where('status', 'ready')->whereNotNull('slot_at')->where('slot_at', '<=', now())->orderBy('slot_at')->limit(12)->get() as $s) {
            $drawn = ! in_array($s->format, ['card', 'carousel', 'reel'], true) || $s->media;
            if ($s->slot_at->lt(now()->subHours(self::WINDOW_HOURS)) || (! $drawn && $s->format === 'reel' && $s->slot_at->lt(now()->subHours(self::WAIT_HOURS)))) {
                $went = $s->fb_post_id || $s->ig_media_id || $s->ig_container;   // its time has passed
                $s->forceFill(['status' => $went ? 'posted' : 'dismissed', 'posted_at' => $went ? now() : null])->save();

                continue;
            }
            if ((! $drawn && $s->slot_at->gt(now()->subHours(self::WAIT_HOURS))) || $sent >= 2) {
                continue;   // its picture is still being drawn, or enough for this run
            }
            $did = false;
            if (FacebookPage::ready() && ! $s->fb_post_id && ! $s->fb_error && filled($s->texts['facebook'] ?? null) && self::today('fb_post_id') < self::FB_CAP) {
                $did = (bool) rescue(fn () => FacebookPage::send($s)[0], false, false);
            }
            $s = $s->fresh();
            if ($s && InstagramPage::ready() && ! $s->ig_media_id && ! $s->ig_container && ! $s->ig_error && filled($s->texts['instagram'] ?? null) && $drawn && self::today('ig_media_id') < self::IG_CAP) {
                $did = (bool) rescue(fn () => InstagramPage::send($s)[0], false, false) || $did;
            }
            $sent += $did ? 1 : 0;
        }

        return $sent;
    }

    /** How many planned posts have gone to a network today (Kolkata time). */
    private static function today(string $column): int
    {
        $from = now(self::TZ)->startOfDay()->utc();

        return SocialPost::whereBetween('slot_at', [$from, $from->copy()->addDay()])->where(fn ($q) => $column === 'ig_media_id' ? $q->whereNotNull('ig_media_id')->orWhereNotNull('ig_container') : $q->whereNotNull($column))->count();
    }

    /** The answers we promised "tomorrow morning", as our comment under yesterday's card. */
    public static function comments(): int
    {
        $n = 0;
        foreach (SocialPost::whereNotNull('comment_at')->where('comment_at', '<=', now())->whereNotNull('fb_post_id')->whereNull('fb_comment_id')->limit(3)->get() as $s) {
            $n += FacebookPage::comment($s) ? 1 : 0;
        }

        return $n;
    }

    /**
     * Today's message for the WhatsApp Channel (WhatsApp gives no way to post to a Channel by program, so the owner
     * pastes this one message; shown on the marketing page). Real items only.
     */
    public static function whatsapp(): string
    {
        $parts = ['আজ হাতপাখায়'];
        if ($p = Post::published()->with('authors')->where('published_at', '>=', now()->subDays(2))->latest('published_at')->first()) {
            $parts[] = 'নতুন লেখা: '.$p->title.(($by = $p->authors->first()?->publicName()) ? ' — '.$by : '')."\n".$p->url();
        }
        if (WordGame::number() >= 1) {
            $parts[] = 'আজকের ধাঁধা: '.WordGame::riddle()['words'][0]['clue']."\nউত্তর মিলিয়ে নিন: ".Url::to('/khela/dhadha');
        }
        if ($k = ReaderPrompt::where('status', 'open')->latest('id')->first()) {
            $parts[] = 'এ সপ্তাহের পাঠকের কলম: “'.$k->title."”। আপনিও লিখুন:\n".$k->url();
        }

        return implode("\n\n", $parts);
    }
}
