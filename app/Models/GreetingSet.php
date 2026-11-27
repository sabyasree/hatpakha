<?php

namespace App\Models;

use App\Support\GreetingPlan;
use App\Support\Url;
use Illuminate\Database\Eloquent\Model;

/** D315: one occasion's greetings. Live only after the check (own lines) and, for the poet's line, two pages letter for letter. */
class GreetingSet extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['body' => 'array', 'check' => 'array', 'checked_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function isLive(): bool
    {
        return $this->status === 'live' && isset(GreetingPlan::OCCASIONS[$this->slug]);
    }

    public function url(): string
    {
        return Url::to('/janun/greetings/'.$this->slug);
    }

    public function name(): string
    {
        return GreetingPlan::OCCASIONS[$this->slug][0] ?? $this->slug;
    }

    /** @return array<int, array{bn: string, en: string}> */
    public function lines(): array
    {
        return collect((array) ($this->body['groups'] ?? []))->flatMap(fn ($g) => (array) ($g['lines'] ?? []))->values()->all();
    }
}
