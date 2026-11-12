<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * D164 (owner, 2026-11-12, after the first newsletter: 4.6% hard bounces): Brevo → here. A hard bounce, an invalid or
 * blocked address, a spam complaint or an unsubscribe stops all bulk mail to that address for good, exactly as for
 * Amazon SES (D117). Brevo signs nothing, so the address carries a long secret: BREVO_WEBHOOK_TOKEN if set, else one
 * derived from the app key (shown to admins in /desk → চিঠি → পাঠানোর পথ). A wrong one is refused. Accepts one event
 * or Brevo's batched list.
 */
class BrevoWebhookController extends Controller
{
    /** Brevo event → why we stop. Soft bounces, opens, clicks and deliveries are ignored. */
    public const EVENTS = [
        'hard_bounce' => 'bounced', 'hardBounce' => 'bounced', 'invalid_email' => 'bounced', 'blocked' => 'bounced',
        'spam' => 'complaint', 'complaint' => 'complaint', 'unsubscribed' => 'complaint', 'unsubscribe' => 'complaint',
    ];

    public function __invoke(Request $request, string $token)
    {
        $secret = self::token();
        if (strlen($secret) < 20 || ! hash_equals($secret, $token)) {
            return response('refused', 403);
        }
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            return response('ok');
        }
        $events = array_is_list($body) ? $body : [$body];
        foreach (array_slice($events, 0, 200) as $e) {
            $why = self::EVENTS[(string) ($e['event'] ?? '')] ?? null;
            $email = mb_strtolower(trim((string) ($e['email'] ?? '')));
            if ($why && $email !== '') {
                SesWebhookController::suppress($email, $why, 'brevo');
            }
        }

        return response('ok');
    }

    /** The secret in the webhook's address. Changes if the app key is ever rotated (then paste the new address in Brevo). */
    public static function token(): string
    {
        $set = (string) config('mail.brevo_webhook_token');

        return $set !== '' ? $set : (config('app.key') ? substr(hash_hmac('sha256', 'brevo-webhook', (string) config('app.key')), 0, 48) : '');
    }

    /** The full address to paste in Brevo (admins only). */
    public static function address(): string
    {
        return url('/webhooks/brevo/'.self::token());
    }
}
