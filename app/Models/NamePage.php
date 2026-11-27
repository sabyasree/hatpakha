<?php

namespace App\Models;

use App\Support\NamePlan;
use App\Support\Url;
use Illuminate\Database\Eloquent\Model;

/** D316: one letter's boy or girl names. */
class NamePage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['names' => 'array', 'publish_at' => 'datetime', 'checked_at' => 'datetime'];
    }

    public function isLive(): bool
    {
        return $this->status === 'live' && isset(NamePlan::LETTERS[$this->letter], NamePlan::GENDERS[$this->gender]);
    }

    public function url(): string
    {
        return Url::to('/janun/names/'.NamePlan::slug($this->gender, $this->letter));
    }

    public function title(): string
    {
        return NamePlan::title($this->gender, $this->letter);
    }

    /** The names that passed, in Bengali alphabetical order. */
    public function checked(): array
    {
        return collect((array) $this->names)->where('status', 'ok')->sortBy('bn')->values()->all();
    }
}
