<?php

namespace App\Ai;

use App\Models\EditorTask;
use App\Models\Post;
use App\Models\Section;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * D101: the narrator (কথক) reads children's pieces and the alphabet aloud with an AI voice (OpenAI text to speech).
 * Every recording waits for the owner's approval; the page always says it is an AI voice. A writer's own reading
 * is never replaced.
 */
final class Narrator
{
    public static function blocked(): ?string
    {
        return Claude::blocked() ?? (blank(config('ai.openai.key')) ? 'No OpenAI key for the voice' : null);
    }

    /** Speak a text; long texts are read in parts and joined (MP3 frames join cleanly). @return array{0:?string,1:?string} */
    public static function speak(string $text, ?int $postId = null): array
    {
        if ($why = self::blocked()) {
            return [null, $why];
        }
        $parts = self::chunks($text, 1500);
        $audio = '';
        foreach ($parts as $part) {
            $res = rescue(fn () => Http::withToken((string) config('ai.openai.key'))->timeout(180)
                ->post(rtrim((string) config('ai.openai.base_url'), '/').'/audio/speech', [
                    'model' => config('ai.openai.tts_model'), 'voice' => config('ai.openai.tts_voice'), 'input' => $part, 'response_format' => 'mp3',
                    'instructions' => 'Read in Bengali (Bangla) with natural Kolkata pronunciation, warmly and slowly, like a parent reading to a young child. '
                        .'For rhymes, keep the rhythm and pause at line ends.',
                ]), null, false);
            if (! $res || ! $res->successful() || $res->body() === '') {
                return [null, 'voice API '.($res?->status() ?? 'connection').': '.mb_substr((string) ($res?->json('error.message') ?? ''), 0, 120)];
            }
            $audio .= $res->body();
        }
        Claude::log('openai', (string) config('ai.openai.tts_model'), 'tts', $postId, ['in' => 0, 'out' => 0, 'cache_read' => 0, 'cache_write' => 0],
            round(mb_strlen($text) / 1000 * (float) config('ai.openai.tts_price_per_1k_chars'), 4));

        return [$audio, null];
    }

    /** A reading for one children's piece, pending the owner's approval. */
    public static function forPost(Post $post): ?string
    {
        if ($post->audio_path && ! $post->audio_ai) {
            return 'লেখকের নিজের পাঠ আছে; যন্ত্রকণ্ঠ দেওয়া হয়নি।';
        }
        $text = $post->title."।\n\n".trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br />', '</p>'], ["\n", "\n", "\n\n"], (string) $post->body_html))));
        [$mp3, $err] = self::speak($text, $post->id);
        if (! $mp3) {
            return $err;
        }
        $rel = 'media/audio/ai-'.$post->id.'-'.Str::lower(Str::random(6)).'.mp3';
        @mkdir(public_path('media/audio'), 0755, true);
        file_put_contents(public_path($rel), $mp3);
        if ($post->audio_ai && $post->audio_path && is_file(public_path(ltrim($post->audio_path, '/')))) {
            @unlink(public_path(ltrim($post->audio_path, '/')));
        }
        $post->forceFill(['audio_path' => '/'.$rel, 'audio_status' => 'pending', 'audio_ai' => true])->saveQuietly();
        EditorTask::propose('audio', __('যন্ত্রকণ্ঠে পাঠ (এআই): ').$post->title, [], $post);

        return null;
    }

    /** Readings for children's pieces that have none yet. */
    public static function backlog(int $max = 5): int
    {
        $sec = Section::where('slug', Section::KIDS)->value('id');
        $done = 0;
        foreach (Post::where('section_id', $sec)->whereIn('status', ['draft', 'scheduled', 'published'])->whereNull('audio_path')->limit($max)->get() as $p) {
            $done += self::forPost($p) === null ? 1 : 0;
        }

        return $done;
    }

    /** The alphabet: "অ। অ-য় অজগর।" for every letter still without a voice. */
    public static function alphabet(): int
    {
        $state = KidsAlphabet::state();
        $done = 0;
        foreach (KidsAlphabet::letters() as $i => [$letter, $word]) {
            if (! empty($state[$i]['audio'])) {
                continue;
            }
            [$mp3] = self::speak($letter.'। '.$letter.'-এ '.$word.'।');
            if ($mp3) {
                $rel = 'media/kids/bornomala/'.$i.'-'.Str::lower(Str::random(4)).'.mp3';
                @mkdir(public_path('media/kids/bornomala'), 0755, true);
                file_put_contents(public_path($rel), $mp3);
                $state[$i] = array_merge($state[$i] ?? [], ['audio' => '/'.$rel, 'audio_ok' => false]);
                $done++;
            }
        }
        KidsAlphabet::save($state);
        if ($done) {
            EditorTask::propose('kids_alphabet', __('বর্ণমালার নতুন কণ্ঠ/ছবি অনুমোদনের অপেক্ষায়'), []);
        }

        return $done;
    }

    /** @return list<string> */
    private static function chunks(string $text, int $max): array
    {
        $out = [''];
        foreach (preg_split("/\n{2,}/u", trim($text)) as $para) {
            if (mb_strlen(end($out)) + mb_strlen($para) > $max && end($out) !== '') {
                $out[] = '';
            }
            $out[count($out) - 1] .= (end($out) === '' ? '' : "\n\n").$para;
        }

        return array_values(array_filter($out, fn ($p) => trim($p) !== ''));
    }
}
