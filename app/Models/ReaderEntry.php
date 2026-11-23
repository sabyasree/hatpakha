<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** D175: one reader's answer to the week's theme. Plain text; shown only once picked and the owner publishes. */
class ReaderEntry extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['new' => 'নতুন', 'ok' => 'এআই দেখেছে: ঠিক আছে', 'flagged' => 'এআই-এর আপত্তি', 'picked' => 'বাছাই', 'rejected' => 'বাদ'];

    protected function casts(): array
    {
        return ['anonymous' => 'boolean', 'ai_score' => 'integer'];
    }

    public function prompt(): BelongsTo { return $this->belongsTo(ReaderPrompt::class, 'reader_prompt_id'); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /**
     * D266 (UX review: long letters): the first few lines shown at once, the rest behind "পুরোটা পড়ুন" (still in the
     * page for search engines and screen readers). @return array{0: string, 1: ?string}
     */
    public function parts(int $lines = 6, int $chars = 360): array
    {
        $body = trim((string) $this->body);
        $all = preg_split('/\R/u', $body);
        if (count($all) > $lines + 1) {
            return [rtrim(implode("\n", array_slice($all, 0, $lines))), ltrim(implode("\n", array_slice($all, $lines)))];
        }
        if (mb_strlen($body) > $chars + 80) {
            $cut = mb_strrpos(mb_substr($body, 0, $chars), '।') ?: mb_strrpos(mb_substr($body, 0, $chars), ' ') ?: $chars;

            return [rtrim(mb_substr($body, 0, $cut + 1)), ltrim(mb_substr($body, $cut + 1))];
        }

        return [$body, null];
    }

    public function shownName(): string { return $this->anonymous ? 'নাম প্রকাশে অনিচ্ছুক' : $this->name; }
}
