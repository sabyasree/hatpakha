<?php

namespace App\Ai;

use App\Models\EditorTask;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * D101: the বর্ণমালা: letters and words (database/content/bornomala.php), with a picture (designer) and an AI voice
 * (narrator) per letter. Both wait for the owner's approval; only approved ones show on the page.
 * State in the setting kids.bornomala: {index: {pic, pic_ok, audio, audio_ok}}.
 */
final class KidsAlphabet
{
    /** @return list<array{0:string,1:string,2:string,3:string}> */
    public static function letters(): array
    {
        return require database_path('content/bornomala.php');
    }

    public static function state(): array
    {
        return json_decode((string) Setting::get('kids.bornomala'), true) ?: [];
    }

    public static function save(array $state): void
    {
        Setting::put('kids.bornomala', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** The designer: one simple picture per letter still without one. */
    public static function pictures(int $max = 12): int
    {
        if (Illustrator::blocked()) {
            return 0;
        }
        $state = self::state();
        $done = 0;
        foreach (self::letters() as $i => [, $word, $en]) {
            if ($done >= $max || ! empty($state[$i]['pic'])) {
                continue;
            }
            [$bytes] = Illustrator::paint('A simple, warm picture-book illustration for a young child of: '.$en.'. One subject, centred, on a plain cream background. '
                .'Flat shapes, soft edges, colours only from red, navy, sky blue and peach. Friendly, gentle, nothing scary. No text, no letters, no numbers.');
            if ($bytes && ($img = @imagecreatefromstring($bytes))) {
                $rel = 'media/kids/bornomala/'.$i.'-'.Str::lower(Str::random(4)).'.webp';
                @mkdir(public_path('media/kids/bornomala'), 0755, true);
                $small = imagescale($img, 512);
                imagewebp($small ?: $img, public_path($rel), 82);
                $state[$i] = array_merge($state[$i] ?? [], ['pic' => '/'.$rel, 'pic_ok' => false]);
                $done++;
            }
        }
        self::save($state);
        if ($done) {
            EditorTask::propose('kids_alphabet', __('বর্ণমালার নতুন কণ্ঠ/ছবি অনুমোদনের অপেক্ষায়'), []);
        }

        return $done;
    }

    /** Owner: approve, or remove (so it can be made again). $what = pic | audio. */
    public static function decide(int $i, string $what, bool $ok): void
    {
        $state = self::state();
        if ($ok) {
            $state[$i][$what.'_ok'] = true;
        } else {
            if ($p = $state[$i][$what] ?? null) {
                @unlink(public_path(ltrim($p, '/')));
            }
            unset($state[$i][$what], $state[$i][$what.'_ok']);
        }
        self::save($state);
    }

    /** For the page: letters with approved picture/voice only. */
    public static function forPage(): array
    {
        $state = self::state();

        return collect(self::letters())->map(fn ($l, $i) => [
            'letter' => $l[0], 'word' => $l[1], 'kind' => $l[3],
            'pic' => ($state[$i]['pic_ok'] ?? false) ? $state[$i]['pic'] : null,
            'audio' => ($state[$i]['audio_ok'] ?? false) ? $state[$i]['audio'] : null,
        ])->all();
    }
}
