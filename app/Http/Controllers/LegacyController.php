<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Redirect;
use App\Support\Slug;
use App\Support\Url;
use Illuminate\Http\Request;

class LegacyController extends Controller
{
    public function gone()
    {
        abort(410);
    }

    /** /wp-json/wp/v2/posts/{id} (or pages/{id}) → that piece or page by its old WordPress number; the rest → home. */
    public function wpJson(\Illuminate\Http\Request $request, ?string $rest = null)
    {
        abort_unless(in_array($request->method(), ['GET', 'HEAD'], true), 410);
        if (preg_match('#^wp/v2/(posts|pages)/(\d+)#', (string) $rest, $m)) {
            $target = $m[1] === 'posts'
                ? \App\Models\Post::whereIn('status', ['published', 'archived'])->where('legacy_wp_id', (int) $m[2])->first()
                : \App\Models\Page::where('legacy_wp_id', (int) $m[2])->first();
            if ($target && (! $target instanceof \App\Models\Post || $target->isLive() || $target->status === 'archived')) {
                return redirect($target->url(), 301);
            }
        }

        return redirect(Url::to('/'), 301);
    }

    public function amp(string $slug)
    {
        return redirect(Url::to('/'.Slug::encode(Slug::normalize($slug))), 301);
    }

    /** /{post}/feed/ and /{post}/{attachment}/ → the post itself (301), if it exists. */
    public function toPost(Request $request, string $slug)
    {
        $post = Post::where('slug', Slug::normalize($slug))->first();

        return $post ? redirect($post->url(), 301) : $this->fallback($request);
    }

    /** WordPress child categories (/category/literature/poetry/) → the child's own URL, which then resolves or 301s. */
    public function nestedCategory(string $parent, string $child)
    {
        return redirect(Url::to('/category/'.Slug::encode(Slug::normalize($child))), 301);
    }

    /** /category/x/page/2/ → /category/x/?page=2 */
    public function paged(string $type, string $slug, int $n)
    {
        return redirect(Url::to("/{$type}/".Slug::encode(Slug::normalize($slug))).($n > 1 ? '?page='.$n : ''), 301);
    }

    /** Date archives (/2016/, /2016/03/, /2016/03/15/) → the reader archive for that year. A real post slug wins. */
    public function dateArchive(Request $request, string $y)
    {
        if (! $request->route('m') && ($post = Post::published()->where('slug', $y)->first())) {
            return app(PostController::class)($y);
        }

        // Old WordPress date archives → the year's archive page (/2016/03/ → /archive/2016/#m3).
        $m = (int) $request->route('m');
        if (! isset(ArchiveController::yearCounts()[(int) $y])) {
            return redirect(Url::to('/archive'), 301);
        }

        return redirect(Url::to('/archive/'.$y).($m >= 1 && $m <= 12 ? '#m'.$m : ''), 301);
    }

    /** Looks the path up in the redirects table (docs/SEO.md §1); otherwise 404. */
    public function fallback(Request $request)
    {
        $path = '/'.trim(Slug::normalize($request->getPathInfo()), '/').'/';
        $hit = Redirect::where('from_path', $path)->first();
        if ($hit) {
            $hit->increment('hits');
            if ($hit->status_code === 410 || ! $hit->to_path) {
                abort(410);
            }

            return redirect(Url::to($hit->to_path), $hit->status_code);
        }
        // Old WordPress image pages ("/img_20190731_092351_057/", "/images-4-2/"): the piece that shows that picture.
        if ($post = self::pieceWithPicture($path)) {
            return redirect($post->url(), 301);
        }

        abort(404);
    }

    /**
     * Search Console 404s (owner, 2026-11-12): WordPress gave every uploaded picture its own page, named after the file.
     * The file's name is still in the piece that used it (its text or its cover), so that piece is where the reader goes.
     * Only for file-like names; the answer is cached for a day either way.
     */
    public static function pieceWithPicture(string $path): ?Post
    {
        $slug = trim($path, '/');
        if (! preg_match('/^[a-z0-9_-]{5,120}$/i', $slug)) {
            return null;
        }
        $id = \Illuminate\Support\Facades\Cache::remember('legacy-pic:'.$slug, 86400, function () use ($slug) {
            foreach (array_unique([$slug, preg_replace('/-\d+$/', '', $slug)]) as $name) {   // WordPress added "-2" to repeated names
                if (strlen($name) < 5) {
                    continue;
                }
                $like = fn (string $tail) => '%/'.$name.$tail.'%';   // the name is letters, digits, _ and - only; an _ matching any one letter is harmless
                $hit = Post::published()->where(fn ($q) => $q->where('featured_image_path', 'like', $like('.'))->orWhere('featured_image_path', 'like', $like('-'))
                    ->orWhere('body_html', 'like', $like('.'))->orWhere('body_html', 'like', $like('-')))->orderBy('published_at')->value('id');
                if ($hit) {
                    return $hit;
                }
            }

            return 0;
        });

        return $id ? Post::find($id) : null;
    }
}
