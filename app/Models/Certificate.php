<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Author certificate with a public verification page (PLAN.md §3 "Author certificates"). */
class Certificate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['issued_at' => 'datetime', 'revoked_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            // Unambiguous characters only (no 0/O/1/I), easy to type from a printed copy.
            $c->code ??= Str::upper(Str::of(Str::random(40))->replaceMatches('/[^A-HJ-NP-Z2-9]/i', '')->substr(0, 10));
        });
    }

    public function author(): BelongsTo { return $this->belongsTo(Author::class); }

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }

    public function isValid(): bool { return $this->revoked_at === null; }

    /** The four designs (D73): label, what it is for. The sheet picks the colours. */
    public const THEMES = [
        'publication' => ['প্রকাশনা শংসাপত্র', 'হাতপাখায় প্রকাশিত প্রতিটি লেখার জন্য'],
        'issue' => ['সংখ্যায় প্রকাশের শংসাপত্র', 'কোনো সংখ্যায় (মাসিক বা উৎসব সংখ্যা) প্রকাশিত লেখার জন্য'],
        'contest' => ['প্রতিযোগিতার শংসাপত্র', 'প্রতিযোগিতার বিজয়ী ও বিশেষ উল্লেখের জন্য'],
        'honour' => ['সম্মাননা শংসাপত্র', 'মাসের লেখক বা অন্য সম্মানের জন্য'],
    ];

    public const PLACES = [1 => 'প্রথম', 2 => 'দ্বিতীয়', 3 => 'তৃতীয়', 9 => 'বিশেষ উল্লেখ'];

    /** Which design this certificate uses; decided when shown, so it follows the piece (e.g. later put in an issue). */
    public function theme(): string
    {
        return match (true) {
            $this->kind === 'contest' => 'contest',
            $this->kind === 'honour' => 'honour',
            (bool) $this->issueTitle() => 'issue',
            default => 'publication',
        };
    }

    public function issueTitle(): ?string
    {
        if ($this->kind !== 'publication') {
            return null;
        }
        $issue = $this->post?->issue;

        return $issue && $issue->status === 'published' ? $issue->title : ($this->issue_label ?: null);
    }

    /** Contest: [contest title, place label]; older rows kept both in issue_label ("title · প্রথম"). */
    public function contestParts(): array
    {
        $label = (string) $this->issue_label;
        if ($this->place) {
            return [$label, self::PLACES[$this->place] ?? ''];
        }
        $at = mb_strrpos($label, ' · ');

        return $at === false ? [$label, ''] : [mb_substr($label, 0, $at), mb_substr($label, $at + 3)];
    }

    /** The check link as a QR code (inline SVG, navy). chillerlan/php-qrcode comes with Filament (D73). */
    public function qrSvg(int $size = 150): string
    {
        $options = new \chillerlan\QRCode\QROptions([
            'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class, 'outputBase64' => false, 'svgAddXmlHeader' => false,
            'drawLightModules' => false, 'connectPaths' => true, 'addQuietzone' => false,
        ]);
        $svg = (new \chillerlan\QRCode\QRCode($options))->render($this->verifyUrl());

        return str_replace(['fill="#000"', '<svg '], ['fill="#13212C"', '<svg width="'.$size.'" height="'.$size.'" '], $svg);
    }

    /** Who signs (settings on /desk → শংসাপত্র): name, title and an optional signature picture. */
    public static function signer(): array
    {
        return [
            'name' => Setting::get('certificates.signer_name') ?: null,
            'title' => Setting::get('certificates.signer_title') ?: 'সম্পাদক, হাতপাখা',
            'image' => Setting::get('certificates.signature') ?: null,
        ];
    }

    /** Automatic certificates (D73): every piece published since the switch was set, once, writers told. */
    public static function autoIssue(int $limit = 20): int
    {
        if (Setting::get('certificates.auto') !== '1' || ! ($since = Setting::get('certificates.auto_since'))) {
            return 0;
        }
        $have = self::where('kind', 'publication')->select('post_id');
        $n = 0;
        foreach (Post::published()->where('published_at', '>=', \Illuminate\Support\Carbon::parse($since))->whereNotIn('id', $have)
            ->whereHas('authors', fn ($q) => $q->where('is_desk', false))->oldest('published_at')->limit($limit)->get() as $post) {
            $n += count(self::issueFor($post));
        }

        return $n;
    }

    public function verifyUrl(): string { return \App\Support\Url::to('/certificate/'.$this->code); }

    /**
     * Publication certificates for a published piece, one per writer (D56, D71). Only on a person's click.
     * Writers with an account get one short e-mail with the link (when real mail is on). Returns the new ones.
     *
     * @return list<self>
     */
    public static function issueFor(Post $post, bool $tell = true): array
    {
        if (! $post->isLive()) {
            return [];
        }
        $new = [];
        foreach ($post->loadMissing('authors', 'issue')->authors as $author) {
            if ($author->is_desk) {
                continue;   // no certificate for the desk byline
            }
            $c = self::firstOrCreate(['author_id' => $author->id, 'post_id' => $post->id, 'kind' => 'publication'],
                ['title_snapshot' => mb_substr($post->title, 0, 400), 'author_name_snapshot' => $author->publicName(), 'issue_label' => $post->issue?->status === 'published' ? $post->issue->title : null]);
            if ($c->wasRecentlyCreated) {
                $new[] = $c;
                if ($tell) {
                    $c->tellWriter();
                }
            }
        }

        return $new;
    }

    public function tellWriter(): void
    {
        $user = $this->author?->user;
        if ($user && $user->email_verified_at && \App\Support\NewsletterSender::realMail()) {
            $user->notify(new \App\Notifications\TemplateMail('certificate_ready',
                ['{নাম}' => (string) $this->author_name_snapshot, '{লেখা}' => (string) $this->title_snapshot], $this->verifyUrl()));   // D123
        }
    }
}
