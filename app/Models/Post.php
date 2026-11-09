<?php

namespace App\Models;

use App\Support\BodyFormatter;
use App\Support\HtmlSanitizer;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'pending', 'changes', 'confirm', 'scheduled', 'published', 'rejected', 'archived'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'submitted_at' => 'datetime',
            'noindex' => 'boolean',
            'audit_reasons' => 'array',
            'audit_decided_at' => 'datetime',
            'edited_in_desk_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'originality_confirmed_at' => 'datetime',
            'pending_edits' => 'array',
            'ai_drafted_at' => 'datetime',
            'glossary' => 'array',
            'audio_ai' => 'boolean',
            'relaunch_answered_at' => 'datetime',
            'relaunch_elsewhere' => 'boolean',
            'relaunch_worth_by_owner' => 'boolean',
            'no_ads' => 'boolean',
            'image_is_ai' => 'boolean',
            'related_ids' => 'array',
            'seo_proposal' => 'array',
            'seo_done_at' => 'datetime',
            'proof' => 'array',
            'proof_at' => 'datetime',
            'plagiarism' => 'array',
            'plagiarism_at' => 'datetime',
        ];
    }

    /** Only the WordPress import may move an old submission straight to published (D104). */
    public static bool $allowLegacyPublish = false;

    /** An old WordPress submission (before the relaunch) that nobody answered. */
    public function isLegacyPending(): bool
    {
        return $this->getOriginal('status') === 'pending' && $this->submitted_by === null && $this->legacy_wp_id !== null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $post) {
            // D104 (owner): the old unanswered submissions are never published unless their writer said yes to the apology letter.
            if ($post->exists && $post->isDirty('status') && in_array($post->status, ['published', 'scheduled'], true)
                && $post->isLegacyPending() && $post->relaunch_status !== 'yes' && ! self::$allowLegacyPublish) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'এটি পুরনো জমা লেখা। লেখক চিঠির উত্তরে রাজি না হলে প্রকাশ করা যাবে না।',
                ]);
            }
            // Security: body is sanitised on every save, whatever the source (docs/SECURITY.md §4).
            if ($post->isDirty('body_html')) {
                // Then tidy structure only (never words): docs/DECISIONS.md D23.
                $post->body_html = BodyFormatter::format(HtmlSanitizer::clean($post->body_html), $post->format ?: 'prose', $post->title);
                $post->reading_minutes = self::estimateMinutes($post->body_html);
            }
            if ($post->isDirty('slug')) {
                $post->slug = Slug::normalize($post->slug);
            }
        });
        // Never break an old URL (CLAUDE.md §2): renaming a live post leaves a 301 behind.
        static::updated(function (self $post) {
            // D108: publishing the first episode of a proposed serial starts the serial.
            if ($post->wasChanged('status') && in_array($post->status, ['published', 'scheduled'], true) && $post->serial_id && $post->serial?->status === 'proposed') {
                $post->serial->activate();
            }
            if ($post->wasChanged('slug') && $post->getOriginal('status') === 'published' && filled($post->getOriginal('slug'))) {
                $from = '/'.$post->getOriginal('slug').'/';
                $to = '/'.$post->slug.'/';
                Redirect::where('from_path', $to)->delete();
                Redirect::updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301]);
                AuditLog::record('post.slug_changed', $post, ['from' => $from, 'to' => $to]);
            }
        });
    }

    public static function estimateMinutes(string $html): int
    {
        $words = count(preg_split('/\s+/u', trim(strip_tags($html)), -1, PREG_SPLIT_NO_EMPTY));

        return max(1, (int) round($words / 180));
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)->withPivot('position')->orderByPivot('position');
    }

    public function aiReviews(): HasMany { return $this->hasMany(AiReview::class)->latest(); }

    public function latestAiReview(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(AiReview::class)->whereNull('blind_run')->latestOfMany(); }

    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function section(): BelongsTo { return $this->belongsTo(Section::class); }

    public function issue(): BelongsTo { return $this->belongsTo(Issue::class); }

    public function tags(): BelongsToMany { return $this->belongsToMany(Tag::class); }

    public function moods(): BelongsToMany { return $this->belongsToMany(Mood::class); }

    public function comments(): HasMany { return $this->hasMany(Comment::class); }

    /** Same rule as scopePublished, for one loaded post. */
    public function isLive(): bool
    {
        return in_array($this->status, ['published', 'scheduled'], true) && $this->published_at !== null && $this->published_at->lte(now());
    }

    /** Live for readers: published, or scheduled whose time has come (no cron needed; 3c). */
    public function scopePublished(Builder $q): Builder
    {
        return $q->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now());
    }

    public function isPoem(): bool { return $this->format === 'poem'; }

    /** "কবিতা", "গল্প" or "লেখা", for labels such as "আজকের …" (owner, 2026-11-09). */
    public function kindLabel(): string
    {
        return $this->isPoem() ? 'কবিতা' : ($this->section?->slug === 'short-stories' ? 'গল্প' : 'লেখা');
    }

    public function serial(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Serial::class); }

    /** D108: the episode before / after this one (published only). */
    public function episodeSibling(int $step): ?self
    {
        return $this->serial_id ? self::published()->where('serial_id', $this->serial_id)->where('episode_no', $this->episode_no + $step)->first() : null;
    }

    /** "পর্ব ৩: ভাঙা আয়না" from the stored "Serial (পর্ব ৩): ভাঙা আয়না". */
    public function episodeLabel(): string
    {
        $own = str_contains((string) $this->title, '): ') ? trim(\Illuminate\Support\Str::after($this->title, '): ')) : (string) $this->title;

        return 'পর্ব '.\App\Support\Bn::num((string) $this->episode_no).($own !== '' ? ': '.$own : '');
    }

    public function isLastEpisode(): bool
    {
        return $this->serial && ($this->serial->status === 'finished' && $this->episode_no >= (int) $this->serial->episodes()->max('episode_no'));
    }

    /** D101: a piece in ছোটদের হাতপাখা (no comments, no ads, age badge, glossary). */
    public function isKids(): bool { return $this->section?->slug === \App\Models\Section::KIDS; }

    public function ageLabel(): ?string { return \App\Ai\KidsTeacher::AGES[$this->age_band] ?? null; }

    /** Glossary rows that are complete (word plus at least one meaning). */
    public function glossaryRows(): array
    {
        return array_values(array_filter((array) $this->glossary, fn ($g) => is_array($g) && filled($g['word'] ?? null) && (filled($g['bn'] ?? null) || filled($g['en'] ?? null))));
    }

    public function url(): string { return \App\Support\Url::to('/'.Slug::encode($this->slug)); }

    /** Cards show the photo only for large images; otherwise the title itself is the card (title-card). */
    /**
     * Imported poems often have one <p> per LINE, which the poem style spaces like stanzas. When most paragraphs
     * are single lines, the page uses tight spacing (mobile UX review).
     */
    public function poemLinesAreParagraphs(): bool
    {
        $html = (string) $this->body_html;
        $p = substr_count(strtolower($html), '<p');
        $br = preg_match_all('~<br\s*/?>~i', $html);

        return $p >= 4 && $br < $p / 3;
    }

    public function hasCardImage(): bool
    {
        return $this->featured_image_path && ($this->featured_image_width ?? 0) >= 600;   // cards are ~300px wide: sharp at 2x
    }

    public function metaTitle(): string { return $this->seo_title ?: $this->title; }

    public function metaDescription(): string
    {
        if ($this->seo_description ?: $this->excerpt) {
            return $this->seo_description ?: $this->excerpt;
        }
        // Lines of a poem stay apart ("… কাজে / সারাটা দিন …"), never glued together (D88).
        $html = preg_replace(['~<br\s*/?>~i', '~</(p|div|li|h[1-6]|blockquote)>~i'], [' / ', ' '], (string) $this->body_html);
        $text = trim(preg_replace(['/\s+/u', '~(\s*/\s*)+~u'], [' ', ' / '], html_entity_decode(strip_tags($html))), " /\t\n");

        if ($text === '') {
            // A piece that is only a picture (an early scanned page, a painting) still gets a real description.
            $by = $this->authors->reject(fn ($a) => $a->is_desk)->map(fn ($a) => $a->publicName())->filter()->implode(', ');

            return '“'.$this->title.'”'.($by ? ', '.$by : '').' · '.($this->section?->name_bn ?? 'লেখা').', হাতপাখা';
        }

        return mb_strlen($text) > 155 ? rtrim(mb_substr($text, 0, 154), " /,।").'…' : $text;
    }

    /** Title for social previews: the piece and its writer, as on the page (D88). */
    public function shareTitle(): string
    {
        $title = $this->metaTitle();
        $by = $this->authors->reject(fn ($a) => $a->is_desk)->map(fn ($a) => $a->publicName())->filter()->implode(', ');

        // A search title that already names the writer ("লেখা | লেখক") is used as it is.
        return $by && ! str_contains($title, '|') && ! str_contains($title, $by) ? $title.' | '.$by : $title;
    }

    /** Share/og image rule (docs/SEO.md §2): a cover at least 600px wide (Facebook's large-preview minimum) is cropped into the share card. */
    public function hasLargeImage(): bool
    {
        return $this->featured_image_path && ($this->featured_image_width ?? 0) >= 600;
    }
}
