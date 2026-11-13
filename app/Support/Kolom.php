<?php

namespace App\Support;

use App\Ai\Claude;
use App\Models\AuditLog;
use App\Models\EditorTask;
use App\Models\ReaderEntry;
use App\Models\ReaderPrompt;
use App\Models\SocialPost;
use Illuminate\Support\Str;

/**
 * D175 (owner: "build this category, promote it, use the agents to do all the stuff"): পাঠকের কলম.
 * Monday: the week's theme opens and a social post inviting readers is made ready (it goes out by itself when the
 * owner's automatic Facebook switch is on). Through the week the AI screens what comes in. Saturday evening it
 * proposes the best ten. Readers' words reach the site only when the owner presses "ফল প্রকাশ করুন" (D42).
 */
final class Kolom
{
    public const LIMITS = ['six' => 80, 'letter' => 600, 'memory' => 240];

    public const PICKS = 10;

    public const PER_PERSON = 3;

    public const AUTO = 'kolom.auto';

    /**
     * D176 (owner: "get rid of the admin click, I want it mostly automated"): on unless the owner switches it off in
     * /desk → পাঠকের কলম. On: the AI's picks are published on Sunday morning and the two posts go to the Facebook Page
     * by themselves. Only entries the AI has read and passed are ever published without a person.
     */
    public static function auto(): bool
    {
        return \App\Models\Setting::get(self::AUTO) !== '0';
    }

    /** With the automatic switch on, a পাঠকের কলম post goes to the Facebook Page at once (live site only). */
    private static function share(SocialPost $s): void
    {
        if (self::auto() && FacebookPage::ready()) {
            rescue(fn () => FacebookPage::publish($s->fresh()), null, false);
        }
    }

    /** Sunday morning: publish the proposed picks by themselves, if every one of them was read and passed by the AI. */
    public static function autoPublish(): bool
    {
        $p = ReaderPrompt::where('status', 'closed')->latest('id')->first();
        if (! self::auto() || ! $p) {
            return false;
        }
        $picked = $p->picked()->get();
        if ($picked->isEmpty() || $picked->contains(fn (ReaderEntry $e) => $e->ai_score === null)) {
            return false;   // nothing to show, or unread entries: the owner's task stays open
        }

        return self::publish($p);
    }

    /** @return list<array{0: string, 1: string, 2: string}> */
    public static function themes(): array
    {
        return require database_path('content/reader-prompts.php');
    }

    /** Monday morning: last week's theme stops taking entries, the next one opens, and readers are invited. */
    public static function openWeek(): ?ReaderPrompt
    {
        ReaderPrompt::where('status', 'open')->get()->each(fn (ReaderPrompt $p) => $p->update(['status' => 'closed', 'closes_at' => $p->closes_at ?? now()]));
        $themes = self::themes();
        $n = ReaderPrompt::count();
        [$kind, $title, $ask] = $themes[$n % count($themes)];
        $prompt = ReaderPrompt::create([
            'slug' => 'w'.($n + 1).'-'.Str::lower(Str::random(4)), 'kind' => $kind, 'title' => $title, 'ask' => $ask,
            'max_chars' => self::LIMITS[$kind], 'status' => 'open', 'opens_at' => now(), 'closes_at' => now()->addDays(6)->setTimezone('Asia/Kolkata')->setTime(18, 0)->utc(),
        ]);
        self::share(self::announce($prompt));
        AuditLog::record('kolom.opened', null, ['prompt' => $prompt->id]);

        return $prompt;
    }

    /** The invitation for Facebook, WhatsApp and Instagram (our own fixed wording: no AI, nothing invented). */
    public static function announce(ReaderPrompt $p): SocialPost
    {
        $kind = ReaderPrompt::KINDS[$p->kind];
        $how = ['six' => 'মাত্র ছয়টি শব্দ। এক মিনিটের কাজ।', 'letter' => 'কয়েক লাইনের একটা চিঠি। নাম না দিলেও চলবে।', 'memory' => 'এক-দু লাইনই যথেষ্ট।'][$p->kind];
        $fb = "এ সপ্তাহের পাঠকের কলম: “{$p->title}”\n\n{$p->ask}\n\n{$how} লগ-ইন লাগে না। সেরা দশটি লেখা রবিবার হাতপাখায় ছাপা হবে, লেখকের নামসহ।\n\nশনিবার সন্ধ্যা ৬টা পর্যন্ত পাঠান।";

        return SocialPost::create(['kind' => 'kolom', 'title' => 'পাঠকের কলম: '.$p->title, 'texts' => [
            'facebook' => $fb."\n\n".$p->url(),
            'whatsapp' => "এ সপ্তাহের পাঠকের কলম: “{$p->title}”। {$p->ask} {$how} সেরা দশটি রবিবার ছাপা হবে। ".$p->url(),
            'instagram' => "এ সপ্তাহের পাঠকের কলম: “{$p->title}”\n\n{$p->ask}\n{$how} সেরা দশটি রবিবার হাতপাখায়।\n\nলিংক বায়োতে: hatpakha.com/kolom\n\n#হাতপাখা #পাঠকেরকলম #বাংলা #বাংলাসাহিত্য #".str_replace(' ', '', $kind),
            'quote' => $p->ask, 'link' => $p->url(), 'kolom' => $p->id,
        ]]);
    }

    /**
     * Hourly: the AI reads what came in. Abuse, hate, private details of a named person, adverts or copied famous
     * lines are flagged with a reason; the rest get a 1-10 for how much the line moves a reader. Advice only.
     */
    public static function screen(int $max = 40): int
    {
        $new = ReaderEntry::with('prompt')->where('status', 'new')->oldest('id')->limit($max)->get();
        if ($new->isEmpty()) {
            return 0;
        }
        [$d] = Claude::json('kolom', 'You help the editors of Hatpakha, a Bengali literary web magazine, read short pieces sent by readers for a weekly theme. '
            .'For each entry say ok=false ONLY if it is abusive, hateful, sexual, an advert or link, reveals private details of a named real person, is not in Bengali or English, or is clearly a famous existing line; give the reason in one short Bengali sentence. '
            .'Otherwise ok=true and a score 1-10 for how fresh, concrete and moving it is as writing for the theme (most honest entries are 4-7; 9-10 is rare), with one short Bengali sentence. Never rewrite an entry.',
            $new->map(fn (ReaderEntry $e) => '<entry id="'.$e->id.'" theme="'.e($e->prompt->title).'">'.e($e->body).'</entry>')->implode("\n"),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['entries'], 'properties' => ['entries' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'ok', 'score', 'note'],
                'properties' => ['id' => ['type' => 'integer'], 'ok' => ['type' => 'boolean'], 'score' => ['type' => 'integer'], 'note' => ['type' => 'string']]]]]],
            config('ai.anthropic.tags_model'), 4000);
        $done = 0;
        foreach ((array) ($d['entries'] ?? []) as $r) {
            if ($e = $new->firstWhere('id', (int) ($r['id'] ?? 0))) {
                $e->update(['status' => ($r['ok'] ?? false) ? 'ok' : 'flagged', 'ai_score' => max(1, min(10, (int) ($r['score'] ?? 5))), 'ai_note' => mb_substr((string) ($r['note'] ?? ''), 0, 300)]);
                $done++;
            }
        }

        return $done;
    }

    /** Saturday evening: entries close and the best ten are proposed. The owner looks, changes if needed, and publishes. */
    public static function pickWeek(): ?ReaderPrompt
    {
        $p = ReaderPrompt::current();
        if (! $p) {
            return null;
        }
        self::screen(200);
        $p->update(['status' => 'closed', 'closes_at' => now()]);
        // AI-scored first; without AI the first to arrive, so the owner still gets a proposal to edit.
        $pool = $p->entries()->where('status', 'ok')->exists() ? ['ok'] : ['ok', 'new'];   // unread ones only when the AI read nothing
        $best = $p->entries()->whereIn('status', $pool)->orderByDesc('ai_score')->orderBy('id')->get()
            ->unique(fn (ReaderEntry $e) => $e->sender_hash ?: 'e'.$e->id)->take(self::PICKS);
        ReaderEntry::whereIn('id', $best->pluck('id'))->update(['status' => 'picked']);
        if ($p->entries()->exists()) {
            EditorTask::propose('kolom', __('পাঠকের কলম: এ সপ্তাহের বাছাই দেখে প্রকাশ করুন').' ('.$p->title.')', ['prompt' => $p->id], $p);
        }
        AuditLog::record('kolom.picked', null, ['prompt' => $p->id, 'picked' => $best->count()]);

        return $p;
    }

    /** The owner's click: the picked entries go public, and a post for social is made ready. */
    public static function publish(ReaderPrompt $p, ?int $by = null): bool
    {
        $picked = $p->picked()->get();
        if ($picked->isEmpty() || $p->status === 'published') {
            return false;
        }
        $p->update(['status' => 'published', 'published_at' => now()]);
        $lines = $picked->take(3)->map(fn (ReaderEntry $e) => '“'.Str::limit(preg_replace('/\s+/u', ' ', $e->body), 140).'” — '.$e->shownName())->implode("\n\n");
        $social = SocialPost::create(['kind' => 'kolom', 'title' => 'পাঠকের কলম, বাছাই: '.$p->title, 'texts' => [
            'facebook' => "পাঠকের কলম: “{$p->title}”\nএ সপ্তাহে পাঠকেরা যা লিখলেন, তার থেকে বাছাই:\n\n{$lines}\n\nসব কটি পড়ুন, আর নতুন সপ্তাহের বিষয়ে আপনিও লিখুন:\n\n".$p->url(),
            'whatsapp' => "পাঠকের কলম “{$p->title}”: পাঠকদের সেরা লেখাগুলো ছাপা হল। পড়ুন, আর এ সপ্তাহে আপনিও লিখুন: ".$p->url(),
            'instagram' => "পাঠকের কলম: “{$p->title}”\n\n{$lines}\n\nসব কটি: hatpakha.com/kolom (লিংক বায়োতে)\n\n#হাতপাখা #পাঠকেরকলম #বাংলা #বাংলাসাহিত্য",
            'quote' => Str::limit(preg_replace('/\s+/u', ' ', (string) $picked->first()->body), 180), 'link' => $p->url(), 'kolom' => $p->id,
        ]]);
        self::share($social);
        EditorTask::open('kolom')->where('subject_id', $p->id)->get()->each->close('done');
        AuditLog::record('kolom.published', null, ['prompt' => $p->id, 'by' => $by, 'entries' => $picked->count()]);

        return true;
    }

    /** Who sent it, without keeping an address: limits repeats per theme. */
    public static function senderHash(\Illuminate\Http\Request $r): string
    {
        return hash_hmac('sha256', $r->user() ? 'u'.$r->user()->id : 'ip'.$r->ip().'|'.mb_substr((string) $r->userAgent(), 0, 120), (string) config('app.key'));
    }
}
