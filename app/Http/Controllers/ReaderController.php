<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Likes, the reading list and following writers (D64). Signed-in, verified readers; works with or without JavaScript. */
class ReaderController extends Controller
{
    public function like(Request $request, Post $post)
    {
        abort_unless($post->isLive(), 404);

        return $this->toggle($request, 'post_likes', 'post_id', $post->id, function (bool $on) use ($post) {
            $post->forceFill(['likes_count' => DB::table('post_likes')->where('post_id', $post->id)->count()])->saveQuietly();

            return ['count' => $post->likes_count];
        }, $post->url().'#reactions');
    }

    public function save(Request $request, Post $post)
    {
        abort_unless($post->isLive(), 404);

        return $this->toggle($request, 'saved_posts', 'post_id', $post->id, fn () => [], $post->url().'#reactions');
    }

    public function follow(Request $request, Author $author)
    {
        abort_if($author->is_desk || $author->user_id === $request->user()->id, 422);

        return $this->toggle($request, 'follows', 'author_id', $author->id, function () use ($author) {
            $author->forceFill(['followers_count' => DB::table('follows')->where('author_id', $author->id)->count()])->saveQuietly();

            return ['count' => $author->followers_count];
        }, url()->previous() ?: $author->url());
    }

    private function toggle(Request $request, string $table, string $col, int $id, \Closure $after, string $back)
    {
        $key = ['user_id' => $request->user()->id, $col => $id];
        $on = ! DB::table($table)->where($key)->exists();
        $on ? DB::table($table)->insertOrIgnore($key + ['created_at' => now()]) : DB::table($table)->where($key)->delete();
        $extra = $after($on);

        return $request->expectsJson() ? response()->json(['on' => $on] + $extra) : redirect($back);
    }
}
