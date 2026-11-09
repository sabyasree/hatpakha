<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use App\Models\NewsletterDelivery;
use App\Support\NewsletterLinks;
use Illuminate\Support\Facades\DB;

/** D117: counts one click on a letter's link, then sends the reader on. A bad or old link still goes home. */
class NewsletterClickController extends Controller
{
    public function __invoke(int $newsletter, int $delivery, string $link, string $sig)
    {
        $n = Newsletter::find($newsletter);
        if (! $n) {
            return redirect(url('/').'/', 302);
        }
        $ok = hash_equals(NewsletterLinks::sig($newsletter, $delivery, $link), $sig)
            && NewsletterDelivery::whereKey($delivery)->where('newsletter_id', $newsletter)->exists();
        if ($ok) {
            DB::table('newsletter_clicks')->insert(['newsletter_id' => $newsletter, 'delivery_id' => $delivery, 'link' => $link, 'created_at' => now()]);
        }

        return redirect(NewsletterLinks::target($n, $link), 302)->header('X-Robots-Tag', 'noindex');
    }
}
