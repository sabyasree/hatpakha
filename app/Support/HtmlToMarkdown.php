<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * A page's main content as plain Markdown, for AI agents that ask for it (D126). Small on purpose: headings,
 * paragraphs, line breaks (poems keep their lines), links, lists, quotes, emphasis and image alt text.
 * Forms, buttons, scripts, ads, menus, hidden parts and anything marked data-md-skip (share and like
 * buttons, the newsletter box) are left out.
 */
class HtmlToMarkdown
{
    private const SKIP = ['script', 'style', 'noscript', 'template', 'form', 'button', 'input', 'select', 'textarea', 'nav', 'svg', 'iframe', 'ins', 'dialog', 'video', 'audio', 'canvas', 'object'];

    /** The page as Markdown: title, a short header with the address and description, then the content of <main>. */
    public static function page(string $html, string $url): ?string
    {
        $dom = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $x = new DOMXPath($dom);

        $main = $x->query('//main')->item(0);
        if (! $main instanceof DOMElement) {
            return null;
        }
        foreach (iterator_to_array($x->query('.//*[@aria-hidden="true" or @hidden or @data-md-skip or contains(concat(" ", normalize-space(@class), " "), " sr-only ")]', $main)) as $n) {
            $n->parentNode?->removeChild($n);
        }

        $title = trim((string) $x->query('//title')->item(0)?->textContent);
        $desc = trim((string) $x->query('//meta[@name="description"]/@content')->item(0)?->nodeValue);
        $body = self::tidy((new self)->blocks($main));

        $head = '# '.($title !== '' ? $title : 'হাতপাখা')."\n\n";
        $head .= '> '.($desc !== '' ? $desc."\n>\n> " : '').'Source: <'.$url.'>'."\n\n";

        return $head.$body."\n";
    }

    private function blocks(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->node($child);
        }

        return $out;
    }

    private function node(DOMNode $n): string
    {
        if ($n->nodeType === XML_TEXT_NODE) {
            return preg_replace('/\s+/u', ' ', $n->textContent);
        }
        if (! $n instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($n->tagName);
        if (in_array($tag, self::SKIP, true)) {
            return '';
        }

        return match ($tag) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => "\n\n".str_repeat('#', max(2, (int) $tag[1])).' '.trim($this->inline($n))."\n\n",
            'p', 'figcaption' => "\n\n".trim($this->inline($n))."\n\n",
            'br' => "\x01\n",
            'hr' => "\n\n---\n\n",
            'blockquote' => "\n\n".preg_replace('/^/mu', '> ', trim(self::tidy($this->blocks($n), false)))."\n\n",
            'ul', 'ol' => "\n\n".$this->list($n, $tag === 'ol')."\n\n",
            'pre' => "\n\n```\n".trim($n->textContent)."\n```\n\n",
            'a' => $this->link($n),
            'strong', 'b' => $this->wrap($n, '**'),
            'em', 'i' => $this->wrap($n, '*'),
            'img' => ($alt = trim($n->getAttribute('alt'))) !== '' ? '!['.$alt.']('.$n->getAttribute('src').')' : '',
            'div', 'section', 'article', 'header', 'footer', 'aside', 'figure', 'table', 'tbody', 'thead', 'tr' => "\n".$this->blocks($n)."\n",
            'td', 'th' => ' '.trim($this->inline($n)).' ',
            default => $this->blocks($n),
        };
    }

    private function inline(DOMNode $n): string
    {
        return preg_replace('/[ \t]+/u', ' ', str_replace("\n\n", "\x01\n", $this->blocks($n)));
    }

    private function wrap(DOMElement $n, string $mark): string
    {
        $text = trim($this->inline($n));

        return $text === '' ? '' : $mark.$text.$mark.' ';
    }

    private function link(DOMElement $n): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $this->inline($n)));
        $href = trim($n->getAttribute('href'));
        if ($text === '' || $href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            return $text;
        }

        return '['.str_replace(['[', ']'], ['\[', '\]'], $text).']('.str_replace([' ', ')'], ['%20', '%29'], $href).')';
    }

    private function list(DOMElement $n, bool $ordered): string
    {
        $lines = [];
        $i = 1;
        foreach ($n->childNodes as $li) {
            if ($li instanceof DOMElement && strtolower($li->tagName) === 'li' && ($text = trim(preg_replace('/\s*\n\s*/u', ' ', $this->inline($li)))) !== '') {
                $lines[] = ($ordered ? ($i++).'. ' : '- ').$text;
            }
        }

        return implode("\n", $lines);
    }

    /** Collapse blank lines and stray spaces; \x01 marks a kept line break (a poem's line), written as two spaces. */
    private static function tidy(string $md, bool $final = true): string
    {
        $md = preg_replace('/^[ \t]+|[ \t]+$/mu', '', $md);
        $md = preg_replace("/\x01+(?=\n\s*\n|\s*$)/u", '', $md);
        $md = preg_replace("/\n{3,}/u", "\n\n", $md);

        $md = preg_replace('/[ \t]*\x01[ \t]*/u', "\x01", $md);

        return trim($final ? str_replace("\x01", '  ', $md) : $md);
    }
}
