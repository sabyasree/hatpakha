<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\Author;
use App\Models\EditorTask;
use App\Models\Post;
use App\Models\Section;
use App\Models\Serial;
use App\Support\CoverImage;
use Illuminate\Support\Str;

/**
 * D108 (owner): three AI serial writers, one genre each, draft a weekly ধারাবাহিক. They plan the whole story first
 * (setting, characters, 10-12 episodes), then write one episode at a time, always one week ahead, each ending so
 * that the reader wants the next. Every episode is a DRAFT under "হাতপাখা ডেস্ক" (never a made-up author, D95) with
 * a proposed picture; the owner edits, approves the picture and publishes. Nothing goes out by itself (D42).
 */
final class SerialWriter
{
    public const WRITERS = [
        'serial_mystery' => ['রহস্য', 'A mystery-thriller (রহস্য-রোমাঞ্চ) set in today\'s Kolkata or a small Bengal town. An original detective or an ordinary person drawn in by chance; '
            .'clues are fair to the reader, suspicion moves between characters, and each episode uncovers one real piece of the puzzle. '
            .'Do not borrow Feluda, Byomkesh, Kiriti or any existing detective, character or plot.'],
        'serial_ghost' => ['অলৌকিক', 'An uncanny, supernatural story (অলৌকিক / ভূতের গল্প) in the Bengali tradition: an old house, a railway quarter, a river village, a hill bungalow. '
            .'Dread that builds slowly through small wrong details; atmosphere and the unexplained over gore; never gratuitous violence. Ordinary people, believable lives.'],
        'serial_family' => ['সম্পর্ক', 'A family and relationship story (সম্পর্কের গল্প) across generations: for example a North Kolkata joint-family house about to be sold, '
            .'a box of old letters, two siblings who have not spoken in years. Each episode reveals one secret or turns one relationship; warmth, humour and ache together.'],
    ];

    /** How a serial episode is written: literature first, and an ending that brings readers back. */
    private const CRAFT = 'You write for Hatpakha (হাতপাখা), a Bengali literary web magazine, a weekly serial (ধারাবাহিক) that readers follow episode by episode. '
        .Prompt::LITERARY.' '
        .'This is fiction: invent ordinary Bengali characters freely, but never a real, living person, a real crime or a real family. '
        .'Each episode: 1200-1800 words of scenes, not summary; dialogue that sounds like people talk in Kolkata or the district (চলিত, a little dialect where it fits); '
        .'one clear movement in the story; a recurring detail or motif readers will recognise. '
        // D172 (owner: "make the serials more engaging and curious, so readers come back")
        .'Readers must want the next episode. So: (1) Open in the middle of something happening, never with weather, waking up or a recap; a reader who missed last week is caught up by one natural line inside the first scene. '
        .'(2) Every episode ANSWERS one question the reader was carrying and OPENS a bigger one; never answer everything, never withhold everything. '
        .'(3) Somewhere past the middle, something turns: a person is not who we thought, an object is in the wrong place, a safe assumption breaks. '
        .'(4) Plant small things early (a remark, an object, a habit) and pay them off episodes later; readers love to say "I noticed that". '
        .'(5) Give each main character a want and a secret, and let the reader know a little more than one character does, so they worry for them. '
        .'(6) Raise the cost as the serial goes on: what the character can lose must grow. '
        .'For a serial, the ending is different from an essay: stop one beat EARLY, on a hook that comes out of the story itself (a discovery, a line of dialogue that changes what we know, a door that should be locked and is not, a decision made but not yet acted on). '
        .'The hook is a concrete image or line, never an explanation of why it matters, and never a melodramatic question to the reader like "কী হবে এবার?". '
        .'Vary the kind of hook from episode to episode. The final episode resolves the story honestly, pays off what was planted, and leaves one quiet image. '
        .'Keep every name, place, date and fact consistent with the story so far.';

    /**
     * Plan a new serial, then draft its first episode. Two separate requests: a plan plus a full Bengali episode in one
     * answer is too long and gets cut off (owner's screenshot, 2026-11-08: "invalid JSON").
     */
    public static function start(string $writer, string $idea = ''): array
    {
        if (! isset(self::WRITERS[$writer])) {
            return [null, 'লেখক পাওয়া যায়নি।'];
        }
        [$genre, $brief] = self::WRITERS[$writer];
        [$d, $err] = Claude::json('serial', self::CRAFT.FeedbackLessons::forPrompt('dharabahik').' '.$brief.' '
            .'Plan the whole serial (do not write any episode yet): a title that makes people curious (short, Bengali, not a label), a logline of one or two lines that sells the story without giving it away, '
            .'the setting, 3-6 characters (each: who they are, what they want and what they hide), and 10-12 episodes, each a title and one or two lines of what happens, which question it answers, which bigger question it opens, and the image or line it ends on. Put the strongest turn near the middle of the serial and a false ending two episodes before the last. '
            .'Also a cover_prompt in English: one striking scene for the serial\'s poster.',
            '<idea>'.e(mb_substr(trim($idea), 0, 600) ?: 'Choose a fresh, original idea.').'</idea>',
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'logline', 'setting', 'characters', 'episodes', 'cover_prompt'], 'properties' => [
                'title' => ['type' => 'string'], 'logline' => ['type' => 'string'], 'setting' => ['type' => 'string'],
                'characters' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['name', 'role'], 'properties' => [
                    'name' => ['type' => 'string'], 'role' => ['type' => 'string']]]],
                'episodes' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'beat'], 'properties' => [
                    'title' => ['type' => 'string'], 'beat' => ['type' => 'string']]]],
                'cover_prompt' => ['type' => 'string'],
            ]], (string) config('ai.anthropic.writer_model'), 32000);   // thinking counts toward the limit (always on)
        if (! is_array($d) || empty($d['episodes'])) {
            return [null, $err ?: 'পরিকল্পনা তৈরি হয়নি।'];
        }
        $serial = Serial::create([
            'title' => mb_substr(trim((string) $d['title']), 0, 200), 'logline' => mb_substr(trim((string) $d['logline']), 0, 400),
            'genre' => $genre, 'writer' => $writer, 'status' => 'proposed', 'day' => 6,
            'bible' => ['setting' => (string) $d['setting'], 'characters' => array_values((array) $d['characters']), 'episodes' => array_values((array) $d['episodes'])],
        ]);
        self::cover($serial, (string) $d['cover_prompt']);
        EditorTask::propose('serial', __('নতুন ধারাবাহিকের প্রস্তাব: ').$serial->title, [], $serial);
        AuditLog::record('serial.proposed', null, ['serial' => $serial->id, 'writer' => $writer]);
        // The plan is kept even if episode 1 fails; "পরের পর্ব লেখান" on the serial tries again.
        self::next($serial);

        return [$serial, null];
    }

    /** The next episode, from the plan and everything written so far. */
    public static function next(Serial $serial): array
    {
        if (! in_array($serial->status, ['proposed', 'active'], true)) {
            return [null, 'ধারাবাহিকটি এখন বন্ধ।'];
        }
        $n = $serial->nextEpisodeNo();
        $plan = (array) ($serial->bible['episodes'] ?? []);
        if ($n > max(count($plan), 1) + 2) {
            return [null, 'পরিকল্পনার সব পর্ব লেখা হয়ে গেছে।'];
        }
        $last = $n >= count($plan);
        $before = $serial->episodes()->get();
        $story = $before->map(fn (Post $p) => 'পর্ব '.$p->episode_no.' "'.self::ownTitle((string) $p->title, $serial->title).'": '.($p->episode_summary ?: mb_substr(strip_tags((string) $p->body_html), 0, 400)))->implode("\n");
        $tail = mb_substr(trim(html_entity_decode(strip_tags(str_replace('</p>', "\n", (string) $before->last()?->body_html)))), -2500);
        [$d, $err] = Claude::json('serial', self::CRAFT.FeedbackLessons::forPrompt('dharabahik', $serial->id).' '.(self::WRITERS[$serial->writer][1] ?? ''),
            '<serial><title>'.e($serial->title).'</title><setting>'.e((string) ($serial->bible['setting'] ?? '')).'</setting>'
            ."\n<characters>".e(collect((array) ($serial->bible['characters'] ?? []))->map(fn ($c) => ($c['name'] ?? '').': '.($c['role'] ?? ''))->implode("\n")).'</characters>'
            ."\n<plan>".e(collect($plan)->map(fn ($e, $i) => 'পর্ব '.($i + 1).' '.($e['title'] ?? '').': '.($e['beat'] ?? ''))->implode("\n")).'</plan>'
            ."\n<so_far>".e($story).'</so_far>'
            ."\n<last_episode_ending>".e($tail).'</last_episode_ending></serial>'
            ."\nWrite episode {$n}".($n === 1 ? ', the opening: draw the reader in from the first line, introduce the world through a scene, and end on a hook.' : ($last ? ', the FINAL episode: resolve the story.' : ', following the plan (you may improve it if the story asks for it), and end on a hook.'))
            .' The title is only this episode\'s own short title (2-5 words): never the serial\'s name, never "পর্ব" or a number. Also give its private summary, an image_prompt, and a teaser: ONE short Bengali line (under 20 words) that will be printed under this episode as "পরের পর্বে" to make readers come back for the NEXT episode: it hints at what is coming (from the plan) through a concrete detail or a question in the story\'s own world, gives nothing away, and is not a question to the reader. For the final episode the teaser is an empty string.',
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['episode'], 'properties' => ['episode' => self::episodeSchema()]],
            (string) config('ai.anthropic.writer_model'), 64000);   // thinking + up to 1800 Bengali words; streamed
        if (! is_array($d) || empty($d['episode']['paragraphs'])) {
            return [null, $err ?: 'পর্ব তৈরি হয়নি।'];
        }

        return [self::saveEpisode($serial, $n, (array) $d['episode']), null];
    }

    /** Daily: every running serial keeps exactly one unpublished episode ready (one week ahead). */
    public static function keepAhead(): int
    {
        $done = 0;
        foreach (Serial::where('status', 'active')->get() as $s) {
            if (! $s->episodes()->whereIn('status', ['draft', 'pending', 'scheduled'])->exists()) {
                [$p] = self::next($s);
                $done += $p ? 1 : 0;
            }
        }

        return $done;
    }

    /**
     * The episode's own short title, without any serial name or "(পর্ব N):" the writer copied from earlier titles
     * (owner's screenshot, 2026-11-09: "লাল কাচ (পর্ব ২): লাল কাচ (পর্ব ২): খাতা").
     */
    public static function ownTitle(string $title, string $serialTitle = ''): string
    {
        $t = trim($title);
        do {
            $before = $t;
            $t = trim((string) preg_replace('/^.*?\(\s*পর্ব\s*[০-৯0-9]+\s*\)\s*[:：।-]?\s*/u', '', $t));
            $t = trim((string) preg_replace('/^পর্ব\s*[০-৯0-9]+\s*[:：।-]\s*/u', '', $t));
            if ($serialTitle !== '') {
                $t = trim((string) preg_replace('/^'.preg_quote($serialTitle, '/').'\s*[:：,–—-]\s*/u', '', $t));
            }
        } while ($t !== $before && $t !== '');

        return $t !== '' ? $t : trim($title);
    }

    public static function fullTitle(Serial $serial, int $n, string $own): string
    {
        return mb_substr($serial->title.' (পর্ব '.\App\Support\Bn::num((string) $n).'): '.self::ownTitle($own, $serial->title), 0, 200);
    }

    private static function episodeSchema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'paragraphs', 'summary', 'image_prompt', 'teaser'], 'properties' => [
            'title' => ['type' => 'string'], 'paragraphs' => ['type' => 'array', 'items' => ['type' => 'string']],
            'summary' => ['type' => 'string'], 'image_prompt' => ['type' => 'string'], 'teaser' => ['type' => 'string'],
        ]];
    }

    private static function saveEpisode(Serial $serial, int $n, array $e): Post
    {
        $sec = Section::where('slug', 'dharabahik')->first();
        $desk = Author::where('slug', 'hatpakha-desk')->first();
        $post = new Post;
        $post->forceFill([
            'title' => self::fullTitle($serial, $n, (string) $e['title']),
            'excerpt' => mb_substr($serial->logline ?? '', 0, 300),
            'body_html' => collect((array) $e['paragraphs'])->map(fn ($p) => '<p>'.e(trim((string) $p)).'</p>')->implode(''),
            'section_id' => $sec?->id, 'serial_id' => $serial->id, 'episode_no' => $n, 'episode_summary' => mb_substr(trim((string) $e['summary']), 0, 2000),
            'episode_teaser' => filled($e['teaser'] ?? null) ? mb_substr(trim((string) $e['teaser']), 0, 300) : null,   // D172
            'format' => 'prose', 'language' => 'bn', 'status' => 'draft',
            'slug' => 'desk-serial-'.$serial->id.'-'.$n.'-'.Str::lower(Str::random(4)),
            'ai_drafted_at' => now(),
            'ai_note' => "এআই ধারাবাহিক (হাতপাখা ডেস্ক), পর্ব {$n}। প্রকাশের আগে নিজের মতো করে ঘষেমেজে নিন; ছবিটি দেখে বসান।",
        ])->save();
        if ($desk) {
            $post->authors()->sync([$desk->id => ['position' => 0]]);
        }
        self::picture($post, (string) $e['image_prompt']);
        EditorTask::propose('serial_episode', __('ধারাবাহিকের নতুন পর্ব: ').$post->title, [], $post);

        return $post;
    }

    /** Eye-catching, but never a real person and never written words in the picture. */
    public const STYLE = 'Striking cinematic illustration for a Bengali serial story, like a contemporary book or web-series poster: '
        .'bold composition with one strong focal point, dramatic light, rich deep colours, a sense of mystery and story; '
        .'Bengali setting, clothes and architecture drawn accurately. No text, letters, numbers or logos anywhere. No real or famous person. No gore.';

    private static function picture(Post $post, string $prompt): void
    {
        if (Illustrator::blocked() || blank($prompt)) {
            return;
        }
        [$bytes] = Illustrator::paint(self::STYLE.' Scene: '.mb_substr(trim($prompt), 0, 700), $post->id);
        if ($bytes) {
            $tmp = tempnam(sys_get_temp_dir(), 'ser');
            file_put_contents($tmp, $bytes);
            rescue(fn () => CoverImage::propose($post, $tmp), null, false);   // the owner approves it on the edit page
            @unlink($tmp);
        }
    }

    public static function cover(Serial $serial, string $prompt): void
    {
        if (Illustrator::blocked() || blank($prompt)) {
            return;
        }
        [$bytes] = Illustrator::paint(self::STYLE.' Poster for the whole serial. Scene: '.mb_substr(trim($prompt), 0, 700));
        if ($bytes) {
            $tmp = tempnam(sys_get_temp_dir(), 'ser');
            file_put_contents($tmp, $bytes);
            $saved = rescue(fn () => CoverImage::store($tmp), null, false);
            @unlink($tmp);
            if ($saved) {
                $serial->forceFill(['cover_path' => $saved['path'], 'cover_width' => $saved['width'], 'cover_height' => $saved['height']])->save();
            }
        }
    }
}
