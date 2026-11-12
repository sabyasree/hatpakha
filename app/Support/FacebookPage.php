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
        $s->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $by, 'fb_post_id' => (string) $id, 'fb_error' => null, 'fb_scheduled_at' => null])->save();
        AuditLog::record('marketing.facebook', null, ['social_post' => $s->id, 'fb' => (string) $id, 'by' => $by]);

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
            $n += self::publish($s)[0] ? 1 : 0;
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

        return $s && self::publish($s)[0] ? $s : null;   // one per run
    }

    /** Which post automatic mode sends next (shown to the owner on the marketing page). */
    public static function nextAuto(): ?SocialPost
    {
        $candidates = SocialPost::with('subject')->where('kind', '!=', 'weekly')->where('status', 'ready')->whereNull('fb_post_id')
            ->whereNull('fb_scheduled_at')->whereNull('fb_error')->where('created_at', '>=', now()->subDays(14))->oldest()->limit(20)->get();
        return $candidates->first(fn (SocialPost $s) => self::postable($s) === null);
    }
}
