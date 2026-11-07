<?php

namespace App\Support;

use App\Models\Author;
use App\Models\Post;
use App\Models\Section;

/** D101: puts the public-domain classics for ছোটদের হাতপাখা in as drafts (never published by this). */
final class KidsClassics
{
    public const AUTHORS = [
        'সুকুমার রায়' => ['slug' => 'sukumar-ray', 'bio' => 'সুকুমার রায় (১৮৮৭–১৯২৩): ছড়াকার, লেখক, আঁকিয়ে; ‘আবোল তাবোল’-এর কবি।'],
        'উপেন্দ্রকিশোর রায়চৌধুরী' => ['slug' => 'upendrakishore-raychowdhury', 'bio' => 'উপেন্দ্রকিশোর রায়চৌধুরী (১৮৬৩–১৯১৫): ছোটদের লেখক, ‘টুনটুনির বই’-এর স্রষ্টা, ‘সন্দেশ’ পত্রিকার প্রতিষ্ঠাতা।'],
    ];

    public static function import(?string $file = null): int
    {
        $sec = Section::where('slug', Section::KIDS)->first();
        if (! $sec) {
            return 0;
        }
        $made = 0;
        foreach (json_decode((string) file_get_contents($file ?? database_path('content/kids-classics.json')), true) ?: [] as $x) {
            if (Post::where('title', $x['title'])->where('section_id', $sec->id)->exists()) {
                continue;
            }
            $meta = self::AUTHORS[$x['author']] ?? ['slug' => Transliterate::slug($x['author']), 'bio' => null];
            $author = Author::firstOrCreate(['slug' => $meta['slug']], ['display_name' => $x['author'], 'bio' => $meta['bio'], 'is_guest' => true]);
            $body = $x['format'] === 'poem'
                ? '<p>'.collect($x['text'])->map(fn ($l) => e($l))->implode('<br>').'</p>'
                : collect($x['text'])->map(fn ($p) => '<p>'.e($p).'</p>')->implode('');
            $post = new Post;
            $post->forceFill([
                'title' => $x['title'], 'body_html' => $body, 'section_id' => $sec->id, 'format' => $x['format'], 'language' => 'bn',
                'status' => 'draft', 'age_band' => $x['age'] ?? null,
                'slug' => \App\Support\Review::uniqueSlug(Transliterate::slug($x['title']) ?: 'chhotoder-'.uniqid(), 0),
                'ai_drafted_at' => now(),
                'ai_note' => "ক্লাসিক (স্বত্বমুক্ত): {$x['author']}।\nউৎস: {$x['source']}\n\nপ্রকাশের আগে উৎসের সঙ্গে একবার মিলিয়ে দেখুন (স্তবকের ভাগ উৎসে যেমন আছে তেমন করে দিতে পারেন)।"
                    .(filled($x['check'] ?? '') ? "\n\nবিশেষ করে দেখুন: ".$x['check'] : ''),
            ])->save();
            $post->authors()->sync([$author->id => ['position' => 0]]);
            $made++;
        }

        return $made;
    }
}
