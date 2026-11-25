<?php

use App\Support\Slug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D291 (owner, 2026-11-25: "do the titles too"): new Google titles and descriptions for the pieces Search Console shows
 * most often that still carried old WordPress titles. Written from each piece's own text; only metadata changes, never
 * the piece. A title the desk has changed since (anything but the old one) is left alone.
 */
return new class extends Migration
{
    public const TITLES = [
        'vidyasagar-the-social-reformer' => [
            'old' => ['Ishwar Chandra Vidyasagar – The Social Reformer | Hatpakha'],
            'title' => 'Ishwar Chandra Vidyasagar: Life and Social Reforms',
            'description' => 'Ishwar Chandra Vidyasagar (ঈশ্বরচন্দ্র বিদ্যাসাগর, 1820–1891): his life, the schools he opened across Bengal and the Widow Remarriage Act of 1856.',
        ],
        'mahalaya-birendra-krishna-bhadra' => [
            'old' => ['Mahalaya - Birendra Krishna Bhadra | Hatpakha - An online magazine'],
            'title' => 'Mahalaya and Birendra Krishna Bhadra’s Mahishasura Mardini',
            'description' => 'What Mahalaya means, the story of Mahishasura Mardini, and Birendra Krishna Bhadra’s dawn recitation that starts Durga Puja, with the recording.',
        ],
        'কবিতা-কী' => [
            'old' => ['কবিতা কী | Hatpakha - An online Bengali English Magazine'],
            'title' => 'কবিতা কী: কবিতার সংজ্ঞা ও বৈশিষ্ট্য',
            'description' => null,
        ],
        'জ্যোৎস্না-বিলাস' => [
            'old' => ['জ্যোৎস্না বিলাস | Hatpakha - An online bengali english magazine'],
            'title' => 'জ্যোৎস্না বিলাস: মফঃস্বলের এক রাতের গল্প',
            'description' => 'লোডশেডিংয়ের সন্ধ্যায় মোমবাতির আলোয় বিভূতিভূষণের গা-ছমছমে গল্প শেষ করে মফঃস্বলের এক সরকারি চাকুরে বেরোলেন একটু হাওয়া খেতে। তারপর…',
        ],
    ];

    public function up(): void
    {
        foreach (self::TITLES as $slug => $t) {
            $q = DB::table('posts')->where('slug', Slug::normalize($slug))
                ->where(fn ($q) => $q->whereNull('seo_title')->orWhere('seo_title', '')->orWhereIn('seo_title', $t['old']));
            $q->update(array_filter(['seo_title' => $t['title'], 'seo_description' => $t['description']]));
        }
    }

    public function down(): void
    {
        // Metadata only; the desk keeps the history (AuditLog) and any title can be edited there.
    }
};
