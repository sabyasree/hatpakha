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
                ['title_snapshot' => mb_substr($post->title, 0, 400), 'author_name_snapshot' => $author->publicName(), 'issue_label' => $post->issue?->title]);
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
            $user->notify(new \App\Notifications\WriterNote('হাতপাখা: আপনার লেখার সনদ',
                'নমস্কার '.$this->author_name_snapshot.', হাতপাখায় প্রকাশিত আপনার লেখা "'.$this->title_snapshot.'"-এর সনদ তৈরি। '
                .'নিচের লিংক খুলে ছবি (PNG) বা PDF হিসেবে নামাতে পারবেন, বন্ধুদের সঙ্গে শেয়ারও করতে পারেন। আপনার অ্যাকাউন্টের "আমার লেখা"-তেও সব সনদ থাকে।',
                $this->verifyUrl(), 'সনদ দেখুন ও নামান'));
        }
    }
}
