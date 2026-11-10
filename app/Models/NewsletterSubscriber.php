<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Newsletter list. Mailable = explicitly consented and not suppressed. Bulk email needs the owner (CLAUDE.md §3). */
class NewsletterSubscriber extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['suppressed' => 'boolean', 'consented_at' => 'datetime'];
    }

    public function scopeMailable(Builder $q): Builder
    {
        return $q->where('suppressed', false)->whereNotNull('consented_at')->where('status', 'subscribed');
    }
}
