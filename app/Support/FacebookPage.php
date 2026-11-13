<?php

namespace App\Support;

use App\Filament\Pages\Marketing;
use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Post;
use App\Models\Setting;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;

/**
 * Posting the marketing team's posts to the Hatpakha Facebook Page (owner, 2026-11-12), with the Page's own token.
 *
 *   - "ফেসবুকে দিন" posts one now; "সময় ঠিক করুন" posts it at a chosen time (the scheduler checks every 5 minutes).
 *   - Automatic mode (a switch in /desk → মার্কেটিং, off until the owner turns it on, D42) posts the oldest ready post
 *     twice a day, at good reading times, and never a piece that is not live.
 *   - Only from the live site: staging never posts to the real Page.
 *   - A post with a link shows Facebook's preview (our picture and title); a poster goes up as a photo.
 */
final class FacebookPage
{
    public const AUTO = 'facebook.auto';

    public static function configured(): bool
    {
        return filled(config('services.facebook_page.id')) && filled(config('services.facebook_page.token'));
    }

    /** Configured, and this is the live site (or a test that says so). */
    public static function ready(): bool
    {
        return self::configured() && MailGuard::live();
    }

    public static function auto(): bool
    {
        return Setting::get(self::AUTO) === '1';
    }

    /** What would go up: the text, and the link (or the poster's picture). */
    public static function payload(SocialPost $s): array
    {
        $text = trim((string) ($s->texts['facebook'] ?? ''));
        if ($s->kind === 'poster' && filled($s->texts['image'] ?? null)) {
            return ['edge' => 'photos', 'caption' => $text, 'url' => asset(ltrim((string) $s->texts['image'], '/'))];
        }
        $link = Marketing::link($s);
        $message = trim(preg_replace('/\s*'.preg_quote($link, '/').'\s*$/u', '', $text));   // the preview shows the link

        return ['edge' => 'feed', 'message' => $message, 'link' => $link];
    }

    /** May this one go up? Text present, not already up, and its piece or issue is live. */
    public static function postable(SocialPost $s): ?string
    {
        if ($s->fb_post_id || ($s->status ?? 'ready') !== 'ready') {   // a new row's status is the database default
            return __('আগেই পোস্ট হয়েছে, বা তালিকা থেকে সরানো');
        }
        if (blank($s->texts['facebook'] ?? null)) {
            return __('ফেসবুকের লেখা নেই');
        }
        $subject = $s->subject;
        if (($subject instanceof Post && ! $subject->isLive()) || ($subject instanceof Issue && $subject->status !== 'published')) {
            return __('লেখা বা সংখ্যাটি এখনও প্রকাশিত নয়');
        }

        return null;
    }

    /** @return array{0: bool, 1: string} [posted, Facebook's post id or the reason] */
    public static function publish(SocialPost $s, ?int $by = null): array
    {
        if (! self::ready()) {
            return [false, self::configured() ? __('শুধু আসল সাইট থেকে ফেসবুকে পোস্ট হয়') : __('ফেসবুকের চাবি দেওয়া নেই')];
        }
        if ($why = self::postable($s)) {
            return [false, $why];
        }
        $p = self::payload($s);
        $edge = $p['edge'];
        unset($p['edge']);
        $token = (string) config('services.facebook_page.token');
        $params = $p + ['access_token' => $token];
        if (filled($secret = config('services.facebook_page.app_secret'))) {
            $params['appsecret_proof'] = hash_hmac('sha256', $token, (string) $secret);
        }
        $url = 'https://graph.facebook.com/'.config('services.facebook_page.graph_version').'/'.config('services.facebook_page.id').'/'.$edge;
        $res = rescue(fn () => Http::asForm()->timeout(25)->post($url, $params), null, false);
        $id = $res?->json('post_id') ?? $res?->json('id');
        if (! $res || ! $res->successful() || ! $id) {
            $why = mb_substr((string) ($res?->json('error.message') ?? ($res ? 'HTTP '.$res->status() : 'no connection')), 0, 280);
            $s->forceFill(['fb_error' => $why, 'fb_scheduled_at' => null])->save();
            AuditLog::record('marketing.facebook_failed', null, ['social_post' => $s->id, 'error' => $why]);

            return [false, $why];
        }
        $s->forceFill(['fb_post_id' => (string) $id, 'fb_error' => null, 'fb_scheduled_at' => null])->save();
        $s->settle($by);   // D170: stays in the list while Instagram is still to do
        AuditLog::record('marketing.facebook', null, ['social_post' => $s->id, 'fb' => (string) $id, 'by' => $by]);

        return [true, (string) $id];
    }

    /**
     * D170 (owner): post one card as a photo on the Page, with the Facebook text (link included, since a photo post
     * shows no link preview). A post may carry several cards over time, so an earlier Facebook post doesn't stop it.
     *
     * @return array{0: bool, 1: string}
     */
    public static function publishCard(SocialPost $s, string $cardPath, string $key, ?int $by = null): array
    {
        if (! self::ready()) {
            return [false, self::configured() ? __('শুধু আসল সাইট থেকে ফেসবুকে পোস্ট হয়') : __('ফেসবুকের চাবি দেওয়া নেই')];
        }
        if (blank($s->texts['facebook'] ?? null)) {
            return [false, __('ফেসবুকের লেখা নেই')];
        }
        $subject = $s->subject;
        if (($subject instanceof Post && ! $subject->isLive()) || ($subject instanceof Issue && $subject->status !== 'published')) {
            return [false, __('লেখা বা সংখ্যাটি এখনও প্রকাশিত নয়')];
        }
        $token = (string) config('services.facebook_page.token');
        $params = ['caption' => trim((string) $s->texts['facebook']), 'url' => asset(ltrim($cardPath, '/')), 'access_token' => $token];
        if (filled($secret = config('services.facebook_page.app_secret'))) {
            $params['appsecret_proof'] = hash_hmac('sha256', $token, (string) $secret);
        }
        $url = 'https://graph.facebook.com/'.config('services.facebook_page.graph_version').'/'.config('services.facebook_page.id').'/photos';
        $res = rescue(fn () => Http::asForm()->timeout(25)->post($url, $params), null, false);
        $id = $res?->json('post_id') ?? $res?->json('id');
        if (! $res || ! $res->successful() || ! $id) {
            $why = mb_substr((string) ($res?->json('error.message') ?? ($res ? 'HTTP '.$res->status() : 'no connection')), 0, 280);
            AuditLog::record('marketing.facebook_failed', null, ['social_post' => $s->id, 'card' => $key, 'error' => $why]);

            return [false, $why];
        }
        $s->forceFill(['fb_post_id' => $s->fb_post_id ?: (string) $id, 'fb_error' => null] + InstagramPage::cardPosted($s, $key, 'facebook', (string) $id))->save();
        AuditLog::record('marketing.facebook', null, ['social_post' => $s->id, 'fb' => (string) $id, 'card' => $key, 'by' => $by]);
        $s->settle($by);

        return [true, (string) $id];
    }

    /** Every 5 minutes: the posts whose chosen time has come. */
    public static function due(): int
    {
        if (! self::ready()) {
            return 0;
        }
        $n = 0;
        foreach (SocialPost::whereNotNull('fb_scheduled_at')->where('fb_scheduled_at', '<=', now())->whereNull('fb_post_id')->where('status', 'ready')->oldest('fb_scheduled_at')->limit(3)->get() as $s) {
            if (self::publish($s)[0]) {
                $n++;
                self::alsoInstagram($s);
            }
        }

        return $n;
    }

    /** Twice a day, when automatic mode is on: the oldest ready post from the last two weeks (pieces, issues, collections, posters…). */
    public static function autoPost(): ?SocialPost
    {
        if (! self::ready() || ! self::auto()) {
            return null;
        }
        $s = self::nextAuto();
        if (! $s || ! self::publish($s)[0]) {
            return null;
        }
        self::alsoInstagram($s);

        return $s;   // one per run
    }

    /** A timed or automatic post goes to Instagram too, when there is a picture Instagram accepts (owner, 2026-11-12). */
    private static function alsoInstagram(SocialPost $s): void
    {
        if (InstagramPage::ready() && InstagramPage::postable($s->fresh()) === null) {
            rescue(fn () => InstagramPage::publish($s->fresh()), null, false);
        }
    }

    /** Which post automatic mode sends next (shown to the owner on the marketing page). */
    public static function nextAuto(): ?SocialPost
    {
        $candidates = SocialPost::with('subject')->where('kind', '!=', 'weekly')->where('status', 'ready')->whereNull('fb_post_id')
            ->whereNull('fb_scheduled_at')->whereNull('fb_error')->where('created_at', '>=', now()->subDays(14))->oldest()->limit(20)->get();

        return $candidates->first(fn (SocialPost $s) => self::postable($s) === null);
    }

    /** One Graph call with the Page token. @return array{0: ?\Illuminate\Http\Client\Response, 1: array} */
    private static function graph(string $path, array $params, bool $get = false): array
    {
        $token = (string) config('services.facebook_page.token');
        $params += ['access_token' => $token];
        if (filled($secret = config('services.facebook_page.app_secret'))) {
            $params['appsecret_proof'] = hash_hmac('sha256', $token, (string) $secret);
        }
        $url = 'https://graph.facebook.com/'.config('services.facebook_page.graph_version').'/'.ltrim($path, '/');
        $res = rescue(fn () => $get ? Http::timeout(25)->get($url, $params) : Http::asForm()->timeout(40)->post($url, $params), null, false);

        return [$res, $params];
    }

    private static function why($res): string
    {
        return mb_substr((string) ($res?->json('error.message') ?? ($res ? 'HTTP '.$res->status() : 'no connection')), 0, 280);
    }

    /**
     * D182: send a planned post in its own format. A card goes up as a photo, a poem as several photos in one post,
     * a reel as a reel; anything else the old way (text with a link preview). The text is the Facebook text as it is
     * (the link is its last line).
     *
     * @return array{0: bool, 1: string}
     */
    public static function send(SocialPost $s, ?int $by = null): array
    {
        $images = array_values((array) ($s->media['images'] ?? []));
        $video = $s->media['video'] ?? null;
        if ($s->format === 'link' || ($s->format === 'reel' ? ! $video : ! $images)) {
            return self::publish($s, $by);
        }
        if (! self::ready()) {
            return [false, self::configured() ? __('শুধু আসল সাইট থেকে ফেসবুকে পোস্ট হয়') : __('ফেসবুকের চাবি দেওয়া নেই')];
        }
        if ($why = self::postable($s)) {
            return [false, $why];
        }
        $page = (string) config('services.facebook_page.id');
        $text = trim((string) $s->texts['facebook']);
        $id = null;
        $res = null;
        if ($s->format === 'reel') {
            [$res] = self::graph($page.'/video_reels', ['upload_phase' => 'start']);
            $videoId = $res?->json('video_id');
            if ($videoId) {
                $up = rescue(fn () => Http::withHeaders(['Authorization' => 'OAuth '.config('services.facebook_page.token'), 'file_url' => asset(ltrim($video, '/'))])
                    ->timeout(60)->post('https://rupload.facebook.com/video-upload/'.config('services.facebook_page.graph_version').'/'.$videoId), null, false);
                if ($up && $up->successful()) {
                    [$res] = self::graph($page.'/video_reels', ['upload_phase' => 'finish', 'video_id' => $videoId, 'video_state' => 'PUBLISHED', 'description' => $text]);
                    $id = $res?->successful() ? (string) $videoId : null;
                } else {
                    $res = $up;
                }
            }
        } elseif (count($images) === 1) {
            [$res] = self::graph($page.'/photos', ['caption' => $text, 'url' => asset(ltrim($images[0], '/'))]);
            $id = $res?->json('post_id') ?? $res?->json('id');
        } else {
            $attached = [];
            foreach ($images as $i => $img) {
                [$res] = self::graph($page.'/photos', ['published' => 'false', 'url' => asset(ltrim($img, '/'))]);
                if (! ($photo = $res?->json('id'))) {
                    $attached = [];
                    break;
                }
                $attached["attached_media[$i]"] = json_encode(['media_fbid' => (string) $photo]);
            }
            if ($attached) {
                [$res] = self::graph($page.'/feed', ['message' => $text] + $attached);
                $id = $res?->json('id');
            }
        }
        if (! $id) {
            $why = self::why($res);
            $s->forceFill(['fb_error' => $why])->save();
            AuditLog::record('marketing.facebook_failed', null, ['social_post' => $s->id, 'format' => $s->format, 'error' => $why]);

            return [false, $why];
        }
        $s->forceFill(['fb_post_id' => (string) $id, 'fb_error' => null, 'fb_scheduled_at' => null])->save();
        $s->settle($by);
        AuditLog::record('marketing.facebook', null, ['social_post' => $s->id, 'fb' => (string) $id, 'format' => $s->format, 'by' => $by]);

        return [true, (string) $id];
    }

    /** Our own comment under our post (a riddle's answer the next morning). Needs the Page's permission to comment; quiet if it has none. */
    public static function comment(SocialPost $s): bool
    {
        if (! self::ready() || ! $s->fb_post_id || $s->fb_comment_id || blank($s->comment)) {
            return false;
        }
        [$res] = self::graph($s->fb_post_id.'/comments', ['message' => (string) $s->comment]);
        $id = $res?->json('id');
        $s->forceFill(['fb_comment_id' => $id ? (string) $id : 'failed', 'comment_at' => null])->save();
        if (! $id) {
            AuditLog::record('marketing.comment_failed', null, ['social_post' => $s->id, 'error' => self::why($res)]);
        }

        return (bool) $id;
    }

    /** What readers did with a post: reactions, comments, shares, and how many saw it (when the token may read that). */
    public static function numbers(SocialPost $s): array
    {
        if (! self::ready() || ! $s->fb_post_id) {
            return [];
        }
        [$res] = self::graph($s->fb_post_id, ['fields' => 'shares,comments.summary(true).limit(0),reactions.summary(true).limit(0)'], true);
        $out = [];
        if ($res?->successful()) {
            $out = ['reactions' => (int) $res->json('reactions.summary.total_count'), 'comments' => (int) $res->json('comments.summary.total_count'), 'shares' => (int) $res->json('shares.count')];
        }
        [$ins] = self::graph($s->fb_post_id.'/insights', ['metric' => 'post_impressions_unique'], true);
        if ($ins?->successful() && ($reach = $ins->json('data.0.values.0.value')) !== null) {
            $out['reach'] = (int) $reach;
        }

        return $out;
    }
}
