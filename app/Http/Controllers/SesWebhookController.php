<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * D117: Amazon SES → SNS → here. A hard bounce (address doesn't exist) or a spam complaint stops all bulk
 * mail to that address for good, so hatpakha.com keeps a good sender name. Every message is checked:
 * it must come from our own SNS topic (SES_SNS_TOPIC_ARN) and carry Amazon's valid signature, made with a
 * certificate fetched only from an Amazon SNS host. Anything else is refused.
 */
class SesWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $m = json_decode($request->getContent(), true);
        $topic = (string) config('mail.ses_topic_arn');
        if (! is_array($m) || $topic === '' || ($m['TopicArn'] ?? null) !== $topic || ! self::verified($m)) {
            return response('refused', 403);
        }

        if (($m['Type'] ?? '') === 'SubscriptionConfirmation') {
            // Confirm the subscription once; the URL must be Amazon's own SNS host.
            if (self::snsHost((string) ($m['SubscribeURL'] ?? ''))) {
                Http::timeout(10)->get($m['SubscribeURL']);
                AuditLog::record('ses.subscribed', null, ['topic' => $topic]);
            }

            return response('ok');
        }
        if (($m['Type'] ?? '') !== 'Notification') {
            return response('ok');
        }

        $n = json_decode((string) ($m['Message'] ?? ''), true) ?: [];
        $type = $n['notificationType'] ?? $n['eventType'] ?? null;
        $emails = [];
        if ($type === 'Bounce' && ($n['bounce']['bounceType'] ?? '') === 'Permanent') {
            $emails = array_column((array) ($n['bounce']['bouncedRecipients'] ?? []), 'emailAddress');
            $why = 'bounced';
        } elseif ($type === 'Complaint') {
            $emails = array_column((array) ($n['complaint']['complainedRecipients'] ?? []), 'emailAddress');
            $why = 'complaint';
        }
        foreach (array_slice($emails, 0, 50) as $email) {
            self::suppress(mb_strtolower(trim((string) $email)), $why);
        }

        return response('ok');
    }

    /** Never mail this address again in bulk. Account mail (password reset) still works for a real user. */
    public static function suppress(string $email, string $why): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        NewsletterSubscriber::whereRaw('LOWER(email) = ?', [$email])->update(['suppressed' => true, 'status' => $why === 'complaint' ? 'unsubscribed' : 'bounced']);
        User::whereRaw('LOWER(email) = ?', [$email])->update(['mail_opt_out' => true]);
        NewsletterDelivery::whereRaw('LOWER(email) = ?', [$email])->whereNull('bounce')->latest('id')->limit(1)->update(['bounce' => $why]);
        AuditLog::record('ses.'.$why, null, ['email_hash' => substr(hash('sha256', $email), 0, 16)]);
    }

    public static function snsHost(string $url): bool
    {
        $p = parse_url($url);

        return ($p['scheme'] ?? '') === 'https' && (bool) preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com$/', (string) ($p['host'] ?? ''));
    }

    /** Amazon's SNS message signature (SignatureVersion 1 = SHA1, 2 = SHA256). */
    public static function verified(array $m): bool
    {
        $certUrl = (string) ($m['SigningCertURL'] ?? '');
        if (! self::snsHost($certUrl) || ! str_ends_with((string) parse_url($certUrl, PHP_URL_PATH), '.pem')) {
            return false;
        }
        $keys = ($m['Type'] ?? '') === 'Notification'
            ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        $text = '';
        foreach ($keys as $k) {
            if (isset($m[$k])) {
                $text .= $k."\n".$m[$k]."\n";
            }
        }
        $pem = Cache::remember('sns.cert.'.sha1($certUrl), 86400, fn () => rescue(fn () => Http::timeout(10)->get($certUrl)->body(), '', false));
        $key = $pem ? openssl_pkey_get_public($pem) : false;
        if (! $key) {
            return false;
        }

        return openssl_verify($text, base64_decode((string) ($m['Signature'] ?? ''), true) ?: '', $key,
            ($m['SignatureVersion'] ?? '1') === '2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1) === 1;
    }
}
