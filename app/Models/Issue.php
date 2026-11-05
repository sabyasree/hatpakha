<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['published_at' => 'datetime']; }

    public function posts(): HasMany { return $this->hasMany(Post::class); }

    public function url(): string { return url('/sonkha/'.$this->slug.'/'); }
}
