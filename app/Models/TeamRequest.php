<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A piece of work the owner gave the AI team by hand (D72). */
class TeamRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['redo' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}
