<?php

namespace App\Ai;

/** The one report shape both providers must return (strict JSON schema). Shown to editors only. */
final class ReviewSchema
{
    public const FLAGS = ['hate', 'sexual', 'violence', 'defamation', 'spam_links', 'personal_data', 'possible_plagiarism'];

    /** @param array<int,string> $sections allowed section slugs */
    public static function schema(array $sections): array
    {
        $str = ['type' => 'string'];
        $list = ['type' => 'array', 'items' => $str];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['quality_score', 'summary', 'genre', 'strengths', 'suggestions', 'edits', 'decision', 'note_to_writer', 'excerpt', 'suggested_section', 'tags', 'seo_title', 'seo_description', 'policy_flags', 'fits_magazine', 'fit_reason'],
            'properties' => [
                'quality_score' => ['type' => 'integer', 'description' => '1-10 against the rubric'],
                'summary' => ['type' => 'string', 'description' => 'One or two sentences for the editor, in Bengali'],
                'genre' => $str,
                'strengths' => $list,
                'suggestions' => ['type' => 'array', 'items' => $str, 'description' => 'Exactly 3 specific points for the editor, one plain line each, in the language of the piece; the same points are already woven into note_to_writer'],
                // Copy-edits only (D42): applied by the server as exact find → replace, never by the model.
                'edits' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false, 'required' => ['original', 'replacement', 'kind'],
                    'properties' => [
                        'original' => ['type' => 'string', 'description' => 'Exact text copied from the piece, 2-60 characters, unique enough to find'],
                        'replacement' => ['type' => 'string', 'description' => 'The same text with only the correction made'],
                        'kind' => ['type' => 'string', 'enum' => \App\Ai\CopyEdit::KINDS],
                    ],
                ]],
                'decision' => ['type' => 'string', 'enum' => ['publish', 'changes', 'decline'], 'description' => 'Your recommendation to the human editor'],
                'note_to_writer' => ['type' => 'string', 'description' => 'A short personal letter from the literary editor to the writer, in the language of the piece, matching the decision and carrying the whole advice in prose (2 short paragraphs, no lists)'],
                'excerpt' => ['type' => 'string', 'description' => 'One-line teaser for cards, in the language of the piece, at most 160 characters, no spoilers'],
                'suggested_section' => ['type' => 'string', 'enum' => array_values($sections)],
                'tags' => ['type' => 'array', 'items' => $str, 'description' => 'At most 5 short topic tags in Bengali'],
                'seo_title' => $str,
                'seo_description' => ['type' => 'string', 'description' => 'At most 155 characters'],
                'policy_flags' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::FLAGS]],
                'fits_magazine' => ['type' => 'boolean'],
                'fit_reason' => $str,
            ],
        ];
    }

    /** Clamp and tidy whatever came back, so the UI never trusts raw model output blindly. */
    public static function normalise(array $r): array
    {
        $r['quality_score'] = max(1, min(10, (int) ($r['quality_score'] ?? 0)));
        $r['suggestions'] = array_slice(array_values(array_filter((array) ($r['suggestions'] ?? []), 'is_string')), 0, 3);
        $r['tags'] = array_slice(array_values(array_filter((array) ($r['tags'] ?? []), 'is_string')), 0, 5);
        $r['policy_flags'] = array_values(array_intersect((array) ($r['policy_flags'] ?? []), self::FLAGS));
        $r['seo_description'] = mb_substr((string) ($r['seo_description'] ?? ''), 0, 160);
        $r['decision'] = in_array($r['decision'] ?? null, ['publish', 'changes', 'decline'], true) ? $r['decision'] : 'changes';
        $r['note_to_writer'] = mb_substr(trim((string) ($r['note_to_writer'] ?? '')), 0, 1500);
        $r['letter'] = 2;   // the note is a whole letter (suggestions woven in); older reports had a short note
        $r['excerpt'] = mb_substr(trim(strip_tags((string) ($r['excerpt'] ?? ''))), 0, 200);
        $edits = [];
        foreach ((array) ($r['edits'] ?? []) as $e) {
            $o = is_array($e) ? (string) ($e['original'] ?? '') : '';
            $n = is_array($e) ? (string) ($e['replacement'] ?? '') : '';
            if ($o === '' || $o === $n || mb_strlen($o) > 80 || mb_strlen($n) > 100 || str_contains($o.$n, '<')) {
                continue;
            }
            $edit = ['original' => $o, 'replacement' => $n, 'kind' => (string) ($e['kind'] ?? 'word')];
            $edit['kind'] = \App\Ai\CopyEdit::kindOf($edit);
            $edits[] = $edit;
        }
        $r['edits'] = array_slice($edits, 0, 80);

        return $r;
    }
}
