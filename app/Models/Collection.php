<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A themed collection or yearly anthology list (D55). Plain text title/intro, escaped on output. */
class Collection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['post_ids' => 'array', 'approved_at' => 'datetime'];
    }

    /** Published pieces in the chosen order (unpublished ones silently drop out). */
    public function posts()
    {
        $ids = array_map('intval', (array) $this->post_ids);
        $posts = Post::published()->with('authors', 'section')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $posts[$id] ?? null)->filter()->values();
    }
}
