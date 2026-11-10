<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A writer's milestone (D78): found from real data, shown on the home page only after the owner approves. */
class Milestone extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reached_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function author(): BelongsTo { return $this->belongsTo(Author::class); }

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }
}
