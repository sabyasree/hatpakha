<?php

namespace App\Support;

use App\Models\Issue;
use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * D277: what an issue's cover says, worked out once for every place it is drawn: the magazine, the issue page, the
 * lists, the desk, the print page and the e-book. Two styles (owner, 2026-11-24: "1st and 2nd both are good"):
 * "fan" (ক পাখা, the picture cut into the fan on a flat colour) and "full" (খ পূর্ণ ছবি, the picture across the cover).
 * An issue keeps one style, so it looks the same everywhere: monthly issues take the fan, festival and special issues
 * the full picture, unless the owner picks another in the desk. Only real data: the title, kind, month and year,
 * the volume (বর্ষ, counted from 2012), the ISSN and real pieces with their writers; never the desk as a writer.
 */
final class IssueCover
{
    public const STYLES = ['fan' => 'পাখা (ছবি হাতপাখার আকারে)', 'full' => 'পূর্ণ ছবি (ছবি পুরো প্রচ্ছদ জুড়ে)'];

    public const MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    public const KINDS = ['special' => 'বিশেষ সংখ্যা', 'monthly' => 'মাসিক সংখ্যা', 'festival' => 'উৎসব সংখ্যা'];

    public const ISSN = '2321-3035';

    private static int $uid = 0;

    /** monthly (a year-month address), festival, or special (any other address); a collection counts as special. */
    public static function kind(object $issue): string
    {
        if ($issue instanceof Issue && in_array($issue->kind, ['festival', 'special'], true)) {
            return $issue->kind;
        }

        return preg_match('/^\d{4}-\d{2}$/', (string) ($issue->slug ?? '')) ? 'monthly' : 'special';
    }

    public static function style(object $issue): string
    {
        $chosen = $issue instanceof Issue ? $issue->cover_style : null;

        return isset(self::STYLES[$chosen]) ? $chosen : (self::kind($issue) === 'monthly' ? 'fan' : 'full');
    }

    /**
     * Everything a cover shows. $pieces: the issue's pieces (editorial left out here), for the cover lines; none for
     * the small cards in lists. $preview: the desk may show the designer's proposed picture before approval.
     */
    public static function data(object $issue, ?Collection $pieces = null, bool $preview = false): array
    {
        $kind = self::kind($issue);
        $style = self::style($issue);
        if (preg_match('/^(\d{4})-(\d{2})$/', (string) ($issue->slug ?? ''), $ym)) {
            [$y, $m] = [(int) $ym[1], (int) $ym[2]];
        } else {
            $at = $issue->published_at ? \Illuminate\Support\Carbon::parse($issue->published_at)->setTimezone('Asia/Kolkata') : now('Asia/Kolkata');
            [$y, $m] = [$at->year, $at->month];
        }
        $title = trim((string) $issue->title);
        $main = preg_replace('/\s+সংখ্যা$/u', '', $title);   // "ফিরে আসা সংখ্যা": the kind label says "সংখ্যা"
        $main = $main !== '' ? $main : $title;
        $vol = max(1, $y - 2011);   // the magazine began in 2012
        $month = self::MONTHS[$m - 1].' '.Bn::num((string) $y);
        $art = $issue->cover_path ?: ($preview ? ($issue->proposed_cover_path ?? null) : null);
        $label = $issue instanceof MagazineBook ? 'সংকলন' : self::KINDS[$kind];
        $lines = self::lines($pieces ?? collect(), $style === 'full' ? 3 : 3);
        if ($style === 'full' && $lines && mb_strlen($lines[0]['t']) > 20) {
            $lines = array_slice($lines, 0, 2);   // a two-line lead leaves room for one more line
        }

        return [
            'style' => $style, 'kind' => $kind, 'label' => $label, 'title' => $title, 'main' => $main,
            'hero' => self::hero($main, $style), 'vol' => Bn::num((string) $vol), 'no' => $kind === 'monthly' ? Bn::num((string) $m) : null,
            'month' => self::MONTHS[$m - 1], 'year' => Bn::num((string) $y), 'monthYear' => $month,
            'ear' => 'বর্ষ '.Bn::num((string) $vol).' · '.($kind === 'monthly' ? 'সংখ্যা '.Bn::num((string) $m) : $month),
            'sub' => $label.' · '.($kind === 'monthly' ? Bn::num((string) $y) : $month),
            'art' => $art ? asset(ltrim($art, '/')) : null, 'artPath' => $art, 'lines' => $lines,
        ];
    }

    /** The hero title's size (cqw of the cover's width) by its length, so it stays on one line at any size. */
    public static function hero(string $main, string $style): float
    {
        $n = mb_strlen($main);
        $steps = $style === 'full' ? [6 => 15, 9 => 13, 13 => 10.5, 18 => 8.4] : [6 => 17, 9 => 14.5, 13 => 11.5, 18 => 9];
        foreach ($steps as $max => $size) {
            if ($n <= $max) {
                return $size;
            }
        }

        return $style === 'full' ? 7 : 7.4;
    }

    /**
     * Up to three cover lines: the lead story first (a piece with a real writer, not a serial), then a serial (by
     * its name, once), then pieces from other sections. A piece signed only by the desk comes last and shows no name.
     */
    public static function lines(Collection $pieces, int $max = 3): array
    {
        $seen = [];
        $all = [];
        foreach ($pieces as $p) {
            if (! $p instanceof Post || $p->section?->slug === 'editorial') {
                continue;
            }
            if ($p->serial_id) {
                if (isset($seen[$p->serial_id])) {
                    continue;
                }
                $seen[$p->serial_id] = true;
            }
            $real = $p->authors->where('is_desk', false)->values();
            $all[] = [
                't' => $p->serial_id && $p->serial ? $p->serial->title : $p->title,
                'w' => $real->isEmpty() ? '' : $real->first()->publicName().($real->count() > 1 ? ' ও অন্যরা' : ''),
                'serial' => (bool) $p->serial_id, 'desk' => $real->isEmpty(), 'sec' => $p->section_id, 'id' => $p->id,
            ];
        }
        $pick = [];
        $take = function (callable $ok) use (&$pick, $all, $max) {
            foreach ($all as $l) {
                if (count($pick) < $max && ! isset($pick[$l['id']]) && $ok($l, $pick)) {
                    $pick[$l['id']] = $l;
                }
            }
        };
        $take(fn ($l, $p) => ! $l['desk'] && ! $l['serial'] && $p === []);   // the lead
        $take(fn ($l, $p) => ! $l['desk'] && $l['serial'] && ! collect($p)->contains('serial', true));
        $take(fn ($l, $p) => ! $l['desk'] && ! collect($p)->contains('sec', $l['sec']));
        $take(fn ($l) => ! $l['desk']);
        $take(fn ($l) => true);

        return array_values(array_map(fn ($l) => ['t' => $l['t'], 'w' => $l['w'], 'serial' => $l['serial']], $pick));
    }

    /**
     * The fan: a half disc of radius 98 in a 200×102 box. With a picture, the picture's middle band is cut into it and
     * thin rays in the background colour make the slats; without one, slats in two colours. $href: a URL or a file name.
     */
    public static function fanSvg(?string $href, string $kind, bool $bleed = false): string
    {
        $bg = ['special' => '#CB1B32', 'monthly' => '#13212C', 'festival' => '#EFC08D'][$kind];
        $cols = $kind === 'special' ? ['#FFFBF4', '#EFC08D'] : ['#CB1B32', '#F08D90'];
        $hub = ['special' => '#13212C', 'monthly' => '#EFC08D', 'festival' => '#13212C'][$kind];
        $id = 'hpfan'.(++self::$uid);
        $pt = fn (float $a) => sprintf('%.2f %.2f', 100 + 98 * cos($a), 100 + 98 * sin($a));
        $s = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 200 102" aria-hidden="true">';
        if ($href && ! $bleed) {
            $h = htmlspecialchars($href, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $s .= '<defs><clipPath id="'.$id.'"><path d="M2 100 A98 98 0 0 1 198 100 Z"/></clipPath></defs>'
                .'<image href="'.$h.'" xlink:href="'.$h.'" x="2" y="2" width="196" height="98" preserveAspectRatio="xMidYMid slice" clip-path="url(#'.$id.')"/>';
            for ($k = 1; $k < 12; $k++) {
                $s .= '<path d="M100 100 L'.$pt(M_PI + M_PI * $k / 12).'" stroke="'.$bg.'" stroke-width="1.3"/>';
            }
        } else {
            for ($k = 0; $k < 12; $k++) {
                $s .= '<path d="M100 100 L'.$pt(M_PI + M_PI * $k / 12).' A98 98 0 0 1 '.$pt(M_PI + M_PI * ($k + 1) / 12).' Z" fill="'.$cols[$k % 2].'"/>';
            }
        }

        return $s.'<path d="M74 100 A26 26 0 0 1 126 100 Z" fill="'.$hub.'"/></svg>';
    }

    /** The magazine's ISSN barcode (EAN-13: 977, the ISSN without its check digit, 00, the check digit). */
    public static function barcodeDigits(): string
    {
        $d = array_map('intval', str_split('977'.substr(str_replace('-', '', self::ISSN), 0, 7).'00'));
        $sum = 0;
        foreach ($d as $i => $n) {
            $sum += $n * ($i % 2 ? 3 : 1);
        }

        return implode('', $d).((10 - $sum % 10) % 10);
    }

    public static function barcodeSvg(): string
    {
        $code = substr(self::barcodeDigits(), 0, 12);
        $l = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
        $r = array_map(fn ($c) => strtr($c, '01', '10'), $l);
        $g = array_map('strrev', $r);
        $par = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];
        $d = array_map('intval', str_split($code));
        $sum = 0;
        foreach ($d as $i => $n) {
            $sum += $n * ($i % 2 ? 3 : 1);
        }
        $d[12] = (10 - $sum % 10) % 10;
        $bits = '101';
        for ($i = 1; $i < 7; $i++) {
            $bits .= ($par[$d[0]][$i - 1] === 'L' ? $l : $g)[$d[$i]];
        }
        $bits .= '01010';
        for ($i = 7; $i < 13; $i++) {
            $bits .= $r[$d[$i]];
        }
        $bits .= '101';
        $rects = '';
        foreach (str_split($bits) as $i => $b) {
            if ($b === '1') {
                $rects .= '<rect x="'.$i.'" y="0" width="1" height="'.($i < 3 || ($i >= 45 && $i < 50) || $i >= 92 ? 40 : 36).'"/>';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 95 40" preserveAspectRatio="none" fill="#13212C" aria-hidden="true">'.$rects.'</svg>';
    }
}
