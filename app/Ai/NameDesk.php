<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\NamePage;
use App\Models\Setting;
use App\Support\NamePlan;
use Carbon\CarbonImmutable;

/**
 * D316 (owner: baby names, three letter pages a week, automatic once checked). Inside the janun job:
 *   - a due page goes live (Tuesday, Thursday, Saturday, 10am India);
 *   - a page being checked: up to CHECK_PER_RUN names are looked up in the dictionaries;
 *   - otherwise, at most one new page a day is written, and only while fewer than three wait for their day.
 * A name is printed only when at least two of four sources have it and give a meaning like ours: the Bangla Academy's
 * Bengali-English dictionary (accessibledictionary.gov.bd, a Bangladesh government site), English Wiktionary (the Bengali
 * entry, or the Arabic, Persian or Sanskrit word it says the name comes from), Bengali Wiktionary, and Behind the Name
 * (a name dictionary with sourced histories; D320, for Arabic and Persian names, which ordinary dictionaries rarely list).
 * Names no two sources know are left out, never guessed.
 */
final class NameDesk
{
    public const CHECK_PER_RUN = 5;

    public const DICTIONARIES = [
        'accessibledictionary.gov.bd' => 'https://accessibledictionary.gov.bd/bengali-to-english/?q=',
        'en.wiktionary.org' => 'https://en.wiktionary.org/wiki/',   // read as wikitext (?action=raw): only the Bengali section's definition lines count
        'bn.wiktionary.org' => 'https://bn.wiktionary.org/wiki/',
        'behindthename.com' => 'https://www.behindthename.com/name/',   // by the English spelling
    ];

    /** Wiktionary's codes for the languages names come from, and the heading of that language's section. */
    private const ORIGINS = ['ar' => 'Arabic', 'fa' => 'Persian', 'sa' => 'Sanskrit'];

    private const STOP_EN = ['name', 'with', 'that', 'which', 'from', 'also', 'one', 'who', 'the', 'and', 'for', 'having', 'being', 'like', 'very', 'person', 'given'];

    private const STOP_BN = ['অর্থ', 'যিনি', 'যার', 'এমন', 'একটি', 'নাম', 'এবং', 'অথবা', 'বা', 'মতো', 'থেকে', 'সঙ্গে'];

    /** Pages whose day has come go live (no AI needed, so this runs even while the AI is paused). */
    public static function publishDue(): int
    {
        $due = NamePage::where('status', 'scheduled')->where('publish_at', '<=', now())->get();
        foreach ($due as $p) {
            $p->forceFill(['status' => 'live'])->save();
            AuditLog::record('names.live', null, ['page' => NamePlan::slug($p->gender, $p->letter)]);
            \App\Support\IndexNow::ping([$p->url(), \App\Support\Url::to('/janun/names')]);
        }

        return $due->count();
    }

    public static function run(CarbonImmutable $now): bool
    {
        if ($p = NamePage::where('status', 'checking')->orderBy('id')->first()) {
            self::check($p, $now);

            return true;
        }
        if (Setting::get('names.day') === $now->toDateString() || NamePage::where('status', 'scheduled')->count() >= 3) {
            return false;
        }
        $done = NamePage::whereNotIn('status', ['planned'])->get()->map(fn ($p) => $p->gender.'|'.$p->letter)->all();
        $failed = NamePage::where('status', 'planned')->where('tries', '>=', 3)->get()->map(fn ($p) => $p->gender.'|'.$p->letter)->all();
        foreach (NamePlan::order() as [$g, $l]) {
            if (! in_array("$g|$l", $done, true) && ! in_array("$g|$l", $failed, true)) {
                Setting::put('names.day', $now->toDateString());
                self::draft(NamePage::firstOrCreate(['gender' => $g, 'letter' => $l]));

                return true;
            }
        }

        return false;
    }

    public static function draft(NamePage $p): bool
    {
        $str = ['type' => 'string'];
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['intro', 'names'], 'properties' => ['intro' => $str,
            'names' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['bn', 'variants', 'roman', 'meaning_bn', 'meaning_en', 'origin'],
                'properties' => ['bn' => $str, 'variants' => ['type' => 'array', 'items' => $str], 'roman' => $str, 'meaning_bn' => $str, 'meaning_en' => $str, 'origin' => ['type' => 'string', 'enum' => ['সংস্কৃত', 'বাংলা', 'আরবি', 'ফারসি', 'অন্য']]]]]]];
        $letter = NamePlan::LETTERS[$p->letter];
        [$gBn, $gEn] = NamePlan::GENDERS[$p->gender];
        [$d, $err] = Claude::json('janun',
            'You list Bengali given names for the "জেনে রাখুন" section of হাতপাখা, a Bengali literary magazine, for parents in West Bengal, Bangladesh and abroad. '
            .'Give as many real, well-attested '.($p->gender === 'boy' ? 'boys\'' : 'girls\'').' names beginning with the letter given as Bengalis actually use, up to 90: Sanskrit-derived and Bengali names, AND the Arabic and Persian names Bengali Muslims use (both, as many as the letter really has), and secular names. '
            .'Each with its established meaning as name dictionaries and word dictionaries give it. Never invent a name or a meaning; leave out any you are unsure of. '
            .'bn = the name in Bengali script, standard spelling; variants = up to two other common Bengali spellings (e.g. ফাতিমা for ফাতেমা), else empty; roman = the usual English spelling (one, e.g. Fatima); meaning_bn = the meaning in two to six plain Bengali words; meaning_en = the same in two to six English words, as a dictionary gives it; origin = the language it comes from. '
            .'intro = two or three sentences for parents about names that begin with this letter (sounds, kinds of meanings); no claims about popularity or numbers.',
            '<page letter="'.e($letter).'" gender="'.e($gEn).'">'.e($letter.' দিয়ে '.$gBn.' নাম').'</page>', $schema, (string) config('ai.anthropic.review_model'), 24000);
        $p->tries++;
        if (! is_array($d)) {
            $p->save();
            AuditLog::record('names.draft_failed', null, ['page' => NamePlan::slug($p->gender, $p->letter), 'error' => mb_substr((string) $err, 0, 200)]);

            return false;
        }
        $names = collect((array) ($d['names'] ?? []))
            ->map(fn ($n) => ['bn' => trim((string) ($n['bn'] ?? '')), 'roman' => mb_substr(trim((string) ($n['roman'] ?? '')), 0, 40), 'meaning_bn' => mb_substr(trim((string) ($n['meaning_bn'] ?? '')), 0, 80),
                'meaning_en' => mb_substr(trim((string) ($n['meaning_en'] ?? '')), 0, 80), 'origin' => (string) ($n['origin'] ?? ''),
                'variants' => array_slice(array_values(array_filter((array) ($n['variants'] ?? []), fn ($v) => is_string($v) && preg_match('/^[\x{0980}-\x{09FF}\x{200C}\x{200D}]+$/u', $v))), 0, 2), 'status' => 'new', 'found' => []])
            ->filter(fn ($n) => $n['bn'] !== '' && str_starts_with(\Normalizer::normalize($n['bn'], \Normalizer::FORM_C) ?: $n['bn'], $letter) && preg_match('/^[\x{0980}-\x{09FF}\x{200C}\x{200D}]+$/u', $n['bn']))
            ->unique('bn')->take(100)->values()->all();
        $p->forceFill(['names' => $names, 'intro' => trim((string) ($d['intro'] ?? '')) ?: null, 'status' => 'checking'])->save();

        return true;
    }

    public static function check(NamePage $p, CarbonImmutable $now): void
    {
        $names = (array) $p->names;
        $done = 0;
        foreach ($names as $i => $n) {
            if (($n['status'] ?? 'new') !== 'new') {
                continue;
            }
            if ($done++ >= self::CHECK_PER_RUN) {
                break;
            }
            $found = [];
            $left = count(self::DICTIONARIES);
            foreach (array_keys(self::DICTIONARIES) as $host) {
                if (count($found) >= 2 || count($found) + $left < 2) {
                    break;   // the answer cannot change
                }
                $left--;
                if (self::lookup($host, $n)) {
                    $found[] = $host;
                }
            }
            $names[$i]['found'] = $found;
            $names[$i]['status'] = count($found) >= 2 ? 'ok' : 'no';
        }
        $p->names = $names;
        $p->checked_at = now();
        if (collect($names)->contains('status', 'new')) {
            $p->save();

            return;
        }
        $ok = collect($names)->where('status', 'ok')->count();
        if ($ok >= NamePlan::MIN_NAMES) {
            $p->forceFill(['status' => 'scheduled', 'publish_at' => self::nextSlot($now)])->save();
        } else {
            $p->forceFill(['status' => 'held'])->save();
        }
        AuditLog::record($ok >= NamePlan::MIN_NAMES ? 'names.scheduled' : 'names.held', null, ['page' => NamePlan::slug($p->gender, $p->letter), 'ok' => $ok, 'of' => count($names)]);
    }

    /** Opens the source's page(s) for this name and asks gives(). */
    public static function lookup(string $host, array $n): bool
    {
        $spellings = array_values(array_unique(array_filter(array_merge([$n['bn']], (array) ($n['variants'] ?? [])))));
        if ($host === 'behindthename.com') {
            $slug = strtolower((string) preg_replace('/[^A-Za-z]/', '', (string) strtok((string) ($n['roman'] ?? ''), '/,')));
            $text = $slug !== '' ? JanunDesk::fetchText(self::DICTIONARIES[$host].$slug) : null;

            return $text !== null && self::gives($host, $text, $n);
        }
        if ($host === 'accessibledictionary.gov.bd') {
            $text = JanunDesk::fetchText(self::DICTIONARIES[$host].rawurlencode($n['bn']));

            return $text !== null && self::gives($host, $text, $n);
        }
        foreach ($spellings as $sp) {
            $raw = JanunDesk::fetchText(self::DICTIONARIES[$host].rawurlencode($sp).'?action=raw');
            if ($raw === null) {
                continue;
            }
            if (self::gives($host, $raw, $n)) {
                return true;
            }
            // the Bengali entry names the word it comes from: {{bor|bn|ar|فاطمة}}; that word's own entry gives the meaning
            if ($host === 'en.wiktionary.org' && preg_match('/\{\{(?:bor\+?|lbor|der\+?|inh\+?)\|bn\|(ar|fa|sa)\|([^|}]+)/u', $raw, $m)) {
                $word = trim((string) preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $m[2]));
                $src = $word !== '' ? JanunDesk::fetchText(self::DICTIONARIES[$host].rawurlencode($word).'?action=raw') : null;
                if ($src !== null && self::gives($host, $src, $n, self::ORIGINS[$m[1]])) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * Does this dictionary have the name as an entry, with a meaning like ours? Only the entry's own definition is read
     * (never menus or other words' entries), words are matched whole, and the meaning must agree: the first main word of
     * our English meaning, or at least half of its main words, or a whole Bengali word of our Bengali meaning.
     */
    public static function gives(string $host, string $text, array $n, ?string $lang = null): bool
    {
        $nfc = fn ($t) => \Normalizer::normalize((string) $t, \Normalizer::FORM_C) ?: (string) $t;
        $text = $nfc($text);
        $name = $nfc($n['bn']);
        $defs = '';
        if ($host === 'accessibledictionary.gov.bd') {
            // "Bengali Word" headword(s) "English definition" … until the next "Bengali Word"; a headword may list variants (জ্যোতি, জ্যোতিঃ)
            preg_match_all('/Bengali Word\s*([^\n]*?)\s*English definition(.*?)(?=Bengali Word|$)/su', $text, $m, PREG_SET_ORDER);
            $names = array_map($nfc, array_merge([$n['bn']], (array) ($n['variants'] ?? [])));
            foreach ($m as $e) {
                if (array_intersect($names, array_map('trim', explode(',', $nfc($e[1])))) !== []) {
                    $defs = $e[2];
                    break;
                }
            }
        } elseif ($host === 'behindthename.com') {
            // the "Meaning & History" part only (not related names, popularity or the menus)
            if (! preg_match('/Meaning\s*&\s*History(.*?)(?:Related Names|Name Days|Popularity|$)/su', $text, $mh)) {
                return false;
            }
            $defs = $mh[1];
        } else {
            // wikitext: the Bengali section (==Bengali== or =={{ভাষা|bn}}== / ==বাংলা==) up to the next language, its "# " lines only
            $heading = $lang !== null ? preg_quote($lang, '/') : '(?:Bengali|বাংলা|\{\{ভাষা\|bn\}\})';
            if (! preg_match('/^==\s*'.$heading.'\s*==\s*$(.*?)(?=^==[^=]|\z)/msu', $text, $sec)) {
                return false;
            }
            preg_match_all('/^#(?![:*])\s*(.+)$/mu', $sec[1], $lines);
            $defs = preg_replace(['/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', '/\{\{[^}]*\}\}/u'], ['$1', ' '], implode("\n", $lines[1]));
        }
        if (trim((string) $defs) === '') {
            return false;
        }
        $defs = mb_strtolower($defs);
        $en = collect(preg_split('/[^a-z]+/', mb_strtolower((string) $n['meaning_en'])))->filter(fn ($w) => strlen($w) >= 3 && ! in_array($w, self::STOP_EN, true))->values();
        $stem = fn ($w) => strlen($w) > 5 && str_ends_with($w, 'ing') ? substr($w, 0, -3) : (strlen($w) > 4 && str_ends_with($w, 'ed') ? substr($w, 0, -2) : (strlen($w) > 4 && str_ends_with($w, 's') ? substr($w, 0, -1) : $w));
        $hit = fn ($w) => (bool) preg_match('/\b'.preg_quote($stem($w), '/').'(?:s|es|ed|ing|er|ers)?\b/u', $defs);
        if ($en->isNotEmpty() && ($hit($en[0]) || $en->filter($hit)->count() * 2 >= $en->count() && $en->count() >= 2)) {
            return true;
        }
        $tokens = preg_split('/[^\x{0980}-\x{09FF}\x{200C}\x{200D}]+/u', $defs, -1, PREG_SPLIT_NO_EMPTY);

        return collect(preg_split('/[\s,;।()\/-]+/u', $nfc($n['meaning_bn'])))->filter(fn ($w) => mb_strlen($w) >= 2 && ! in_array($w, self::STOP_BN, true))
            ->contains(fn ($w) => in_array($w, $tokens, true));
    }

    /** The next free Tuesday, Thursday or Saturday, 10am India, at least a day ahead and after every page already given a day. */
    public static function nextSlot(CarbonImmutable $now): CarbonImmutable
    {
        $last = NamePage::whereIn('status', ['scheduled', 'live'])->max('publish_at');
        $from = $now->timezone('Asia/Kolkata')->addDay()->setTime(10, 0);
        if ($last && ($l = CarbonImmutable::parse($last)->timezone('Asia/Kolkata'))->greaterThanOrEqualTo($from)) {
            $from = $l->addDay()->setTime(10, 0);
        }
        while (! in_array($from->dayOfWeekIso, NamePlan::DAYS, true)) {
            $from = $from->addDay();
        }

        return $from->setTimezone((string) config('app.timezone'));
    }
}
