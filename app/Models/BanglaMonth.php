<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** D303: one West Bengal month's first day (shown only once checked). */
class BanglaMonth extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'sources' => 'array'];
    }
}
