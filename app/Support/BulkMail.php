<?php

namespace App\Support;

/**
 * D117: which mailer carries bulk mail (newsletters, the relaunch letters, new-episode and new-from-writer
 * notes). Amazon SES when MAIL_BULK_HOST is set; otherwise the normal mailer, as before. Account mail
 * (verification, password reset, codes, editor's decisions) always uses the normal mailer.
 */
final class BulkMail
{
    public static function mailer(): string
    {
        return filled(config('mail.mailers.bulk.host')) ? 'bulk' : (string) config('mail.default');
    }

    /** Whether bulk mail really leaves the server (not the "log" or "array" test mailers). */
    public static function real(): bool
    {
        return ! in_array(self::mailer(), ['log', 'array'], true);
    }

    /** How many more bulk e-mails may go now under MAIL_DAILY_LIMIT (null = no limit). */
    public static function remainingToday(): ?int
    {
        $limit = (int) config('mail.daily_limit');
        if ($limit <= 0) {
            return null;
        }
        $used = \App\Models\NewsletterDelivery::where('sent_at', '>=', now()->subDay())->count();

        return max(0, $limit - $used);
    }
}
