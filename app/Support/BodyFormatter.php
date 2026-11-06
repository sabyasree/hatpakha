<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Tidies the STRUCTURE of sanitised post HTML so old and new posts look consistent
 * (owner: "don't change the content, only the structure and how it looks").
 *
 * - wraps loose text in paragraphs; splits paragraphs at blank lines (<br><br>)
 * - prose only: a paragraph made of long lines joined by <br> becomes separate paragraphs
 * - removes empty paragraphs, leading/trailing <br>, runs of non-breaking spaces
 * - poems: when every line was saved as its own paragraph, consecutive lines are joined into a
 *   stanza (lines kept, separated by <br>); empty paragraphs in the original still mark stanza breaks
 * - moves images out of the middle of a sentence into their own <figure>
 * - unwraps bold when (almost) the whole post is bold
 * - prose only: drops a first line that only repeats the title (never in poems)
 *
 * Safety net: the visible text before and after must be identical (ignoring whitespace and the
 * repeated title). If not, the original HTML is returned unchanged.
 */
final class BodyFormatter
{
    private const BLOCK = ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'blockquote', 'figure', 'hr', 'table', 'pre'];

    /** True when the last call refused to change anything because the text would have changed. */
    public static bool $refused = false;

    public static function format(string $html, string $format = 'prose', ?string $title = null): string
    {
        // Two passes: the second only settles whitespace left by the first, so saving twice is stable.
        $once = self::pass($html, $format, $title);
        $refused = self::$refused;
        $twice = self::pass($once, $format, null);
        self::$refused = $refused || self::$refused;

        return $twice;
    }

    private static function pass(string $html, string $format, ?string $title): string
    {
        self::$refused = false;
        if (trim($html) === '') {
            return $html;
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<html><head><meta charset="utf-8"></head><body><div id="hp-root">'.$html.'</div></body></html>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('hp-root');
        if (! $root) {
            return $html;
        }

        self::wrapLooseInline($doc, $root);
        self::liftImages($doc, $root);
        self::splitParagraphs($doc, $root, $format === 'prose');
        if ($format === 'poem') {
            self::joinPoemLines($doc, $root);
        }
        self::cleanParagraphs($doc, $root);
        self::unwrapAllBold($doc, $root);
        // Prose only: in a poem the first line is often also the title, and it is part of the poem.
        $droppedTitle = $title !== null && $format !== 'poem' ? self::dropRepeatedTitle($root, $title) : null;

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        $out = trim(preg_replace("/\n{2,}/", "\n", preg_replace('~(</(?:p|h[2-4]|ul|ol|blockquote|figure|table|pre)>|<hr>)~', "$1\n", $out)));

        // Every visible character must survive, in the same order. Only spacing may differ.
        $before = self::letters($html);
        $after = ($droppedTitle !== null ? self::letters($droppedTitle) : '').self::letters($out);

        if ($before !== $after) {
            self::$refused = true;

            return $html;
        }

        return $out;
    }

    /** Visible text, whitespace-insensitive: what the reader actually reads. */
    public static function text(string $html): string
    {
        $t = html_entity_decode(strip_tags(preg_replace('~<br\s*/?>|</p>|</h\d>|</li>|</figcaption>~i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}\x{200B}]+/u', ' ', $t));
    }

    /** Visible characters with all whitespace removed (the strict comparison used by the safety net). */
    public static function letters(string $html): string
    {
        return preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', self::text($html));
    }

    /** Consecutive top-level text/inline nodes → one <p> (old posts often have no paragraphs). */
    private static function wrapLooseInline(DOMDocument $doc, DOMElement $root): void
    {
        $run = [];
        $flush = function (?DOMNode $before) use (&$run, $doc, $root) {
            $text = '';
            foreach ($run as $n) {
                $text .= $n->textContent;
            }
            if (trim(str_replace("\u{00A0}", ' ', $text)) === '' && ! self::hasImg($run)) {
                $run = [];

                return;
            }
            $p = $doc->createElement('p');
            $root->insertBefore($p, $before);
            foreach ($run as $n) {
                $p->appendChild($n);
            }
            $run = [];
        };
        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node instanceof DOMElement && in_array($node->tagName, self::BLOCK, true)) {
                if ($run) {
                    $flush($node);
                }
            } else {
                $run[] = $node;
            }
        }
        if ($run) {
            $flush(null);
        }
    }

    /** @param array<int,DOMNode> $nodes */
    private static function hasImg(array $nodes): bool
    {
        foreach ($nodes as $n) {
            if ($n instanceof DOMElement && ($n->tagName === 'img' || $n->getElementsByTagName('img')->length)) {
                return true;
            }
        }

        return false;
    }

    /** An image sitting inside a paragraph of text gets its own <figure> just before that paragraph. */
    private static function liftImages(DOMDocument $doc, DOMElement $root): void
    {
        foreach (iterator_to_array($root->getElementsByTagName('p')) as $p) {
            $imgs = iterator_to_array($p->getElementsByTagName('img'));
            if (! $imgs) {
                continue;
            }
            foreach ($imgs as $img) {
                $holder = $img->parentNode instanceof DOMElement && $img->parentNode->tagName === 'a' && trim($img->parentNode->textContent) === '' ? $img->parentNode : $img;
                $fig = $doc->createElement('figure');
                $p->parentNode->insertBefore($fig, $p);
                $fig->appendChild($holder);
            }
        }
    }

    private static function splitParagraphs(DOMDocument $doc, DOMElement $root, bool $prose): void
    {
        foreach (iterator_to_array($root->getElementsByTagName('p')) as $p) {
            if ($p->parentNode !== $root) {
                continue; // leave paragraphs inside blockquotes/lists alone
            }
            // Group children into lines separated by <br>.
            $lines = [[]];
            foreach (iterator_to_array($p->childNodes) as $c) {
                if ($c instanceof DOMElement && $c->tagName === 'br') {
                    $lines[] = [];
                } else {
                    $lines[count($lines) - 1][] = $c;
                }
            }
            if (count($lines) < 2) {
                continue;
            }
            $lineText = array_map(fn ($nodes) => trim(str_replace("\u{00A0}", ' ', implode('', array_map(fn ($n) => $n->textContent, $nodes)))), $lines);

            // Paragraph groups: blank line always splits; in prose, long single lines are paragraphs too.
            $nonEmpty = array_values(array_filter($lineText, fn ($t) => $t !== ''));
            $avg = $nonEmpty ? array_sum(array_map('mb_strlen', $nonEmpty)) / count($nonEmpty) : 0;
            $eachLineIsParagraph = $prose && $avg >= 90;

            $groups = [[]];
            foreach ($lines as $i => $nodes) {
                if ($lineText[$i] === '' && ! self::hasImg($nodes)) {
                    $groups[] = [];

                    continue;
                }
                if ($eachLineIsParagraph && $groups[count($groups) - 1]) {
                    $groups[] = [];
                }
                $groups[count($groups) - 1][] = $nodes;
            }
            $groups = array_values(array_filter($groups));
            if (count($groups) < 2) {
                continue;
            }
            foreach ($groups as $g) {
                $np = $doc->createElement('p');
                foreach ($g as $li => $nodes) {
                    if ($li > 0) {
                        $np->appendChild($doc->createElement('br'));
                    }
                    foreach ($nodes as $n) {
                        $np->appendChild($n);
                    }
                }
                $root->insertBefore($np, $p);
            }
            $root->removeChild($p);
        }
    }

    /** Poems saved one paragraph per line → stanzas. Only when that pattern clearly dominates. */
    private static function joinPoemLines(DOMDocument $doc, DOMElement $root): void
    {
        $ps = array_values(array_filter(iterator_to_array($root->childNodes), fn ($n) => $n instanceof DOMElement && $n->tagName === 'p'));
        $isBlank = fn (DOMElement $p) => trim(str_replace("\u{00A0}", ' ', $p->textContent)) === '' && ! $p->getElementsByTagName('img')->length;
        $lines = array_filter($ps, fn ($p) => ! $isBlank($p));
        if (count($lines) < 4) {
            return;
        }
        $single = array_filter($lines, fn ($p) => ! $p->getElementsByTagName('br')->length && mb_strlen(trim($p->textContent)) <= 90);
        if (count($single) / count($lines) < 0.8) {
            return;
        }

        $stanza = null;
        foreach (iterator_to_array($root->childNodes) as $node) {
            $isP = $node instanceof DOMElement && $node->tagName === 'p';
            if (! $isP) {
                if ($node instanceof DOMElement || trim($node->textContent) !== '') {
                    $stanza = null; // headings, images etc. end a stanza
                }

                continue;
            }
            if ($isBlank($node) || $node->getElementsByTagName('img')->length) {
                $stanza = null;

                continue;
            }
            if ($stanza === null) {
                $stanza = $node;

                continue;
            }
            $stanza->appendChild($doc->createElement('br'));
            while ($node->firstChild) {
                $stanza->appendChild($node->firstChild);
            }
            $root->removeChild($node);
        }
    }

    private static function cleanParagraphs(DOMDocument $doc, DOMElement $root): void
    {
        $xp = new DOMXPath($doc);
        // Runs of non-breaking spaces (old manual indentation) → one normal space.
        foreach ($xp->query('.//text()', $root) as $t) {
            $v = preg_replace('/[\x{00A0} ]{2,}/u', ' ', $t->nodeValue);
            $v = str_replace("\u{00A0}", ' ', $v);
            if ($v !== $t->nodeValue) {
                $t->nodeValue = $v;
            }
        }
        foreach (iterator_to_array($root->getElementsByTagName('p')) as $p) {
            // Trim <br> and whitespace at both ends.
            foreach (['firstChild', 'lastChild'] as $end) {
                while ($p->$end && (($p->$end instanceof DOMElement && $p->$end->tagName === 'br') || ($p->$end->nodeType === XML_TEXT_NODE && trim($p->$end->nodeValue) === ''))) {
                    $p->removeChild($p->$end);
                }
            }
            if ($p->firstChild && $p->firstChild->nodeType === XML_TEXT_NODE) {
                $p->firstChild->nodeValue = ltrim($p->firstChild->nodeValue);
            }
            if ($p->lastChild && $p->lastChild->nodeType === XML_TEXT_NODE) {
                $p->lastChild->nodeValue = rtrim($p->lastChild->nodeValue);
            }
            if (trim($p->textContent) === '' && ! $p->getElementsByTagName('img')->length) {
                $p->parentNode->removeChild($p);
            }
        }
        // Empty inline wrappers left behind (<strong> </strong>).
        foreach (['strong', 'b', 'em', 'i', 'u'] as $tag) {
            foreach (iterator_to_array($root->getElementsByTagName($tag)) as $el) {
                if (trim($el->textContent) === '' && ! $el->getElementsByTagName('img')->length) {
                    self::unwrap($el);
                }
            }
        }
    }

    /** If 80%+ of the text is bold, bold means nothing: unwrap it. */
    private static function unwrapAllBold(DOMDocument $doc, DOMElement $root): void
    {
        $total = mb_strlen(trim($root->textContent));
        if ($total < 200) {
            return;
        }
        $bold = 0;
        $els = [];
        foreach (['strong', 'b'] as $tag) {
            foreach ($root->getElementsByTagName($tag) as $el) {
                if (! $el->parentNode || in_array($el->parentNode->nodeName, ['strong', 'b'], true)) {
                    continue;
                }
                $bold += mb_strlen($el->textContent);
                $els[] = $el;
            }
        }
        if ($bold >= 0.8 * $total) {
            foreach ($els as $el) {
                self::unwrap($el);
            }
            foreach (['strong', 'b'] as $tag) {
                foreach (iterator_to_array($root->getElementsByTagName($tag)) as $el) {
                    self::unwrap($el);
                }
            }
        }
    }

    /** Returns the removed HTML if the first block only repeated the post title. */
    private static function dropRepeatedTitle(DOMElement $root, string $title): ?string
    {
        $first = $root->firstChild;
        while ($first && $first->nodeType === XML_TEXT_NODE && trim($first->nodeValue) === '') {
            $first = $first->nextSibling;
        }
        if (! $first instanceof DOMElement || ! in_array($first->tagName, ['p', 'h2', 'h3', 'h4'], true) || $first->getElementsByTagName('img')->length) {
            return null;
        }
        $norm = fn (string $s) => mb_strtolower(trim(preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode($s)), " \t:-–—।|~*"));
        if ($norm($first->textContent) === '' || $norm($first->textContent) !== $norm($title)) {
            return null;
        }
        $removed = $first->ownerDocument->saveHTML($first);
        $root->removeChild($first);

        return $removed;
    }

    private static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }
}
