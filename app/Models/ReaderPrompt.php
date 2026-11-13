<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** D175: one week's theme in পাঠকের কলম. */
class ReaderPrompt extends Model
{
    protected $guarded = ['id'];

    public const KINDS = ['six' => 'ছয় শব্দের গল্প', 'letter' => 'না-পাঠানো চিঠি', 'memory' => 'এক লাইনের স্মৃতি'];

    public const STATUSES = ['open' => 'লেখা নেওয়া চলছে', 'closed' => 'বাছাই চলছে', 'published' => 'প্রকাশিত'];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function entries(): HasMany { return $this->hasMany(ReaderEntry::class); }

    public function picked(): HasMany { return $this->entries()->where('status', 'picked')->orderByDesc('ai_score')->orderBy('id'); }

    public function url(): string { return \App\Support\Url::to('/kolom/'.$this->slug); }

    public function isOpen(): bool { return $this->status === 'open' && (! $this->closes_at || $this->closes_at->isFuture()); }

    public static function current(): ?self
    {
        return static::where('status', 'open')->latest('id')->first();
    }
}
