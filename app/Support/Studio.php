<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Post;
use App\Models\SocialPost;
use Illuminate\Support\Str;

/**
 * The marketing studio (D182): pictures and reels for the planned posts, drawn with no person and no open page.
 *
 * The web server cannot draw them (no browser, no ffmpeg, and PHP's own drawing breaks Bengali letters), so a GitHub
 * Actions job does (studio/render.mjs, .github/workflows/studio.yml): it asks `hatpakha:studio list` what is waiting,
 * draws each one in a headless browser with our own fonts, uploads the files over the deploy SSH connection into a
 * private inbox, and `hatpakha:studio take` checks each file's real type and moves it under public/media/cards.
 * Nothing here is reachable from the web.
 */
final class Studio
{
    public const INBOX = 'app/private/studio-in';

    /** Slide: a line from a piece, exactly as written, with the writer's name. */
    public static function line(Post $post, string $quote): array
    {
        $post->loadMissing('authors', 'section');

        return ['t' => 'line', 'chip' => (string) ($post->section?->name_bn ?: 'হাতপাখা'), 'quote' => $quote,
            'by' => (string) ($post->authors->first()?->publicName() ?? ''), 'title' => (string) $post->title];
    }

    /** A poem as slides: title, up to four stanzas (exact text), and where to read the rest. */
    public static function poem(Post $post, array $stanzas): array
    {
        $post->loadMissing('authors', 'section');
        $chip = (string) ($post->section?->name_bn ?: 'কবিতা');
        $by = (string) ($post->authors->first()?->publicName() ?? '');
        $slides = [['t' => 'title', 'chip' => $chip, 'title' => (string) $post->title, 'by' => $by, 'note' => 'পাশে সরান →']];
        foreach (array_slice($stanzas, 0, 4) as $s) {
            $slides[] = ['t' => 'stanza', 'chip' => $chip, 'text' => $s];
        }
        $slides[] = ['t' => 'end', 'dark' => true, 'title' => (string) $post->title, 'by' => $by];

        return $slides;
    }

    /** What is waiting to be drawn, for the job that draws. @return list<array{id: int, format: string, slides: array, reel: ?array}> */
    public static function pending(int $max = 12): array
    {
        return SocialPost::where('status', 'ready')->whereNotNull('design')->whereNull('media')->whereIn('format', ['card', 'carousel', 'reel'])
            ->where('created_at', '>=', now()->subDays(4))->orderByRaw('slot_at is null, slot_at')->limit($max)->get()
            ->map(fn (SocialPost $s) => ['id' => $s->id, 'format' => $s->format, 'slides' => array_values((array) ($s->design['slides'] ?? [])), 'reel' => $s->design['reel'] ?? null])->all();
    }

    /** Move what the job uploaded from the private inbox into public/media/cards. Returns how many posts got their media. */
    public static function take(): int
    {
        $inbox = storage_path(self::INBOX);
        $done = 0;
        foreach (glob($inbox.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $s = ctype_digit(basename($dir)) ? SocialPost::find((int) basename($dir)) : null;
            if ($s && $s->design && ! $s->media) {
                $media = self::accept($s, $dir);
                if ($media) {
                    $texts = (array) $s->texts;
                    if ($media['images'] ?? null) {
                        $texts['card_image'] = $media['images'][0];   // the marketing page and Instagram's single picture use it
                    }
                    $s->forceFill(['media' => $media, 'texts' => $texts])->save();
                    AuditLog::record('marketing.studio', null, ['social_post' => $s->id, 'images' => count($media['images'] ?? []), 'video' => isset($media['video'])]);
                    $done++;
                }
            }
            array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir);
        }

        return $done;
    }

    /** Only real JPEGs and MP4s of sensible size, stored under random names. */
    private static function accept(SocialPost $s, string $dir): ?array
    {
        $target = 'media/cards/'.now()->format('Y/m');
        if (! is_dir(public_path($target))) {
            mkdir(public_path($target), 0775, true);
        }
        CoverImage::guardFolder();
        $keep = function (string $file, string $ext) use ($target): ?string {
            $path = $target.'/'.Str::lower(Str::random(24)).'.'.$ext;

            return @copy($file, public_path($path)) ? $path : null;
        };
        $jpeg = function (string $file): bool {
            $i = @getimagesize($file);

            return $i && $i[2] === IMAGETYPE_JPEG && $i[0] >= 320 && $i[0] <= 4000 && filesize($file) <= 8 * 1024 * 1024;
        };
        $media = [];
        $want = max(1, count((array) ($s->design['slides'] ?? [])));
        for ($n = 1; $n <= min($want, 10); $n++) {
            $f = $dir.'/'.$n.'.jpg';
            if (is_file($f) && $jpeg($f) && ($p = $keep($f, 'jpg'))) {
                $media['images'][] = $p;
            }
        }
        if ($s->format === 'reel') {
            $v = $dir.'/reel.mp4';
            $mime = is_file($v) ? ((new \finfo(FILEINFO_MIME_TYPE))->file($v) ?: '') : '';
            if ($mime !== 'video/mp4' || filesize($v) > 60 * 1024 * 1024 || ! ($media['video'] = $keep($v, 'mp4'))) {
                return null;
            }
            if (is_file($dir.'/cover.jpg') && $jpeg($dir.'/cover.jpg')) {
                $media['cover'] = $keep($dir.'/cover.jpg', 'jpg');
            }

            return $media;
        }

        return count($media['images'] ?? []) === min($want, 10) ? $media : null;
    }
}
