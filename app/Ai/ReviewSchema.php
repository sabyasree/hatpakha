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
            'required' => ['quality_score', 'summary', 'genre', 'strengths', 'suggestions', 'spelling', 'suggested_section', 'tags', 'seo_title', 'seo_description', 'policy_flags', 'fits_magazine', 'fit_reason'],
            'properties' => [
                'quality_score' => ['type' => 'integer', 'description' => '1-10 against the rubric'],
                'summary' => ['type' => 'string', 'description' => 'One or two sentences for the editor, in Bengali'],
                'genre' => $str,
                'strengths' => $list,
                'suggestions' => ['type' => 'array', 'items' => $str, 'description' => 'Exactly 3 kind, specific suggestions for the writer, in the language of the piece'],
                'spelling' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false, 'required' => ['original', 'suggestion'],
                    'properties' => ['original' => $str, 'suggestion' => $str],
                ]],
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

        return $r;
    }
}
