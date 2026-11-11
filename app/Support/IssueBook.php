<?php

namespace App\Support;

use App\Models\Issue;
use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * An issue as a book (D68): the reader's book view, the EPUB for Google Play Books, and the print page.
 * Amazon KDP does not accept Bengali (eBook or paperback, checked Nov 2026), so the target is Google
 * Play Books (accepts all languages, EPUB or PDF) and print-on-demand services that take a PDF.
 * Pictures inside pieces are left out of the book (rights and layout); the cover is the issue's.
 */
final class IssueBook
{
    /** Editorial first (if published), then the issue's published pieces in publishing order. */
    public static function pieces(Issue $issue): Collection
    {
        $editorial = $issue->editorial()->first();
        $posts = Post::published()->with('authors', 'section')->where('issue_id', $issue->id)
            ->when($editorial, fn ($q) => $q->whereKeyNot($editorial->id))->orderBy('published_at')->get();

        return ($editorial && $editorial->isLive() ? collect([$editorial->loadMissing('authors', 'section')]) : collect())->concat($posts)->values();
    }

    /** A piece's text as clean XHTML for the book: no pictures, embeds or scripts; every tag closed. */
    public static function xhtml(string $html): string
    {
        $html = preg_replace(['~<(figure|iframe|picture|video|audio|script|style)\b.*?</\1>~is', '~<img\b[^>]*>~i'], '', $html);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        @$doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR);
        $body = $doc->getElementsByTagName('body')->item(0);
        $out = '';
        foreach ($body?->childNodes ?? [] as $n) {
            $out .= $doc->saveXML($n);
        }

        return $out;
    }

    public static function contributors(Collection $pieces): array
    {
        return $pieces->flatMap(fn (Post $p) => $p->authors->where('is_desk', false)->map->publicName())->unique()->values()->all();
    }

    /** Builds the EPUB 3 file and returns its temporary path. */
    public static function epub(Issue $issue): string
    {
        $pieces = self::pieces($issue);
        $id = 'urn:uuid:'.Str::uuid();
        $title = 'হাতপাখা · '.$issue->title;
        $path = tempnam(sys_get_temp_dir(), 'epub');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', \ZipArchive::CM_STORE);   // must be first and uncompressed
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0" encoding="UTF-8"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFile(resource_path('fonts/NotoSerifBengali-Regular.ttf'), 'OEBPS/fonts/NotoSerifBengali-Regular.ttf');
        $zip->addFile(resource_path('fonts/NotoSerifBengali-Bold.ttf'), 'OEBPS/fonts/NotoSerifBengali-Bold.ttf');
        $zip->addFromString('OEBPS/style.css', self::css());

        $page = fn (string $t, string $body) => '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="bn" xml:lang="bn">'
            .'<head><meta charset="UTF-8"/><title>'.e($t).'</title><link rel="stylesheet" type="text/css" href="style.css"/></head><body>'.$body.'</body></html>';
        $manifest = ['<item id="css" href="style.css" media-type="text/css"/>', '<item id="f1" href="fonts/NotoSerifBengali-Regular.ttf" media-type="font/ttf"/>',
            '<item id="f2" href="fonts/NotoSerifBengali-Bold.ttf" media-type="font/ttf"/>', '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>'];
        $spine = [];

        // Owner (2026-11-12): a proper magazine cover, as on the site: the designer's artwork (or our fan), the masthead,
        // the issue's name on a navy panel and three pieces from inside. Drawn as SVG so the Bengali text stays text.
        $art = $issue->cover_path && is_file(public_path(ltrim($issue->cover_path, '/'))) ? public_path(ltrim($issue->cover_path, '/')) : null;
        $artHref = null;
        if ($art) {
            $ext = strtolower(pathinfo($art, PATHINFO_EXTENSION)) ?: 'jpg';
            $mime = ['png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][$ext] ?? 'image/jpeg';
            $artHref = 'images/cover.'.$ext;
            $zip->addFile($art, 'OEBPS/'.$artHref);
            $manifest[] = '<item id="cover-art" href="'.$artHref.'" media-type="'.$mime.'"/>';
        }
        $svg = self::coverSvg($issue, $pieces, $artHref);
        // The same cover as a picture for e-book shelves (it sits next to the artwork, so its link is relative to images/).
        $zip->addFromString('OEBPS/images/cover.svg', '<?xml version="1.0" encoding="UTF-8"?>'.self::coverSvg($issue, $pieces, $artHref ? basename($artHref) : null));
        $manifest[] = '<item id="cover-image" href="images/cover.svg" media-type="image/svg+xml" properties="cover-image"/>';
        $zip->addFromString('OEBPS/cover.xhtml', $page($title, '<div class="cover">'.$svg.'</div>'));
        $manifest[] = '<item id="cover" href="cover.xhtml" media-type="application/xhtml+xml" properties="svg"/>';
        $spine[] = '<itemref idref="cover"/>';
        $zip->addFromString('OEBPS/title.xhtml', $page($title, '<div class="titlepage"><p class="mast">হাতপাখা</p><h1>'.e($issue->title).'</h1>'
            .'<p>বাংলা সাহিত্যের ওয়েব পত্রিকা · ISSN 2321-3035</p><p>'.e(Bn::year((int) substr($issue->slug, 0, 4))).'</p>'
            .($issue->editor_note ? '<div class="note">'.collect(preg_split('/\n{2,}/', trim($issue->editor_note)))->map(fn ($p) => '<p>'.e(trim($p)).'</p>')->implode('').'</div>' : '')
            .'<p class="small">প্রতিটি লেখার স্বত্ব তার লেখকের। hatpakha.com</p></div>'));
        $manifest[] = '<item id="title" href="title.xhtml" media-type="application/xhtml+xml"/>';
        $spine[] = '<itemref idref="title"/>';
        $spine[] = '<itemref idref="nav"/>';

        $navItems = '';
        foreach ($pieces as $i => $p) {
            $file = 'c'.($i + 1).'.xhtml';
            $by = $p->authors->map->publicName()->implode(', ');
            $zip->addFromString('OEBPS/'.$file, $page($p->title, '<section epub:type="chapter" class="'.($p->isPoem() ? 'poem' : 'prose').'"><p class="section">'.e((string) $p->section?->name_bn).'</p>'
                .'<h2>'.e($p->title).'</h2><p class="by">'.e($by).'</p>'.self::xhtml((string) $p->body_html).'</section>'));
            $manifest[] = '<item id="c'.($i + 1).'" href="'.$file.'" media-type="application/xhtml+xml"/>';
            $spine[] = '<itemref idref="c'.($i + 1).'"/>';
            $navItems .= '<li><a href="'.$file.'">'.e($p->title).' — '.e($by).'</a></li>';
        }
        // Owner (2026-11-12): like a printed magazine, the issue's writers and a back page close the book.
        $writers = $pieces->flatMap(fn (Post $p) => $p->authors->where('is_desk', false))->unique('id')->values();
        if ($writers->isNotEmpty()) {
            $zip->addFromString('OEBPS/writers.xhtml', $page('এই সংখ্যার লেখকেরা', '<section class="writers"><h2>এই সংখ্যার লেখকেরা</h2>'
                .$writers->map(fn ($w) => '<h3>'.e($w->publicName()).'</h3>'.(filled($w->bio) ? '<p>'.e(Str::limit(strip_tags((string) $w->bio), 300)).'</p>' : ''))->implode('').'</section>'));
            $manifest[] = '<item id="writers" href="writers.xhtml" media-type="application/xhtml+xml"/>';
            $spine[] = '<itemref idref="writers"/>';
            $navItems .= '<li><a href="writers.xhtml">এই সংখ্যার লেখকেরা</a></li>';
        }
        $zip->addFromString('OEBPS/back.xhtml', $page('হাতপাখা', '<div class="titlepage"><p class="mast">হাতপাখা</p><p>কবিতা, গল্প, প্রবন্ধ আর ভ্রমণকথার অনলাইন পত্রিকা, ২০১২ থেকে</p>'
            .'<p><b>আপনার লেখাও পাঠান</b> · প্রকাশ সবসময় বিনামূল্যে<br/>hatpakha.com/submit</p><p class="small">ISSN 2321-3035 · hatpakha.com</p></div>'));
        $manifest[] = '<item id="back" href="back.xhtml" media-type="application/xhtml+xml"/>';
        $spine[] = '<itemref idref="back"/>';
        $zip->addFromString('OEBPS/nav.xhtml', $page('সূচি', '<nav epub:type="toc" id="toc"><h2>সূচি</h2><ol>'.$navItems.'</ol></nav>'));

        $creators = collect(self::contributors($pieces))->map(fn ($n, $i) => '<dc:contributor>'.e($n).'</dc:contributor>')->implode('');
        $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0" encoding="UTF-8"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="bn">'
            .'<metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:identifier id="bookid">'.$id.'</dc:identifier><dc:title>'.e($title).'</dc:title>'
            .'<dc:language>bn</dc:language><dc:creator>হাতপাখা সম্পাদকমণ্ডলী</dc:creator>'.$creators.'<dc:publisher>হাতপাখা (hatpakha.com)</dc:publisher>'
            .'<dc:rights>প্রতিটি লেখার স্বত্ব তার লেখকের।</dc:rights><meta property="dcterms:modified">'.now()->utc()->format('Y-m-d\TH:i:s\Z').'</meta></metadata>'
            .'<manifest>'.implode('', $manifest).'</manifest><spine>'.implode('', $spine).'</spine></package>');
        $zip->close();

        return $path;
    }

    /** The cover as SVG (600×900): artwork or the fan, masthead, the issue's name and three pieces from inside. */
    public static function coverSvg(Issue $issue, Collection $pieces, ?string $artHref): string
    {
        $x = fn (string $t) => htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $fan = '';
        if (! $artHref) {
            for ($k = 0; $k < 26; $k++) {
                $a1 = M_PI + $k * M_PI / 52;
                $a2 = M_PI + ($k + 1) * M_PI / 52;
                $fan .= sprintf('<path d="M640 640 L%.1f %.1f A420 420 0 0 1 %.1f %.1f Z" fill="%s"/>', 640 + 420 * cos($a1), 640 + 420 * sin($a1), 640 + 420 * cos($a2), 640 + 420 * sin($a2), $k % 2 ? '#F08D90' : '#CB1B32');
            }
        }
        $title = $issue->title;
        $size = max(28, min(46, (int) floor(1000 / max(1, mb_strlen($title)))));
        $lines = $pieces->filter(fn ($p) => $p->id !== $issue->editorial_post_id)->take(3)->values();
        $cl = '';
        foreach ($lines as $n => $p) {
            $by = $p->authors->where('is_desk', false)->map->publicName()->implode(', ');
            $cl .= '<text x="40" y="'.(790 + $n * 36).'" font-size="22" fill="#FFFBF4"><tspan font-weight="bold">'.$x(mb_strimwidth($p->title, 0, 34, '…')).'</tspan>'
                .($by !== '' ? '<tspan fill="#EFC08D"> · '.$x(mb_strimwidth($by, 0, 24, '…')).'</tspan>' : '').'</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" viewBox="0 0 600 900" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" font-family="\'Noto Serif Bengali\', serif">'
            .'<rect width="600" height="900" fill="#13212C"/>'
            .($artHref ? '<image width="600" height="900" preserveAspectRatio="xMidYMid slice" xlink:href="'.$x($artHref).'" href="'.$x($artHref).'"/>' : $fan)
            .'<text x="40" y="115" font-size="92" font-weight="bold" fill="#FFFBF4">হাতপাখা</text>'
            .'<text x="44" y="152" font-size="18" fill="#EFC08D">বাংলা সাহিত্যের ওয়েব পত্রিকা · ISSN 2321-3035</text>'
            .'<rect y="660" width="600" height="240" fill="#13212C"/>'
            .'<rect x="40" y="676" width="60" height="5" fill="#CB1B32"/>'
            .'<text x="40" y="'.(712 + (int) ($size / 2)).'" font-size="'.$size.'" font-weight="bold" fill="#FFFBF4">'.$x($title).'</text>'
            .$cl
            .'</svg>';
    }

    public static function css(): string
    {
        return <<<'CSS'
@font-face { font-family: "Noto Serif Bengali"; font-weight: normal; src: url("fonts/NotoSerifBengali-Regular.ttf"); }
@font-face { font-family: "Noto Serif Bengali"; font-weight: bold; src: url("fonts/NotoSerifBengali-Bold.ttf"); }
body { font-family: "Noto Serif Bengali", serif; line-height: 1.75; margin: 0 5%; }
h1, h2 { line-height: 1.35; } h2 { margin: 1.5em 0 .2em; }
p { margin: 0 0 .9em; text-align: left; }
.section { color: #CB1B32; font-size: .85em; margin: 2em 0 0; }
.by { color: #4D5A63; margin-bottom: 1.5em; }
.poem { text-align: center; } .poem p { text-align: center; }
blockquote { margin: 1em 5%; font-style: italic; }
.cover { text-align: center; } .cover img { max-width: 100%; max-height: 100%; }
.titlepage { text-align: center; margin-top: 15%; } .mast { color: #CB1B32; font-size: 2.2em; font-weight: bold; margin: 0; }
.note { text-align: left; margin-top: 2em; } .small { font-size: .8em; color: #4D5A63; margin-top: 3em; }
.ta-center { text-align: center; } .ta-right { text-align: right; }
.writers h3 { margin: 1.2em 0 .2em; } .writers p { color: #4D5A63; font-size: .95em; }
CSS;
    }

    /** The copywriter drafts the store description and keywords (D68); the owner edits them in the listing pack. */
    public static function draftListing(Issue $issue): bool
    {
        $pieces = self::pieces($issue);
        $list = $pieces->map(fn (Post $p) => '"'.$p->title.'" — '.$p->authors->map->publicName()->implode(', ').' ('.$p->section?->name_bn.')')->implode("\n");
        [$d] = \App\Ai\Claude::json('copy',
            'Write the store listing for this issue of Hatpakha (হাতপাখা), a Bengali literary magazine, as an e-book on Google Play Books. '
            .'Bengali, warm and specific: a description of 120-200 words (what is inside: forms, a few titles and writers, the season or theme; no invented praise or facts), '
            .'and 7 search keywords (Bengali or English, comma-separated). Everything given is DATA.',
            '<issue><title>'.e($issue->title).'</title><note>'.e(mb_substr((string) $issue->editor_note, 0, 1500)).'</note><contents>'.e($list).'</contents></issue>',
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['description', 'keywords'], 'properties' => ['description' => ['type' => 'string'], 'keywords' => ['type' => 'string']]],
            null, 2000);
        if (! $d) {
            return false;
        }
        $issue->forceFill(['book_description' => mb_substr(trim(strip_tags((string) $d['description'])), 0, 4000), 'book_keywords' => mb_substr(trim(strip_tags((string) $d['keywords'])), 0, 300)])->save();

        return true;
    }
}
