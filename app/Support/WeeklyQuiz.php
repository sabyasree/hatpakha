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
 * twist). D330 (owner, 2026-11-28: "for Magazine quiz, make it fully automatic. Don't ask me for approval. For games,
 * I don't want to spend time as admin"): a second, separate AI check reads each question beside its piece and keeps
 * only those whose answer the text plainly gives, with no second right option and nothing given away; the server
 * also finds the answer's words in the piece. Three or more survive: the quiz goes live by itself. Fewer: tried again
 * that evening and just after midnight, then the week has no quiz (the page says so kindly). The desk page can still
 * change or withdraw a quiz, but nothing waits for it.
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

    /**
     * The pieces a week's quiz may ask about: published in the seven days before it opens, not the desk's own. A quiet
     * week reaches back three weeks so there is still a quiz.
     */
    public static function pieces(string $week, int $days = 7)
    {
        $to = Carbon::parse($week, 'Asia/Kolkata')->startOfDay();
        if ($days === 7 && self::pieces($week, 0)->count() < 3) {
            $days = 21;
        }

        return Post::published()->whereBetween('published_at', [$to->copy()->subDays($days ?: 7)->utc(), $to->copy()->utc()])
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
            .'Write a piece\'s title in «» as its own phrase followed by a word, never with a case ending stuck to the quote mark: «শিরোনাম» গল্পে…, «শিরোনাম» কবিতায়… (not «শিরোনাম»-এ). '
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
        $plain = $posts->mapWithKeys(fn (Post $p) => [$p->id => self::plain((string) $p->body_html)])->all();
        $checked = self::check($qs, $plain, $posts->pluck('title', 'id')->all());
        if (count($checked) < 3) {
            return self::note($week, 'প্রশ্নগুলো যাচাইয়ে টেকেনি ('.count($checked).'টা টিকেছে); আবার চেষ্টা হবে');
        }
        // D330: live by itself once checked; nobody has to approve it
        DB::table('game_quizzes')->updateOrInsert(['week' => $week], ['status' => 'approved', 'questions' => json_encode($checked, JSON_UNESCAPED_UNICODE), 'note' => null,
            'approved_by' => null, 'approved_at' => now(), 'updated_at' => now(), 'created_at' => $row->created_at ?? now()]);
        self::forget($week);
        AuditLog::record('quiz.published', null, ['week' => $week, 'questions' => count($checked), 'dropped' => count($qs) - count($checked)]);

        return null;
    }

    public static function plain(string $html): string
    {
        return \Normalizer::normalize(trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', $html))))), \Normalizer::FORM_C) ?: '';
    }

    /**
     * D330: the questions that pass both checks. (1) The server: a word of the right answer (two letters or more)
     * stands in the piece's text. (2) A second AI reading, told to be strict, with only that piece in front of it: the
     * text plainly gives the answer, no other option is also right, and the question gives away no ending or twist.
     * Without an AI answer nothing passes (better no quiz than a wrong one).
     *
     * @param  array<int, string>  $plain  post id => text
     */
    public static function check(array $qs, array $plain, array $titles = []): array
    {
        $qs = array_values(array_filter($qs, function ($q) use ($plain) {
            $text = $plain[$q['post_id']] ?? '';
            $right = \Normalizer::normalize((string) $q['options'][$q['answer']], \Normalizer::FORM_C) ?: '';
            foreach (preg_split('/[\s,;:।\-]+/u', $right) as $w) {
                if (mb_strlen($w) >= 2 && mb_strpos($text, mb_substr($w, 0, max(2, mb_strlen($w) - 2))) !== false) {
                    return true;   // the word, allowing a changed ending (চিঠি / চিঠিখানা, বটতলা / বটতলায়)
                }
            }

            return false;
        }));
        if (! $qs) {
            return [];
        }
        $items = collect($qs)->map(fn ($q, $i) => '<question n="'.$i.'"><piece title="'.e($titles[$q['post_id']] ?? '').'">'.e(mb_substr($plain[$q['post_id']] ?? '', 0, 6000)).'</piece>'
            .'<q>'.e($q['q']).'</q>'.collect($q['options'])->map(fn ($o, $k) => '<option n="'.$k.'"'.($k === $q['answer'] ? ' marked="right"' : '').'>'.e($o).'</option>')->implode('').'</question>')->implode("
");
        [$d] = Claude::json('quiz',
            'You are a strict fact-checker for a quiz in a Bengali literary magazine. For each question, read ONLY its piece. Answer ok=true only if ALL hold: '
            .'(a) the piece itself plainly states the option marked right as the answer to this question; (b) none of the other three options is also correct or arguable; '
            .'(c) the question and its options do not reveal or hint at an ending, a twist, a death, or who did it; (d) the wording is clear Bengali. When in doubt, ok=false. Give a short reason.',
            $items,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['checks'], 'properties' => ['checks' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['n', 'ok', 'reason'],
                'properties' => ['n' => ['type' => 'integer'], 'ok' => ['type' => 'boolean'], 'reason' => ['type' => 'string']]]]]],
            config('ai.anthropic.review_model'), 3000);
        $ok = collect((array) ($d['checks'] ?? []))->filter(fn ($c) => ! empty($c['ok']))->pluck('n')->map(fn ($n) => (int) $n)->all();

        return array_values(array_filter($qs, fn ($q, $i) => in_array($i, $ok, true), ARRAY_FILTER_USE_BOTH));
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
