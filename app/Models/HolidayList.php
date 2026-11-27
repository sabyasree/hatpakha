<?php

namespace App\Models;

use App\Support\Url;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** D314: one government's holiday list for one year. */
class HolidayList extends Model
{
    protected $guarded = ['id'];

    public const COUNTRIES = ['west-bengal' => ['পশ্চিমবঙ্গ', 'West Bengal'], 'bangladesh' => ['বাংলাদেশ', 'Bangladesh']];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class)->orderBy('day');
    }

    /** Shown once the notice is confirmed and at least ten of its dates are checked. */
    public function isLive(): bool
    {
        return $this->status === 'verified' && $this->holidays()->where('status', 'verified')->count() >= 10;
    }

    public function url(): string
    {
        return Url::to('/janun/holidays/'.$this->country.'-'.$this->year);
    }

    public function name(): string
    {
        return self::COUNTRIES[$this->country][0] ?? $this->country;
    }
}
