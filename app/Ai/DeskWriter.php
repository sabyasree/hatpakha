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
        // D106 (owner): literary essays, not information. One moment, seen closely; the facts stay in the background.
        'heritage' => 'A literary essay (রম্যরচনা / স্মৃতিগদ্যের ধাঁচ) about one place, craft, ritual or festival of Bengal. '
            .'Do not survey its history, regions or variations. Choose ONE moment of it (an hour, a sound, a gesture, the light) and stay there; '
            .'let at most two or three facts arrive inside the scene, the way a person who was there would mention them, never as explanation.',
        'kitchen' => 'A literary essay about one dish, ingredient or kitchen custom of Bengal, in the spirit of Bengali food writing that is really about time, '
            .'season, people and longing (the tradition of Buddhadeva Bose\'s "ভোজনশিল্পী বাঙালী" or Leela Majumdar\'s kitchen prose; never copy them). '
            .'Not a recipe, not a catalogue of varieties or regions: no lists of dish names, no "in East Bengal… in West Bengal…" survey. '
            .'Stay with one kitchen-moment (the smell reaching the stairs, the waiting, the first bite in the cold) and let the method appear only through it.',
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
            // What makes it literature rather than information (owner, 2026-11-08).
            .Prompt::LITERARY.' '
            .'Show the scene in present tense or a remembered "সেই সময়" in the plural ("আমরা", "পাড়ায়"). '
            .'Let the piece turn once (a small surprise, a shift from then to now, from the many to the one); never end on a moral, a question to the reader or an invitation to write in. '
            .'Honesty: never invent named people, quotes, statistics, dates or events, and never claim a personal memory as a real fact ("আমার ঠাকুমা"). '
            .'Unnamed, typical scenes ("কোনো এক উঠোনে", "পাড়ার মোড়ের দোকানে") are fine; every factual claim must be widely documented, and anything you are not sure of goes in facts_to_check and is left vague in the text. '
            .'450-750 words. First fill plan (the one moment, the feeling under it, the 4-6 exact details you chose, the closing image), then write. '
            .'Return: plan, title (short, evocative, not a label; Bengali), excerpt (one line that makes a reader want to read, not a summary), paragraphs, '
            .'facts_to_check (each specific claim, date or name the editor should verify, with where to check), photo_ideas (2-3 real photos the editor could take or license; no AI pictures of real monuments).';
        [$d, $err] = Claude::json('copy', $system, "<topic>\n".e($topic)."\n</topic>",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['plan', 'title', 'excerpt', 'paragraphs', 'facts_to_check', 'photo_ideas'], 'properties' => [
                'plan' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['moment', 'feeling', 'details', 'closing_image'], 'properties' => [
                    'moment' => ['type' => 'string'], 'feeling' => ['type' => 'string'],
                    'details' => ['type' => 'array', 'items' => ['type' => 'string']], 'closing_image' => ['type' => 'string'],
                ]],
                'title' => ['type' => 'string'], 'excerpt' => ['type' => 'string'],
                'paragraphs' => ['type' => 'array', 'items' => ['type' => 'string']],
                'facts_to_check' => ['type' => 'array', 'items' => ['type' => 'string']],
                'photo_ideas' => ['type' => 'array', 'items' => ['type' => 'string']],
            ]], (string) config('ai.anthropic.writer_model'), 7000);
        if (! is_array($d) || empty($d['paragraphs'])) {
            return [null, $err ?: 'খসড়া তৈরি হয়নি।'];
        }

        $post = self::save($sec, $desk, (string) $d['title'], (string) $d['excerpt'], (array) $d['paragraphs'], (array) $d['facts_to_check'], (array) $d['photo_ideas']);
        if (is_array($plan = $d['plan'] ?? null)) {
            // D106: what the writer aimed for, so the owner can judge and rewrite with it in mind.
            $post->forceFill(['ai_note' => $post->ai_note."\n\nলেখার ভাবনা:\n• মুহূর্ত: ".trim((string) ($plan['moment'] ?? ''))
                ."\n• অনুভব: ".trim((string) ($plan['feeling'] ?? ''))."\n• শেষের ছবি: ".trim((string) ($plan['closing_image'] ?? ''))])->saveQuietly();
        }

        // Owner (2026-11-08): every AI-written piece gets a meaningful cover from the designer (a proposal the owner approves).
        Illustrator::queue($post, $section === 'heritage'
            ? 'A symbolic or everyday scene from the piece (people, light, season, objects); never a drawing of a specific real temple, monument or place.'
            : 'The dish or ingredient in its kitchen moment: warm light, hands at work, the season; appetising and real.');

        return [$post, null];
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
