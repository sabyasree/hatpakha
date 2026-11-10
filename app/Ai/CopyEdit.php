<?php

namespace App\Ai;

use DOMDocument;
use DOMText;
use DOMXPath;

/**
 * Applies the AI editor's copy-edits (D42) to a piece WITHOUT ever letting the model rewrite it:
 * each edit is a short exact find → replace inside one text node; markup is never touched.
 * The server, not the model, decides what counts as a copy-edit: anything that changes more than
 * a little of the original is re-labelled "word" (needs the owner's tick and the writer's OK).
 */
final class CopyEdit
{
    public const KINDS = ['spelling', 'punctuation', 'spacing', 'typo', 'word'];

    public const LABELS = ['spelling' => 'বানান', 'punctuation' => 'যতিচিহ্ন', 'spacing' => 'ফাঁক', 'typo' => 'টাইপের ভুল', 'word' => 'শব্দ বদল'];

    /** Copy-edit kinds that never change the writer's words. */
    public const SAFE = ['spelling', 'punctuation', 'spacing', 'typo'];

    /**
     * Server-side check of the model's own label.
     *
     * @param array{original:string,replacement:string,kind:string} $e
     */
    public static function kindOf(array $e): string
    {
        $kind = in_array($e['kind'] ?? '', self::KINDS, true) ? $e['kind'] : 'word';
        if ($kind === 'word') {
            return 'word';
        }
        $a = preg_split('//u', $e['original'], -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('//u', $e['replacement'], -1, PREG_SPLIT_NO_EMPTY);
        $changed = self::distance($a, $b);
        // Copy-edits touch a few characters; anything bigger is a word change, whatever the model says.
        $limit = max(2, (int) floor(count($a) * 0.34));

        return $changed <= $limit ? $kind : 'word';
    }

    /**
     * @param array<int,array{original:string,replacement:string,kind:string}> $edits
     * @return array{html:string, applied:array<int,int>, missing:array<int,int>}
     */
    public static function apply(string $html, array $edits): array
    {
        if ($edits === [] || trim($html) === '') {
            return ['html' => $html, 'applied' => [], 'missing' => array_keys($edits)];
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="ce-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('ce-root') ?? $doc->documentElement;
        $xp = new DOMXPath($doc);
        $applied = $missing = [];
        foreach ($edits as $i => $e) {
            $done = false;
            foreach ($xp->query('.//text()', $root) as $node) {
                /** @var DOMText $node */
                $pos = mb_strpos($node->nodeValue, $e['original']);
                if ($pos !== false) {
                    $node->nodeValue = mb_substr($node->nodeValue, 0, $pos).$e['replacement'].mb_substr($node->nodeValue, $pos + mb_strlen($e['original']));
                    $done = true;
                    break;
                }
            }
            $done ? $applied[] = $i : $missing[] = $i;
        }
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return ['html' => $out, 'applied' => $applied, 'missing' => $missing];
    }

    /** Levenshtein distance over characters (multibyte-safe; edits are short). */
    private static function distance(array $a, array $b): int
    {
        $prev = range(0, count($b));
        foreach ($a as $i => $ca) {
            $cur = [$i + 1];
            foreach ($b as $j => $cb) {
                $cur[] = min($prev[$j + 1] + 1, $cur[$j] + 1, $prev[$j] + ($ca === $cb ? 0 : 1));
            }
            $prev = $cur;
        }

        return end($prev);
    }
}
