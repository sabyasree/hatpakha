<?php

namespace App\Support;

use App\Ai\Claude;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D179 (owner: "keep an option for users to report a word if it is not correct, and fix them automatically").
 * A player reports a word, clue or riddle from a puzzle they just played. Every hour the AI editor looks at the
 * reported entries and decides: keep it, correct the spelling or the clue, or take it out of the games. The decision
 * is applied by itself from the next day and listed in /desk → শব্দের অভিযোগ, where it can be undone.
 * Without AI, an entry three different players reported is taken out until someone looks.
 */
final class WordReports
{
    public const REASONS = ['spelling' => 'বানান ভুল', 'clue' => 'সূত্র বা ধাঁধা ঠিক নয়', 'hard' => 'শব্দটা অচেনা বা খুব কঠিন', 'other' => 'অন্য কিছু'];

    public const STATUSES = ['new' => 'দেখা বাকি', 'kept' => 'ঠিকই ছিল', 'fixed' => 'ঠিক করা হয়েছে', 'removed' => 'খেলা থেকে সরানো হয়েছে'];

    /** The answers a player could have seen today or yesterday: only those can be reported. */
    public static function reportable(string $game): array
    {
        $out = [];
        foreach ([now('Asia/Kolkata'), now('Asia/Kolkata')->subDay()] as $day) {
            $sets = $game === 'riddle' ? [WordGame::riddle($day)['words']] : [WordGame::day($day)['words'], WordGame::crossword($day)['words'], WordGame::crossword($day, 'easy')['words'], WordGame::crossword($day, 'hard')['words']];
            foreach ($sets as $set) {
                foreach ($set as $w) {
                    $out[$w['answer']] = true;
                }
            }
        }

        return array_keys($out);
    }

    public static function store(Request $r, string $game, string $word, string $reason, ?string $note): bool
    {
        if (! in_array($word, self::reportable($game), true)) {
            return false;
        }
        DB::table('word_reports')->insertOrIgnore([
            'game' => $game, 'word' => $word, 'reason' => $reason, 'note' => filled($note) ? mb_substr(trim(strip_tags((string) $note)), 0, 300) : null,
            'user_id' => $r->user()?->id, 'sender_hash' => Kolom::senderHash($r), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return true;
    }

    /** Hourly: decide the reported entries and apply the decisions. Returns how many entries were decided. */
    public static function review(int $max = 15): int
    {
        $groups = DB::table('word_reports')->where('status', 'new')->get()->groupBy(fn ($r) => $r->game.'|'.$r->word)->take($max);
        if ($groups->isEmpty()) {
            return 0;
        }
        $items = [];
        foreach ($groups as $key => $reports) {
            [$game, $word] = explode('|', $key, 2);
            $k = $game === 'riddle' ? 1 : 0;
            $row = collect(WordGame::listFor($game, now('Asia/Kolkata')->addDay()))->first(fn ($x) => $x && $x[$k] === $word);
            if (! $row) {   // already changed or taken out
                DB::table('word_reports')->whereIn('id', $reports->pluck('id'))->update(['status' => 'kept', 'outcome' => 'আগেই বদলানো হয়েছে', 'updated_at' => now()]);

                continue;
            }
            $items[$key] = ['game' => $game, 'word' => $word, 'clue' => $row[1 - $k], 'reports' => $reports];
        }
        if (! $items) {
            return 0;
        }
        [$d] = Claude::json('words', 'You are a careful Bengali copy editor (পশ্চিমবঙ্গ বাংলা আকাদেমি spelling; Bangladesh forms are also correct Bengali). '
            .'Players of a word game reported entries. Each entry is an answer word with its clue (for a riddle: the riddle and its answer). For each, decide: '
            .'"keep" if the spelling is a standard form and the clue is true and points to this word; a word being a West Bengal or a Bangladesh form, or merely unfamiliar to one player, is NOT a reason to change it. '
            .'"fix" if the spelling is wrong or the clue is wrong, misleading or fits another common word better: give the corrected word (the SAME word, standard spelling; empty string if the word is fine) and/or a better clue (empty string if the clue is fine). '
            .'"remove" if the word is obscure, offensive, not really one word, or cannot be made fair. '
            .'A word has 3-6 অক্ষর (a consonant with its conjunct and vowel sign is one), a riddle answer 2-6; Bengali letters only; the clue must not contain the answer. Give the reason in one short Bengali sentence.',
            collect($items)->map(fn ($it, $key) => '<entry key="'.e($key).'" kind="'.$it['game'].'"><answer>'.e($it['word']).'</answer><clue>'.e($it['clue']).'</clue>'
                .$it['reports']->map(fn ($r) => '<report reason="'.$r->reason.'">'.e((string) $r->note).'</report>')->implode('').'</entry>')->implode("\n"),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['entries'], 'properties' => ['entries' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['key', 'verdict', 'word', 'clue', 'why'],
                'properties' => ['key' => ['type' => 'string'], 'verdict' => ['type' => 'string', 'enum' => ['keep', 'fix', 'remove']], 'word' => ['type' => 'string'], 'clue' => ['type' => 'string'], 'why' => ['type' => 'string']]]]]],
            config('ai.anthropic.tags_model'), 4000);
        $verdicts = collect((array) ($d['entries'] ?? []))->keyBy('key');
        $done = 0;
        foreach ($items as $key => $it) {
            $v = $verdicts[$key] ?? null;
            if (! $v) {   // no AI: three different players are enough to take it out until someone looks
                if ($it['reports']->unique('sender_hash')->count() < 3) {
                    continue;
                }
                $v = ['verdict' => 'remove', 'word' => '', 'clue' => '', 'why' => 'তিনজন খেলোয়াড় ভুল বলেছেন; এআই ছাড়া যাচাই হয়নি'];
            }
            self::apply($it['game'], $it['word'], $it['clue'], $v, $it['reports']->pluck('id')->all());
            $done++;
        }

        return $done;
    }

    private static function apply(string $game, string $word, string $clue, array $v, array $reportIds): void
    {
        $verdict = (string) $v['verdict'];
        $newWord = trim((string) ($v['word'] ?? ''));
        $newClue = trim((string) ($v['clue'] ?? ''));
        $why = mb_substr(trim((string) ($v['why'] ?? '')), 0, 300);
        if ($verdict === 'fix') {
            $newWord = $newWord === $word ? '' : $newWord;
            $newClue = $newClue === $clue ? '' : $newClue;
            $final = $newWord ?: $word;
            $n = count(WordGame::letters($final));
            $k = $game === 'riddle' ? 1 : 0;
            $taken = $newWord !== '' && collect(WordGame::listFor($game))->contains(fn ($x) => $x && $x[$k] === $newWord);
            $bad = ($newWord === '' && $newClue === '') || implode('', WordGame::letters($final)) !== $final || preg_match('/[^\x{0980}-\x{09FF}]/u', $final)
                || $n < ($game === 'riddle' ? 2 : 3) || $n > 6 || str_contains($newClue ?: $clue, $final) || $taken;
            if ($bad) {   // a correction that is itself not fit for the game: safer out than wrong
                $verdict = 'remove';
                $why = trim($why.' (সংশোধনটি খেলার নিয়মে মিলল না, তাই সরানো হল)');
            }
        }
        if ($verdict !== 'keep') {
            DB::table('word_fixes')->updateOrInsert(['game' => $game, 'word' => $word], [
                'action' => $verdict === 'fix' ? 'replace' : 'remove', 'new_word' => $verdict === 'fix' && $newWord !== '' ? $newWord : null,
                'new_clue' => $verdict === 'fix' && $newClue !== '' ? mb_substr($newClue, 0, 400) : null, 'reason' => $why,
                'from_day' => now('Asia/Kolkata')->addDay()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            Cache::forget('hp.wordfixes');
        }
        $status = ['keep' => 'kept', 'fix' => 'fixed', 'remove' => 'removed'][$verdict];
        $outcome = $verdict === 'fix' ? trim(($newWord !== '' ? $word.' → '.$newWord.'। ' : '').($newClue !== '' ? 'নতুন সূত্র: '.$newClue.'। ' : '').$why) : $why;
        DB::table('word_reports')->whereIn('id', $reportIds)->update(['status' => $status, 'outcome' => mb_substr($outcome, 0, 300), 'updated_at' => now()]);
        AuditLog::record('words.'.$status, null, ['game' => $game, 'word' => $word, 'new_word' => $newWord ?: null, 'why' => $why]);
    }

    /** The desk's undo: the entry goes back to how it was. */
    public static function undo(string $game, string $word): void
    {
        DB::table('word_fixes')->where('game', $game)->where('word', $word)->delete();
        DB::table('word_reports')->where('game', $game)->where('word', $word)->update(['status' => 'kept', 'outcome' => 'ডেস্ক থেকে আগের মতো করা হয়েছে', 'updated_at' => now()]);
        Cache::forget('hp.wordfixes');
        AuditLog::record('words.undone', null, ['game' => $game, 'word' => $word]);
    }
}
