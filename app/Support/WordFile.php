<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * D237 (owner: "add the 8 feature recommendations", among them reading a Word file into the form; writers keep
 * writing that the form is hard and that part of a piece was left out when copying). A .docx is a zip of XML: the
 * text is read out paragraph by paragraph, with bold and italic, and put into the form's writing box for the writer
 * to check before sending. Nothing of the file is kept. Old .doc files and PDFs are not read: their Bengali comes out
 * broken more often than not. docs/SECURITY.md §5: size limits before anything is opened, no external entities,
 * the result is escaped here and sanitised again when the piece is saved.
 */
final class WordFile
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_XML = 12 * 1024 * 1024;

    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** @return array{html: string, paragraphs: int} */
    public static function read(UploadedFile $file): array
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['word' => $m]);
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            $fail('ফাইলটি ৫ MB-এর মধ্যে হতে হবে।');
        }
        if (strtolower($file->getClientOriginalExtension()) !== 'docx' || ! class_exists(\ZipArchive::class)) {
            $fail('শুধু ওয়ার্ডের .docx ফাইল নেওয়া যায়। পুরনো .doc বা পিডিএফ হলে লেখাটা কপি করে নিচের ঘরে বসিয়ে দিন।');
        }
        $zip = new \ZipArchive;
        if ($zip->open($file->getRealPath(), \ZipArchive::RDONLY) !== true) {
            $fail('ফাইলটি খোলা গেল না। ওয়ার্ডে খুলে “Save as .docx” করে আবার দিন।');
        }
        $stat = $zip->statName('word/document.xml');
        if (! $stat || $stat['size'] > self::MAX_XML) {
            $zip->close();
            $fail('ফাইলটি খোলা গেল না। লেখাটা কপি করে নিচের ঘরে বসিয়ে দিন।');
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $doc = new \DOMDocument;
        if ($xml === '' || ! @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            $fail('ফাইলটি পড়া গেল না। লেখাটা কপি করে নিচের ঘরে বসিয়ে দিন।');
        }
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('w', self::NS);

        $on = function (\DOMNode $run, string $tag) use ($xp): bool {
            $el = $xp->query('w:rPr/w:'.$tag, $run)->item(0);

            return $el instanceof \DOMElement && ! in_array($el->getAttributeNS(self::NS, 'val'), ['0', 'false', 'none'], true);
        };
        $paras = [];
        $plain = '';
        foreach ($xp->query('//w:body//w:p[not(ancestor::w:txbxContent)]') as $p) {
            $html = '';
            foreach ($xp->query('.//w:r', $p) as $run) {
                $text = '';
                foreach ($run->childNodes as $n) {
                    $text .= match ($n->localName) {
                        't' => e($n->textContent), 'tab' => ' ', 'br', 'cr' => '<br>', default => '',
                    };
                }
                if ($text === '') {
                    continue;
                }
                $plain .= strip_tags($text);
                $text = $on($run, 'b') ? '<strong>'.$text.'</strong>' : $text;
                $html .= $on($run, 'i') ? '<em>'.$text.'</em>' : $text;
            }
            $html = trim(preg_replace('~</(strong|em)><\1>~', '', $html));
            if ($html !== '' || ($paras && end($paras) !== '')) {
                $paras[] = $html;   // one empty line between stanzas is kept, a run of them is not
            }
            if (count($paras) > 4000 || mb_strlen($plain) > 300000) {
                $fail('লেখাটি খুব বড়। ভাগ করে পাঠান।');
            }
        }
        while ($paras && end($paras) === '') {
            array_pop($paras);
        }
        if (mb_strlen(trim($plain)) < 2) {
            $fail('ফাইলে কোনো লেখা পাওয়া গেল না। লেখাটা কপি করে নিচের ঘরে বসিয়ে দিন।');
        }
        // Typed in an old Bijoy font: the file holds Latin letters that only look Bengali in that font.
        $letters = preg_match_all('/\p{L}/u', $plain);
        $bengali = preg_match_all('/[\x{0980}-\x{09FF}]/u', $plain);
        $oldFont = (bool) preg_match('/w:(ascii|hAnsi)="[^"]*(Sutonny|Bijoy|MJ"|AdorshoLipi|Boishakhi|Chandrabati)/i', $xml);
        if ($oldFont && $bengali < 0.2 * max(1, $letters)) {
            $fail('ফাইলটি পুরনো বিজয় ফন্টে লেখা, তাই এখানে পড়া যাচ্ছে না। ইউনিকোডে (অভ্র বা ফোনের বাংলা কি-বোর্ডে) লেখা ফাইল দিন, অথবা লেখাটা নতুন করে টাইপ করুন।');
        }

        return ['html' => implode('', array_map(fn ($p) => '<p>'.($p === '' ? '<br>' : $p).'</p>', $paras)), 'paragraphs' => count(array_filter($paras))];
    }
}
