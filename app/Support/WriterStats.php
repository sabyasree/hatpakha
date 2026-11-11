<?php

namespace App\Support;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

/** Writers' own numbers (owner, 2026-11-12): reads, likes, saves, followers. Totals only, never a reader.
 * Places and how readers came are for the desk only (owner, later the same day): see HealthReport. */
final class WriterStats
{
    private const COUNTRIES = ['IN' => 'ভারত', 'BD' => 'বাংলাদেশ', 'US' => 'আমেরিকা', 'GB' => 'ব্রিটেন', 'CA' => 'কানাডা', 'AU' => 'অস্ট্রেলিয়া', 'AE' => 'আমিরশাহি',
        'SG' => 'সিঙ্গাপুর', 'DE' => 'জার্মানি', 'SA' => 'সৌদি আরব', 'QA' => 'কাতার', 'KW' => 'কুয়েত', 'OM' => 'ওমান', 'MY' => 'মালয়েশিয়া', 'NP' => 'নেপাল',
        'JP' => 'জাপান', 'FR' => 'ফ্রান্স', 'IT' => 'ইতালি', 'SE' => 'সুইডেন', 'NL' => 'নেদারল্যান্ডস', 'NZ' => 'নিউজিল্যান্ড', 'IE' => 'আয়ারল্যান্ড'];

    /** Cities readers come from most often, in Bengali (UX review); any other city keeps its English name. */
    private const CITIES = ['Kolkata' => 'কলকাতা', 'Calcutta' => 'কলকাতা', 'Dhaka' => 'ঢাকা', 'Siliguri' => 'শিলিগুড়ি', 'Howrah' => 'হাওড়া', 'Durgapur' => 'দুর্গাপুর',
        'Asansol' => 'আসানসোল', 'Bardhaman' => 'বর্ধমান', 'Kharagpur' => 'খড়্গপুর', 'Agartala' => 'আগরতলা', 'Guwahati' => 'গুয়াহাটি', 'Silchar' => 'শিলচর',
        'Chittagong' => 'চট্টগ্রাম', 'Chattogram' => 'চট্টগ্রাম', 'Sylhet' => 'সিলেট', 'Khulna' => 'খুলনা', 'Rajshahi' => 'রাজশাহী', 'Delhi' => 'দিল্লি', 'New Delhi' => 'দিল্লি',
        'Mumbai' => 'মুম্বই', 'Bengaluru' => 'বেঙ্গালুরু', 'Bangalore' => 'বেঙ্গালুরু', 'Chennai' => 'চেন্নাই', 'Hyderabad' => 'হায়দরাবাদ', 'Pune' => 'পুনে',
        'London' => 'লন্ডন', 'New York' => 'নিউ ইয়র্ক', 'Toronto' => 'টরন্টো', 'Dubai' => 'দুবাই', 'Singapore' => 'সিঙ্গাপুর', 'Sydney' => 'সিডনি'];

    public static function place(string $place): string
    {
        [$city, $cc] = array_pad(explode('|', $place, 2), 2, '');
        $city = self::CITIES[$city] ?? $city;
        $country = self::COUNTRIES[$cc] ?? $cc;

        return $city !== '' ? $city.', '.$country : $country;
    }

    /** @param  \Illuminate\Support\Collection<int, Author>  $authors */
    public static function for($authors): array
    {
        $ids = $authors->pluck('id');
        $posts = Post::published()->with('section')->whereHas('authors', fn ($a) => $a->whereIn('authors.id', $ids))->latest('published_at')->get();
        $pids = $posts->pluck('id');
        $since = now('Asia/Kolkata')->subDays(29)->toDateString();
        $total = DB::table('post_views')->whereIn('post_id', $pids)->selectRaw('post_id, sum(views) v, sum(search) s, sum(social) so, sum(internal) i')->groupBy('post_id')->get()->keyBy('post_id');
        $month = DB::table('post_views')->whereIn('post_id', $pids)->where('day', '>=', $since)->selectRaw('post_id, sum(views) v')->groupBy('post_id')->pluck('v', 'post_id');
        $saves = DB::table('saved_posts')->whereIn('post_id', $pids)->selectRaw('post_id, count(*) n')->groupBy('post_id')->pluck('n', 'post_id');
        $daily = DB::table('post_views')->whereIn('post_id', $pids)->where('day', '>=', $since)->selectRaw('day, sum(views) v')->groupBy('day')->pluck('v', 'day');
        $days = collect(range(29, 0))->map(fn ($d) => now('Asia/Kolkata')->subDays($d)->toDateString())->mapWithKeys(fn ($d) => [$d => (int) ($daily[$d] ?? 0)]);

        $rows = $posts->map(fn (Post $p) => [
            'post' => $p,
            'reads' => (int) ($total[$p->id]->v ?? 0),
            'month' => (int) ($month[$p->id] ?? 0),
            'likes' => (int) $p->likes_count,
            'saves' => (int) ($saves[$p->id] ?? 0),
        ]);

        return [
            'rows' => $rows,
            'reads' => $rows->sum('reads'),
            'month' => $rows->sum('month'),
            'likes' => $rows->sum('likes'),
            'saves' => $rows->sum('saves'),
            'followers' => (int) DB::table('follows')->whereIn('author_id', $ids)->distinct()->count('user_id'),
            'days' => $days,
            'best' => $rows->sortByDesc('reads')->first(),
        ];
    }
}
