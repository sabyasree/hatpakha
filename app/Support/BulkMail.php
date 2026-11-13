<?php

namespace App\Support;

use App\Models\Setting;

/**
 * D117/D129: which service carries bulk mail (newsletters, the relaunch letters, new-episode and new-from-writer
 * notes): Amazon SES (MAIL_BULK_*) or Brevo (MAIL_BREVO_*), whichever the owner picked in /desk → চিঠি →
 * "পাঠানোর পথ"; with only one set up, that one; with neither, the normal mailer, as before.
 * Account mail (verification, password reset, codes, editor's decisions) always uses the normal mailer.
 */
final class BulkMail
{
    /** provider => [label, mailer name] */
    public const PROVIDERS = ['ses' => ['Amazon SES', 'bulk'], 'brevo' => ['Brevo', 'brevo']];

    /** Services with settings in ENV_FILE. */
    public static function available(): array
    {
        return array_keys(array_filter(self::PROVIDERS, fn ($p) => filled(config('mail.mailers.'.$p[1].'.host'))));
    }

    /** The service letters go through now (null = the normal mailer). */
    public static function provider(): ?string
    {
        $available = self::available();
        $chosen = Setting::get('mail.bulk_provider');

        return in_array($chosen, $available, true) ? $chosen : ($available[0] ?? null);
    }

    public static function choose(string $provider, ?int $by): void
    {
        abort_unless(in_array($provider, self::available(), true), 422);
        Setting::put('mail.bulk_provider', $provider);
        \App\Models\AuditLog::record('mail.provider', null, ['provider' => $provider, 'by' => $by]);
    }

    public static function label(?string $provider = null): string
    {
        $provider ??= self::provider();

        return $provider ? self::PROVIDERS[$provider][0] : 'Hostinger';
    }

    public static function mailer(): string
    {
        $p = self::provider();

        return $p ? self::PROVIDERS[$p][1] : (string) config('mail.default');
    }

    /** Whether bulk mail really leaves the server (not the "log" or "array" test mailers). */
    public static function real(): bool
    {
        return MailGuard::live() && ! in_array(self::mailer(), ['log', 'array'], true);   // never on staging
    }

    /** The day's limit of a service (0 = none): MAIL_DAILY_LIMIT for Amazon SES, MAIL_BREVO_DAILY_LIMIT for Brevo. */
    public static function limit(?string $provider = null): int
    {
        $provider ??= self::provider();

        return (int) ($provider === 'brevo' ? config('mail.brevo_daily_limit') : config('mail.daily_limit'));
    }

    /** How many more letters may go through this service in the last 24 hours (null = no limit). */
    public static function remainingToday(?string $provider = null): ?int
    {
        $provider ??= self::provider();
        $limit = self::limit($provider);
        if ($limit <= 0) {
            return self::monthLeft($provider);
        }
        $via = fn ($q) => $q->when($provider === 'brevo', fn ($q) => $q->where('via', 'brevo'), fn ($q) => $q->where(fn ($w) => $w->whereNull('via')->orWhere('via', '!=', 'brevo')));
        $used = $via(\App\Models\NewsletterDelivery::where('sent_at', '>=', now()->subDay()))->count();
        // D190: the month's allowance too (Brevo's plan counts by the calendar month), whichever is nearer.
        if (($month = self::monthLeft($provider)) !== null) {
            return max(0, min($limit - $used, $month));
        }

        return max(0, $limit - $used);
    }

    /** Letters still allowed this calendar month through Brevo (null = no monthly limit set: MAIL_BREVO_MONTHLY_LIMIT). */
    public static function monthLeft(?string $provider = null): ?int
    {
        $provider ??= self::provider();
        $limit = $provider === 'brevo' ? (int) config('mail.brevo_monthly_limit') : 0;
        if ($limit <= 0) {
            return null;
        }

        return max(0, $limit - \App\Models\NewsletterDelivery::where('via', 'brevo')->where('sent_at', '>=', now('Asia/Kolkata')->startOfMonth()->utc())->count());
    }
}
