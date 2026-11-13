<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Post;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;

/**
 * Posting to the Hatpakha Instagram account (owner, 2026-11-12), the professional account linked to the Facebook Page,
 * with the same Page token. Instagram takes a picture (JPEG) and a caption, never a link:
 *
 *   - the designer's card, drawn in the owner's browser (Bengali letters right) and saved with the post, or else
 *   - the piece's own picture when it is a JPEG of a shape Instagram accepts (4:5 to 1.91:1).
 *
 * Same rules as Facebook: on the owner's click, at their time, or in automatic mode they switched on (D42);
 * never a piece that is not live; never from staging.
 */
final class InstagramPage
{
    public static function configured(): bool
    {
        return filled(config('services.facebook_page.instagram_id')) && filled(config('services.facebook_page.token'));
    }

    public static function ready(): bool
    {
        return self::configured() && MailGuard::live();
    }

    /** The picture for Instagram, as a public URL, or null when there is none it accepts. */
    public static function picture(SocialPost $s): ?string
    {
        $card = (string) ($s->texts['card_image'] ?? '');
        if ($card !== '' && is_file(public_path(ltrim($card, '/')))) {
            return asset(ltrim($card, '/'));
        }
        $subject = $s->subject;
        $path = $subject instanceof Post ? $subject->featured_image_path : null;
        if ($s->kind === 'poster') {
            $path = $s->texts['image'] ?? null;
        }
        $file = $path ? public_path(ltrim($path, '/')) : null;
        if (! $file || ! is_file($file) || ! preg_match('/\.jpe?g$/i', $file)) {
            return null;
        }
        [$w, $h] = array_pad((array) (@getimagesize($file) ?: []), 2, 0);
        $ratio = $h ? $w / $h : 0;

        return $ratio >= 0.8 && $ratio <= 1.91 ? asset(ltrim($path, '/')) : null;
    }

    public static function postable(SocialPost $s): ?string
    {
        if ($s->ig_media_id) {
            return __('আগেই ইনস্টাগ্রামে পোস্ট হয়েছে');
        }
        if (blank($s->texts['instagram'] ?? null)) {
            return __('ইনস্টাগ্রামের লেখা নেই');
        }
        $subject = $s->subject;
        if (($subject instanceof Post && ! $subject->isLive()) || ($subject instanceof Issue && $subject->status !== 'published')) {
            return __('লেখা বা সংখ্যাটি এখনও প্রকাশিত নয়');
        }
        if (! self::picture($s)) {
            return __('ইনস্টাগ্রামের জন্য ছবি নেই');
        }

        return null;
    }

    /** @return array{0: bool, 1: string} [posted, the post's address or the reason] */
    public static function publish(SocialPost $s, ?int $by = null): array
    {
        if (! self::ready()) {
            return [false, self::configured() ? __('শুধু আসল সাইট থেকে ইনস্টাগ্রামে পোস্ট হয়') : __('ইনস্টাগ্রামের চাবি দেওয়া নেই')];
        }
        if ($why = self::postable($s)) {
            return [false, $why];
        }
        [$published, $made] = self::upload((string) self::picture($s), (string) $s->texts['instagram']);
        if (! $published) {
            $why = mb_substr((string) ($made?->json('error.message') ?? ($made ? 'HTTP '.$made->status() : 'no connection')), 0, 280);
            $s->forceFill(['ig_error' => $why])->save();
            AuditLog::record('marketing.instagram_failed', null, ['social_post' => $s->id, 'error' => $why]);

            return [false, $why];
        }
        [$token, $auth, $base] = self::auth();
        $link = rescue(fn () => Http::timeout(15)->get($base.$published, ['fields' => 'permalink'] + $auth)->json('permalink'), null, false);
        $s->forceFill(['ig_media_id' => (string) $published, 'ig_permalink' => is_string($link) && str_starts_with($link, 'https://') ? mb_substr($link, 0, 300) : null, 'ig_error' => null])->save();
        AuditLog::record('marketing.instagram', null, ['social_post' => $s->id, 'ig' => (string) $published, 'by' => $by]);
        $s->settle($by);   // D170

        return [true, (string) ($s->ig_permalink ?: $published)];
    }

    /** @return array{0: string, 1: array, 2: string} token, auth params, Graph base URL */
    private static function auth(): array
    {
        $token = (string) config('services.facebook_page.token');
        $auth = ['access_token' => $token] + (filled($secret = config('services.facebook_page.app_secret')) ? ['appsecret_proof' => hash_hmac('sha256', $token, (string) $secret)] : []);

        return [$token, $auth, 'https://graph.facebook.com/'.config('services.facebook_page.graph_version').'/'];
    }

    /**
     * 1. Instagram fetches the picture; 2. once it is ready, publish it.
     *
     * @return array{0: ?string, 1: mixed} [the new media id or null, the last response]
     */
    private static function upload(string $imageUrl, string $caption): array
    {
        [, $auth, $base] = self::auth();
        $ig = (string) config('services.facebook_page.instagram_id');
        $made = rescue(fn () => Http::asForm()->timeout(30)->post($base.$ig.'/media', ['image_url' => $imageUrl, 'caption' => mb_substr($caption, 0, 2200)] + $auth), null, false);
        $container = $made?->json('id');
        $published = null;
        if ($container) {
            for ($try = 0; $try < 5 && ! $published; $try++) {
                $res = rescue(fn () => Http::asForm()->timeout(30)->post($base.$ig.'/media_publish', ['creation_id' => $container] + $auth), null, false);
                $published = $res?->json('id');
                if (! $published && (int) $res?->json('error.code') === 9007 && $try < 4) {   // the picture is still being fetched
                    usleep(app()->runningUnitTests() ? 0 : 3_000_000);
                    continue;
                }
                $made = $res;
                break;
            }
        }

        return [$published ? (string) $published : null, $made];
    }

    /**
     * D170 (owner): post one particular card (the share card, the quote card or a new quote card). A post may carry
     * several cards over time, so this does not stop at the first Instagram post. Returns [posted, address or reason].
     *
     * @return array{0: bool, 1: string}
     */
    public static function publishCard(SocialPost $s, string $cardPath, string $key, ?int $by = null): array
    {
        if (! self::ready()) {
            return [false, self::configured() ? __('শুধু আসল সাইট থেকে ইনস্টাগ্রামে পোস্ট হয়') : __('ইনস্টাগ্রামের চাবি দেওয়া নেই')];
        }
        if (blank($s->texts['instagram'] ?? null)) {
            return [false, __('ইনস্টাগ্রামের লেখা নেই')];
        }
        $subject = $s->subject;
        if (($subject instanceof Post && ! $subject->isLive()) || ($subject instanceof Issue && $subject->status !== 'published')) {
            return [false, __('লেখা বা সংখ্যাটি এখনও প্রকাশিত নয়')];
        }
        [$published, $made] = self::upload(asset(ltrim($cardPath, '/')), (string) $s->texts['instagram']);
        if (! $published) {
            $why = mb_substr((string) ($made?->json('error.message') ?? ($made ? 'HTTP '.$made->status() : 'no connection')), 0, 280);
            AuditLog::record('marketing.instagram_failed', null, ['social_post' => $s->id, 'card' => $key, 'error' => $why]);

            return [false, $why];
        }
        [, $auth, $base] = self::auth();
        $link = rescue(fn () => Http::timeout(15)->get($base.$published, ['fields' => 'permalink'] + $auth)->json('permalink'), null, false);
        $link = is_string($link) && str_starts_with($link, 'https://') ? mb_substr($link, 0, 300) : null;
        $s->forceFill(['ig_media_id' => $s->ig_media_id ?: $published, 'ig_permalink' => $s->ig_permalink ?: $link, 'ig_error' => null] + self::cardPosted($s, $key, 'instagram', $link ?? $published))->save();
        AuditLog::record('marketing.instagram', null, ['social_post' => $s->id, 'ig' => $published, 'card' => $key, 'by' => $by]);
        $s->settle($by);

        return [true, $link ?? $published];
    }

    /** Remember which card went to which network (shown beside the card). @return array{texts: array} */
    public static function cardPosted(SocialPost $s, string $key, string $net, string $ref): array
    {
        $texts = (array) $s->texts;
        $texts['card_posts'][$key][$net] = ['ref' => $ref, 'at' => now()->toIso8601String()];

        return ['texts' => $texts];
    }

    /** Save the card the owner's browser drew (a JPEG upload) with the post, for Instagram. Returns its path. */
    public static function saveCard(SocialPost $s, string $tmpFile, string $key = 'share'): ?string
    {
        $info = @getimagesize($tmpFile);
        if (! $info || $info[2] !== IMAGETYPE_JPEG || $info[0] < 320 || $info[0] > 4000 || filesize($tmpFile) > 8 * 1024 * 1024) {
            return null;
        }
        $ratio = $info[0] / max(1, $info[1]);
        if ($ratio < 0.8 || $ratio > 1.91) {
            return null;
        }
        $dir = 'media/cards/'.now()->format('Y/m');
        @mkdir(public_path($dir), 0775, true);
        $path = $dir.'/'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(24)).'.jpg';
        if (! @copy($tmpFile, public_path($path))) {
            return null;
        }
        $texts = (array) $s->texts;
        // D170: the share card stays `card_image` (automatic Instagram uses it); other cards are kept by name
        $old = (string) ($key === 'share' ? ($texts['card_image'] ?? '') : ($texts['card_images'][$key] ?? ''));
        if ($old !== '' && str_starts_with($old, 'media/cards/')) {
            @unlink(public_path($old));
        }
        if ($key === 'share') {
            $texts['card_image'] = $path;
        } else {
            $texts['card_images'][$key] = $path;
        }
        $s->forceFill(['texts' => $texts])->save();

        return $path;
    }
}
