<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Post;
use App\Models\Section;
use Illuminate\Support\Str;

/**
 * D95: until real writers join ঐতিহ্য ও উৎসব and রান্নাঘরের গল্প, the copywriter drafts pieces for them on the
 * owner's request. Always a DRAFT under the house byline "হাতপাখা ডেস্ক"; never a made-up person, never
 * published by itself (D42). Facts the owner must check are kept in the desk-only `ai_note`, and publishing an
 * AI draft needs the owner to confirm they edited it and checked the facts.
 */
final class DeskWriter
{
    public const SECTIONS = [
        'heritage' => 'A heritage or festival piece: a place, a craft, a ritual or a festival of Bengal (both Bengals). '
            .'Ground it in what a visitor or participant actually sees, hears and does; then the history in plain words; then how it lives today. '
            .'Only facts that are widely documented. Dates and names you are not fully sure of go in facts_to_check, and are written cautiously in the text ("সপ্তদশ শতকে", not an exact year).',
        'kitchen' => 'A food piece in the "কিচেন স্টোরি" spirit: one dish, ingredient or kitchen custom of Bengal, as culture and memory of a people, '
            .'not a recipe card. When and why it is cooked, how it changes between regions and households (ঘটি-বাঙাল, দুই বাংলা), the season and festival around it, '
            .'how it is made described in flowing prose. No invented family memories or invented people: write about how it is done "in many homes", not "my grandmother".',
    ];

    public static function draft(string $section, string $topic): array
    {
        $sec = Section::where('slug', $section)->first();
        $desk = Author::where('slug', 'hatpakha-desk')->first();
        if (! $sec || ! $desk || ! isset(self::SECTIONS[$section]) || trim($topic) === '') {
            return [null, 'বিভাগ বা বিষয় পাওয়া যায়নি।'];
        }
        $system = 'You write for Hatpakha (হাতপাখা), a Bengali literary web magazine, as its editorial desk (byline: হাতপাখা ডেস্ক). '
            .self::SECTIONS[$section].' '
            .'Write the way a good Bengali magazine essayist writes: standard, simple চলিত বাংলা; concrete nouns and real detail over adjectives; '
            .'sentences of different lengths; a specific opening scene or fact, never a general statement about Bengal or culture; no headings, no bullet lists, no summary paragraph at the end; '
            .'no stock phrases such as "বলা বাহুল্য", "সব মিলিয়ে", "এক কথায়", "অপরিসীম", "অবিচ্ছেদ্য অংশ", "আজও অমলিন", "সমৃদ্ধ ঐতিহ্য". '
            .'Never invent people, quotes, statistics, personal memories or events. 600-850 words. '
            .'Return: title (short, evocative, Bengali), excerpt (one sentence), paragraphs, facts_to_check (each specific claim, date or name the editor should verify, with where to check), '
            .'photo_ideas (2-3 real photos the editor could take or license; no AI pictures of real monuments).';
        [$d, $err] = Claude::json('copy', $system, "<topic>\n".e($topic)."\n</topic>",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'excerpt', 'paragraphs', 'facts_to_check', 'photo_ideas'], 'properties' => [
                'title' => ['type' => 'string'], 'excerpt' => ['type' => 'string'],
                'paragraphs' => ['type' => 'array', 'items' => ['type' => 'string']],
                'facts_to_check' => ['type' => 'array', 'items' => ['type' => 'string']],
                'photo_ideas' => ['type' => 'array', 'items' => ['type' => 'string']],
            ]], null, 6000);
        if (! is_array($d) || empty($d['paragraphs'])) {
            return [null, $err ?: 'খসড়া তৈরি হয়নি।'];
        }

        return [self::save($sec, $desk, (string) $d['title'], (string) $d['excerpt'], (array) $d['paragraphs'], (array) $d['facts_to_check'], (array) $d['photo_ideas']), null];
    }

    /** Store a desk draft (also used for the example pieces). */
    public static function save(Section $sec, Author $desk, string $title, string $excerpt, array $paragraphs, array $facts, array $photos = []): Post
    {
        $note = "এআই খসড়া (হাতপাখা ডেস্ক)। প্রকাশের আগে:\n১. নিজের ভাষায় বদলে নিন; আপনার নিজের দেখা বা জানা কথা যোগ করুন।\n২. নিচের তথ্যগুলো যাচাই করুন।\n\nযাচাই করুন:\n"
            .collect($facts)->map(fn ($f) => '• '.trim((string) $f))->implode("\n")
            .($photos ? "\n\nছবির ভাবনা:\n".collect($photos)->map(fn ($f) => '• '.trim((string) $f))->implode("\n") : '');
        $post = new Post;
        $post->forceFill([
            'title' => mb_substr(trim($title), 0, 200),
            'excerpt' => mb_substr(trim($excerpt), 0, 300),
            'body_html' => collect($paragraphs)->map(fn ($p) => '<p>'.e(trim((string) $p)).'</p>')->implode(''),
            'section_id' => $sec->id, 'format' => 'prose', 'language' => 'bn', 'status' => 'draft',
            'slug' => 'desk-'.$sec->slug.'-'.Str::lower(Str::random(6)),
            'ai_drafted_at' => now(), 'ai_note' => $note,
        ])->save();
        $post->authors()->sync([$desk->id => ['position' => 0]]);
        AuditLog::record('desk.drafted', $post, ['section' => $sec->slug]);

        return $post;
    }
}
