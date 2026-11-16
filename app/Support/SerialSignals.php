<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Serial;
use Illuminate\Support\Facades\DB;

/**
 * D215 (owner: "check how people are reacting to the published ones, likes, visits, and based on that change the
 * serials, the next series, the next chapter, the concept"): what readers did with a serial's episodes, counted from
 * the site's own records and given to the serial writer with every episode and every new plan. Numbers only, with a
 * few plain conclusions drawn by fixed rules; the writer decides what to do with them inside the story's own plan.
 */
final class SerialSignals
{
    /** Below this many opens an episode's numbers are too small to mean anything. */
    public const ENOUGH = 20;

    /** @return list<array{no: int, title: string, opens: int, finished: int, likes: int, comments: int}> */
    public static function episodes(Serial $s): array
    {
        $out = [];
        foreach ($s->liveEpisodes()->get() as $p) {
            $out[] = [
                'no' => (int) $p->episode_no, 'title' => \App\Ai\SerialWriter::ownTitle((string) $p->title, $s->title),
                'opens' => (int) DB::table('post_views')->where('post_id', $p->id)->sum('views'),
                'finished' => (int) DB::table('reading_history')->where('post_id', $p->id)->where('progress', '>=', 90)->count(),
                'likes' => (int) $p->likes_count,
                'comments' => (int) $p->comments()->where('status', 'approved')->count(),
            ];
        }

        return $out;
    }

    /** Plain conclusions from the numbers, by fixed rules. @return list<string> */
    public static function lessons(Serial $s): array
    {
        $eps = self::episodes($s);
        $lessons = [];
        if (count($eps) >= 2) {
            [$prev, $last] = array_slice($eps, -2);
            if ($prev['opens'] >= self::ENOUGH && $last['opens'] < 0.6 * $prev['opens']) {
                $lessons[] = "Far fewer readers opened episode {$last['no']} than episode {$prev['no']}: the ending of episode {$prev['no']} did not pull them back. End the next episode on a stronger, more concrete hook, and open it in the middle of something happening.";
            }
            $best = collect($eps)->filter(fn ($e) => $e['opens'] >= self::ENOUGH)->sortByDesc(fn ($e) => ($e['likes'] + 2 * $e['comments']) / max(1, $e['opens']))->first();
            if ($best && ($best['likes'] + $best['comments']) > 0) {
                $lessons[] = "Readers reacted most to episode {$best['no']} (\"{$best['title']}\"): notice what that episode did and do more of it.";
            }
        }
        foreach ($eps as $e) {
            if ($e['opens'] >= self::ENOUGH && $e['finished'] > 0 && $e['finished'] < 0.25 * $e['opens']) {
                $lessons[] = "Few of those who opened episode {$e['no']} read it to the end: it may have started slowly or run long. Get into the scene faster.";
                break;
            }
        }
        // A detective case: how the readers' guesses are spread (never a reason to change who did it).
        if (($tally = CaseGuesses::tally($s)) && array_sum($tally) >= 10) {
            $total = array_sum($tally);
            $onCulprit = (int) ($tally[CaseGuesses::culprit($s)] ?? 0);
            if ($onCulprit > 0.6 * $total) {
                $lessons[] = 'Most readers who guessed already suspect the real culprit: without changing the solution, give the other suspects stronger, fair reasons to be suspected in the coming episodes.';
            } elseif ($onCulprit < 0.1 * $total) {
                $lessons[] = 'Almost nobody suspects the real culprit: make sure the clues that point to them are plainly on the page, so the ending feels fair and not a trick.';
            }
        }

        return $lessons;
    }

    /** For the writer of the next episode of this serial. */
    public static function forEpisode(Serial $s): string
    {
        $eps = self::episodes($s);
        if (! $eps) {
            return '';
        }
        $rows = collect($eps)->map(fn ($e) => "episode {$e['no']}: opened {$e['opens']}, read to the end {$e['finished']}, likes {$e['likes']}, comments {$e['comments']}")->implode("\n");
        $follows = (int) DB::table('serial_follows')->where('serial_id', $s->id)->count();

        return "\n<reader_signals>\nWhat readers did so far (the site's own counts; small numbers are hints, not proof):\n{$rows}\nfollowing this serial: {$follows}"
            .(($l = self::lessons($s)) ? "\nWhat this suggests:\n- ".implode("\n- ", $l) : '')
            ."\nUse this to pace and sharpen the coming episode. Never change the plan's facts or, in a detective case, the solution because of it.\n</reader_signals>";
    }

    /** For the planner of a new serial by the same writer: how the earlier ones did. */
    public static function forPlan(string $writer): string
    {
        $rows = [];
        foreach (Serial::where('writer', $writer)->whereNull('user_id')->whereIn('status', ['active', 'finished', 'paused'])->oldest('id')->get() as $s) {
            $eps = self::episodes($s);
            if (! $eps) {
                continue;
            }
            $first = $eps[0]['opens'];
            $last = end($eps)['opens'];
            $rows[] = '"'.$s->title.'" ('.count($eps).' episodes): first episode opened '.$first.', latest '.$last.', likes '.array_sum(array_column($eps, 'likes')).', comments '.array_sum(array_column($eps, 'comments'))
                .', followers '.(int) DB::table('serial_follows')->where('serial_id', $s->id)->count()
                .(($t = CaseGuesses::tally($s)) ? ', readers who guessed '.array_sum($t).' ('.(int) ($t[CaseGuesses::culprit($s)] ?? 0).' right)' : '');
        }

        return $rows ? "\n<reader_signals>\nHow readers took the earlier ones (the site's own counts; small numbers are hints):\n".implode("\n", $rows)
            ."\nLearn from it: keep what held readers from the first episode to the last, and change what lost them (the kind of crime, the setting, the pace, how early the hook comes).\n</reader_signals>" : '';
    }
}
