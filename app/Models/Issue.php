<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['published_at' => 'datetime', 'ai_plan' => 'array']; }

    public function editorial(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Post::class, 'editorial_post_id'); }

    public function posts(): HasMany { return $this->hasMany(Post::class); }

    public function url(): string { return \App\Support\Url::to('/sonkha/'.$this->slug); }
}
