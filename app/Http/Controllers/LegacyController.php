<?php

namespace App\Http\Controllers;

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

    public function amp(string $slug)
    {
        return redirect(Url::to('/'.Slug::encode(Slug::normalize($slug))), 301);
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

        abort(404);
    }
}
