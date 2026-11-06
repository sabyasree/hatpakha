<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A writing contest (D53). Open = shown on the submit form between starts_at and ends_at. */
class Contest extends Model
{
    protected $guarded = ['id'];

    public const STATUS = ['draft' => 'খসড়া', 'open' => 'চলছে', 'judging' => 'বিচার চলছে', 'done' => 'ফল প্রকাশিত'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'shortlist' => 'array', 'winners' => 'array'];
    }

    public function entries(): HasMany { return $this->hasMany(Post::class); }

    public function section(): BelongsTo { return $this->belongsTo(Section::class); }

    public static function openNow()
    {
        return static::where('status', 'open')->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
