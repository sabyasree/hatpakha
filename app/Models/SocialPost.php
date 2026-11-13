<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A ready-to-post marketing draft (D51). Plain text only; printed escaped. */
class SocialPost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['texts' => 'array', 'posted_at' => 'datetime', 'fb_scheduled_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * D170 (owner: "if I share to Facebook, the post goes away and I can't share it on Instagram"): a post leaves the
     * ready list by itself only when every channel set up for it is done: Facebook posted (or not set up, or no
     * Facebook text) and Instagram posted (or not set up, no Instagram text, or no picture it can take).
     * "পোস্ট করেছি" still moves it at once.
     */
    public function settle(?int $by = null): void
    {
        $fbDone = $this->fb_post_id || ! \App\Support\FacebookPage::configured() || blank($this->texts['facebook'] ?? null);
        $igDone = $this->ig_media_id || ! \App\Support\InstagramPage::configured() || blank($this->texts['instagram'] ?? null)
            || (! \App\Support\InstagramPage::picture($this) && ! \App\Filament\Pages\Marketing::card($this));
        if ($fbDone && $igDone && ($this->status ?? 'ready') === 'ready') {   // a new row's status is the database default
            $this->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $by])->save();
        }
    }

    /** Extra lines for quote cards (D170), each copied exactly from the piece. */
    public function quotes(): array
    {
        return array_values(array_filter(array_merge([(string) ($this->texts['quote'] ?? '')], (array) ($this->texts['quotes'] ?? [])), fn ($q) => is_string($q) && $q !== ''));
    }

    public static function hasFor(Model $subject, string $kind): bool
    {
        return static::where('kind', $kind)->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())->exists();
    }
}
