<?php

namespace App\Ai;

use App\Models\EditorTask;
use App\Models\Post;
use App\Models\Section;

/**
 * D101: the teacher of ছোটদের হাতপাখা. For each children's piece: the age band, a short list of harder words with
 * simple Bengali and English meanings (for parents abroad), and whether the piece suits children. It never changes
 * the text. The owner sees and can edit everything on the piece's desk page before publishing.
 */
final class KidsTeacher
{
    public const AGES = ['3-5' => '৩–৫ বছর', '6-8' => '৬–৮ বছর', '9-12' => '৯–১২ বছর'];

    public static function prepare(Post $post): ?string
    {
        $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br />', '</p>'], "\n", (string) $post->body_html))));
        [$d, $err] = Claude::json('kids',
            'You are an experienced primary-school teacher of Bengali, helping parents who live abroad read with their children. '
            .'For the piece (DATA): choose the age band it suits best (3-5, 6-8 or 9-12) for a child hearing it read aloud by a parent; '
            .'list 4-10 words a young child or a parent abroad may not know (old, regional or literary words; skip easy words), each with a short, simple Bengali meaning '
            .'and a short English meaning; say whether the piece is suitable for children (no frightening violence, cruelty presented as fine, adult themes) with a one-line reason. '
            .'Never change the text. Use the word exactly as written in the piece.',
            "<piece><title>".e($post->title)."</title>\n<text>".e(mb_substr($text, 0, 6000))."</text></piece>",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['age', 'words', 'suitable', 'note'], 'properties' => [
                'age' => ['type' => 'string', 'enum' => array_keys(self::AGES)],
                'words' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['word', 'bn', 'en'],
                    'properties' => ['word' => ['type' => 'string'], 'bn' => ['type' => 'string'], 'en' => ['type' => 'string']]]],
                'suitable' => ['type' => 'boolean'], 'note' => ['type' => 'string'],
            ]], null, 2000, $post->id);
        if (! is_array($d)) {
            return $err ?: 'উত্তর পাওয়া যায়নি।';
        }
        // Only words that really occur in the piece.
        $words = collect((array) $d['words'])->filter(fn ($w) => filled($w['word'] ?? '') && mb_strpos($text, (string) $w['word']) !== false)
            ->map(fn ($w) => ['word' => mb_substr(trim($w['word']), 0, 40), 'bn' => mb_substr(trim(strip_tags($w['bn'])), 0, 160), 'en' => mb_substr(trim(strip_tags($w['en'])), 0, 160)])
            ->unique('word')->take(10)->values()->all();
        $post->forceFill(['age_band' => $post->age_band ?: $d['age'], 'glossary' => $words])->saveQuietly();
        if (! ($d['suitable'] ?? true)) {
            EditorTask::propose('alert', __('শিক্ষকের সতর্কতা: ').$post->title, ['body' => (string) $d['note']], $post);
        }

        return null;
    }

    /** Every piece in the section still without word meanings. Returns how many were done. */
    public static function backlog(int $max = 10): int
    {
        $sec = Section::where('slug', Section::KIDS)->value('id');
        $done = 0;
        foreach (Post::where('section_id', $sec)->whereIn('status', ['draft', 'pending', 'scheduled', 'published'])->whereNull('glossary')->limit($max)->get() as $p) {
            $done += self::prepare($p) === null ? 1 : 0;
        }

        return $done;
    }
}
