<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Plain-text reader comment. Always escaped on output. */
class Comment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['feature_opt_out' => 'boolean']; }

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /**
     * D75: no links in comments (owner). Catches plain and disguised addresses: http(s)://, www.,
     * "site.com", "site dot com", "site[.]com", "ডট কম", and e-mail addresses. Old comments are kept as they were.
     */
    public static function hasLink(string $text): bool
    {
        $t = mb_strtolower($text);
        $tld = '(com|net|org|info|in|co|io|xyz|bd|me|biz|site|online|shop|store|blog|app|link|live|top|club|tk|ru|us|uk|pk|lk|np|ly|gl|gg|to)';
        $dot = '(\.|\s+\.\s*|\s*\[\s*\.\s*\]\s*|\s*\(\s*\.\s*\)\s*|\s+dot\s+|\s*\[dot\]\s*|\s*\(dot\)\s*)';

        return (bool) preg_match('~(https?\s*:\s*/\s*/|\bwww\s*\.|\bwww\s+dot\b|ডট\s*কম|ডট\s*ইন|ডট\s*নেট)~u', $t)
            || (bool) preg_match('~[a-z0-9][a-z0-9-]*'.$dot.$tld.'(?![a-z])~u', $t)
            || (bool) preg_match('~[a-z0-9._%+-]+\s*(@|\[at\]|\(at\)|\s+at\s+)\s*[a-z0-9-]+'.$dot.'[a-z]{2,}~u', $t);
    }

    /** Approved comments a signed-in reader needs before theirs appear without a check (D39). */
    public const TRUST_AFTER = 2;

    /** Guests: always checked. Signed-in: checked until trusted. Spam-flagged accounts: always checked. */
    public static function statusFor(?User $user): string
    {
        if (! $user || filled($user->spam_reason) || (filled($user->bot_reasons) && $user->bot_review !== 'human')) {   // D121
            return 'pending';
        }

        return static::where('user_id', $user->id)->where('status', 'approved')->count() >= self::TRUST_AFTER ? 'approved' : 'pending';
    }
}
