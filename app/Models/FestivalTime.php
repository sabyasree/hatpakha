<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** D302: one tithi or puja time of one year's festival (stored in UTC, shown in the reader's time zone). */
class FestivalTime extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sources' => 'array', 'day' => 'date', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** Verified and not yet over (a day after it ends, so a reader on the day still sees it). */
    public function scopeUpcoming($q)
    {
        return $q->where('status', 'verified')->where(fn ($w) => $w->where('ends_at', '>=', now()->subDay())
            ->orWhere(fn ($d) => $d->whereNull('ends_at')->where('day', '>=', now()->subDay()->toDateString())));
    }
}
