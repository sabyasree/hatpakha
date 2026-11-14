<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** D191: one kind of error the site ran into (App\Support\SiteErrors). */
class SiteError extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_at' => 'datetime', 'last_at' => 'datetime', 'seen_at' => 'datetime'];
    }
}
