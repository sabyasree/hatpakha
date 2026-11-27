<?php

namespace App\Support;

use App\Ai\Claude;
use App\Models\AuditLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D328 (owner: "সাপ্তাহিক পত্রিকা-কুইজ"): five questions on the week's pieces. On Saturday the AI drafts next week's
 * quiz from what was published in the last seven days (each question names its piece; nothing about an ending or a
 * twist); the desk edits and approves it (/desk → পত্রিকা-কুইজ). Nothing reaches readers before that click (D42).
 * The quiz opens on Sunday (Kolkata) and stays for the week; it is worth 100 like a daily game and counts once, on
 * the day it is played. Every answer links to its piece, so the way to win is to read.
 */
final class WeeklyQuiz
{
    public const QUESTIONS = 5;

    /** The Sunday that starts the week a day falls in. */
    public static function week(Carbon|string|null $day = null): string
    {
        $d = $day instanceof Carbon ? $day->copy()->setTimezone('Asia/Kolkata') : Carbon::parse($day ?? now('Asia/Kolkata')->toDateString(), 'Asia/Kolkata');

        return $d->startOfDay()->subDays($d->dayOfWeek)->toDateString();
    }

    /** The approved quiz open on a day, as plain data (D18: no models in the cache). */
    public static function live(Carbon|string|null $day = null): ?array
    {
        $week = self::week($day);
        $row = Cache::remember('hp.quiz.'.$week, 300, fn () => (array) (DB::table('game_quizzes')->where('week', $week)->where('status', 'approved')->first() ?? []));
        if (! $row) {
            return null;
        }
        $qs = array_values(array_filter((array) json_decode((string) $row['questions'], true), fn ($q) => self::valid($q)));

        return $qs ? ['id' => (int) $row['id'], 'week' => $week, 'questions' => $qs] : null;
    }

    public static function forget(string $week): void
    {
        Cache::forget('hp.quiz.'.$week);
    }

    public static function count(Carbon|string|null $day = null): int
    {
        return count(self::live($day)['questions'] ?? []);
    }

    /** Whether a player already has the quiz of that day's week on the board. */
    public static function played(User $user, string $day): bool
    {
        $from = self::week($day);

        return DB::table('game_scores')->where('user_id', $user->id)->where('game', 'quiz')
            ->whereBetween('day', [$from, Carbon::parse($from)->addDays(6)->toDateString()])->exists();
    }

    public static function valid(mixed $q): bool
    {
        return is_array($q) && trim((string) ($q['q'] ?? '')) !== '' && count(array_filter(array_map('trim', (array) ($q['options'] ?? [])), 'strlen')) === 4
            && in_array((int) ($q['answer'] ?? -1), [0, 1, 2, 3], true) && count(array_unique(array_map('trim', (array) $q['options']))) === 4;
    }

    /** The pieces a week's quiz may ask about: published in the seven days before it opens, not the desk's own. */
    public static function pieces(string $week)
    {
        $to = Carbon::parse($week, 'Asia/Kolkata')->startOfDay();

        return Post::published()->whereBetween('published_at', [$to->copy()->subDays(7)->utc(), $to->copy()->utc()])
            ->whereDoesntHave('authors', fn ($q) => $q->where('is_desk', true))
            ->orderByDesc('published_at')->limit(14)->get();
    }

    /**
     * Saturday: draft the next week's quiz if it has none. Returns why nothing was drafted, or null.
     */
    public static function draft(?string $week = null, bool $force = false): ?string
    {
        $week ??= Carbon::parse(self::week(), 'Asia/Kolkata')->addDays(7)->toDateString();
        $row = DB::table('game_quizzes')->where('week', $week)->first();
        if ($row && ($row->status === 'approved' || (! $force && $row->questions !== '[]'))) {   // an empty row only holds why the last try failed
            return 'এই সপ্তাহের কুইজ আগেই আছে';
        }
        $posts = self::pieces($week);
        if ($posts->count() < 3) {
            return self::note($week, 'গত সাত দিনে তিনটির কম লেখা বেরিয়েছে; কুইজ বানানো হয়নি');
        }
        if ($why = Claude::blocked()) {
            return self::note($week, 'এআই চলছে না: '.$why);
        }
        $text = $posts->map(fn (Post $p) => '<piece id="'.$p->id.'" title="'.e($p->title).'" by="'.e($p->authors->pluck('name')->implode(', ')).'">'
            .e(mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', (string) $p->body_html))))), 0, 6000)).'</piece>')->implode("\n");
        [$d] = Claude::json('quiz',
            'You write a friendly weekly quiz for readers of হাতপাখা, a Bengali literary magazine. Write exactly '.self::QUESTIONS.' questions in simple চলিত Bengali, '
            .'each about a DIFFERENT piece below (use the piece id). A reader who has read the piece can answer; one who has not, cannot guess it from general knowledge. '
            .'Ask about a detail in the text (a place, an object, a name, what someone did, an image in a poem). NEVER ask about or reveal the ending, the twist, who did it, or how a story ends. '
            .'Nothing sad, violent or private as the point of a question; nothing about the writer\'s life. Each question has four short options (under 6 words each), exactly one right, the others plausible and from the same kind. '
            .'Give in "why" one short Bengali sentence saying where in the piece the answer is (without revealing the ending). Use only what the text says; never invent.',
            "Pieces published this week:\n".$text,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['questions'], 'properties' => ['questions' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['post_id', 'q', 'options', 'answer', 'why'],
                'properties' => ['post_id' => ['type' => 'integer'], 'q' => ['type' => 'string'], 'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'answer' => ['type' => 'integer'], 'why' => ['type' => 'string']]]]]],
            config('ai.anthropic.tags_model'), 4000);
        $ids = $posts->pluck('id')->all();
        $qs = array_values(array_filter((array) ($d['questions'] ?? []), fn ($q) => self::valid($q) && in_array((int) $q['post_id'], $ids, true)));
        if (count($qs) < 3) {
            return self::note($week, 'এআই ঠিকমতো প্রশ্ন দেয়নি; আবার চেষ্টা করুন বা নিজে লিখুন');
        }
        $qs = array_map(fn ($q) => ['q' => trim((string) $q['q']), 'options' => array_map(fn ($o) => trim((string) $o), array_slice((array) $q['options'], 0, 4)),
            'answer' => (int) $q['answer'], 'post_id' => (int) $q['post_id'], 'why' => trim((string) $q['why'])], array_slice($qs, 0, self::QUESTIONS));
        DB::table('game_quizzes')->updateOrInsert(['week' => $week], ['status' => 'draft', 'questions' => json_encode($qs, JSON_UNESCAPED_UNICODE), 'note' => null,
            'approved_by' => null, 'approved_at' => null, 'updated_at' => now(), 'created_at' => $row->created_at ?? now()]);
        AuditLog::record('quiz.drafted', null, ['week' => $week, 'questions' => count($qs)]);

        return null;
    }

    private static function note(string $week, string $why): string
    {
        $row = DB::table('game_quizzes')->where('week', $week)->first();
        if (! $row) {
            DB::table('game_quizzes')->insert(['week' => $week, 'status' => 'draft', 'questions' => '[]', 'note' => $why, 'created_at' => now(), 'updated_at' => now()]);
        } elseif ($row->status === 'draft') {
            DB::table('game_quizzes')->where('id', $row->id)->update(['note' => $why, 'updated_at' => now()]);
        }

        return $why;
    }
}
