<?php

use App\Models\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D294 (owner: "make … the off-topic list. But do everything automatic"): pieces the old site posted under the house
 * account that are not literature (horoscopes, syllabus downloads, coupons, news and celebrity filler, press releases)
 * are taken out of Google (noindex, and so out of the sitemap and the topic pages). They stay on the site at their
 * addresses. Only the house account's pieces: a writer's own piece is never hidden by itself.
 */
return new class extends Migration
{
    public const SLUGS = [
        // 2024 news, celebrity and how-to filler
        'top-10-surprising-parenting-tips-that-actually-work', '10-essential-python-concepts-you-can-master-in-a-month',
        'the-deliverance-netflix-a-thrilling-new-addition-to-your-watchlist', '50-heartfelt-happy-birthday-wishes-to-make-their-day-special',
        'how-to-cite-chatgpt-in-your-research-a-complete-guide', 'mariah-careys-sister-a-look-into-the-life-of-alison-carey',
        'what-happened-to-taeil-latest-updates-on-ncts-star', 'a-nation-stands-together-pm-modis-inspiring-message-to-team-india-for-paris-paralympics',
        'paris-2024-paralympic-games-opening-ceremony-highlights-schedule', 'meta-misinformation-and-government-pressure-mark-zuckerberg-speaks-out',
        'telegram-founder-durov-s-arrest-what-it-means-for-tech-giants', 'chhatra-samaj-the-new-student-outfit-leading-the-march-to-nabanna-amid-controversy',
        'usopen2024-tennis-stars-compete-for-a-share-of-75-million-in-record-cash-prizes',
        'from-unique-to-inspirational-how-matt-stutzmans-armless-archery-legacy-is-changing-lives',
        // press releases
        'press-release-of-aetm-international-conference-2018', 'press-release-of-aetm-international-conference-2018-2', 'press-release-of-irdp-award-2018',
        // horoscopes, calendars, results, syllabus, coupons, apps, courses
        'monthly-horoscope-february-2016', 'monthly-horoscope-january-2016', 'monthly-horoscope-december-2015', 'monthly-horoscope-november-2015',
        'monthly-horoscope-october-2015', 'monthly-horoscope-august-2014', 'monthly-horoscope-july-2014', 'monthly-horoscope-june-2014',
        'achievement-of-astrology-in-solving-10-regular-problems', 'bengali-calender-december-2013', 'bengali-calender-november-2013', 'bengali-calender-october-2013',
        'wbut-syllabus-download-new-revised-syllabus-wbut-b-tech-syllabus-wbut', 'download-wbut-syllabus-new-old-syllabus',
        'west-bengal-school-service-commission-result-2013', 'madhyamik-2014-mathematics-preparation-test',
        'flight-coupon-code-big-travel-discounts', 'best-gifting-app-greetzap', 'free-online-courses-best-5',
        'how-thesis-writing-services-have-made-impact-over-the-internet',
        // dated puja guides and news of the day
        'durga-puja-2015-dates-and-timings', 'durga-puja-parikrama-2015', 'kolkata-durga-puja-roadmap-routemap', 'first-ever-ice-idol-goddess-durga',
        'ucweb-employees-wish-indian-users-a-happy-independence-day', 'and-then-suddenly-google-look-evolved', 'celebrate-raksha-bandhan-on-august-29th',
        'unknown-facts-26-jan-republic-day-india', 'intense-firework-war',
        // tips, lists of quotes and videos
        'sleep-like-baby-tips-help', 'simple-tips-better-sleep', 'you-can-change-your-negetive-thoughts-to-positive-easily-10-tips',
        'swami-vivekananda-inspiring-quotes', '10-beautiful-life-quotes-by-rabindranath-tagore', '21-colorful-places',
        'the-video-that-inspired-a-million-lives', 'life-motivational-videos-true-heart-touching-inspirational-video', 'sudoku-puzzle-1',
    ];

    public function up(): void
    {
        $desk = DB::table('authors')->where('slug', 'hatpakha-desk')->value('id');
        if (! $desk) {
            return;
        }
        $ids = DB::table('posts')->whereIn('slug', self::SLUGS)->where('noindex', false)
            ->whereIn('id', DB::table('author_post')->where('author_id', $desk)->select('post_id'))
            ->whereNotIn('id', DB::table('author_post')->where('author_id', '!=', $desk)->select('post_id'))
            ->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        DB::table('posts')->whereIn('id', $ids)->update(['noindex' => true]);
        AuditLog::record('seo.hide_off_topic', null, ['posts' => $ids->all()]);
    }

    public function down(): void {}
};
