<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** D132: a notification the owner sends from the desk to every phone (or the daily-note phones). */
class PushAnnouncement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
