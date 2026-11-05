<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['is_controlled' => 'boolean']; }

    public function posts(): BelongsToMany { return $this->belongsToMany(Post::class); }

    public function url(): string { return url('/tag/'.rawurlencode($this->slug).'/'); }
}
