<?php

namespace App\Support;

use App\Models\Author;
use App\Models\Post;
use App\Models\User;

/**
 * D166: who is looking, for the header's "আমার" button, the "আরও" sheet and the account pages: their name and
 * initial, whether they write for Hatpakha (then "আমার লেখা" comes first), and how many pieces wait for them.
 * Worked out once per request (kept on the request, never between requests).
 */
final class AccountMenu
{
    /** @return array{name: string, initial: string, avatar: ?string, writer: bool, act: int, author: ?Author} */
    public static function for(User $user): array
    {
        $key = 'hp.account-menu.'.$user->id;
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }
        $menu = (function () use ($user) {
            $author = Author::where('user_id', $user->id)->first();
            $mine = Post::query()->where(fn ($q) => $q->where('submitted_by', $user->id)->orWhereHas('authors', fn ($a) => $a->where('user_id', $user->id)));
            $writer = ($author && $author->published_count > 0) || (clone $mine)->exists();
            $name = $author?->display_name_bn ?: $user->name;

            return [
                'name' => $name,
                'first' => trim(explode(' ', trim($name))[0] ?? $name) ?: $name,
                'initial' => mb_substr($name, 0, 1),
                'avatar' => $author?->avatar_path,
                'writer' => $writer,
                'act' => $writer ? (clone $mine)->whereIn('status', ['changes', 'confirm'])->count() : 0,
                'author' => $author,
            ];
        })();
        request()->attributes->set($key, $menu);

        return $menu;
    }
}
