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

    public function verifyUrl(): string { return url('/certificate/'.$this->code.'/'); }
}
