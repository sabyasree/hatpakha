<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** D314: one day of a holiday list (shown only when checked on two pages). */
class Holiday extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['day' => 'date', 'moon' => 'boolean', 'sources' => 'array'];
    }
}
