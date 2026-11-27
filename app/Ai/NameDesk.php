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
 * A name is printed only when at least two of the three dictionaries have it as an entry and give a meaning that shares a
 * word with ours: the Bangla Academy's Bengali-English dictionary (accessibledictionary.gov.bd, a Bangladesh government
 * site), Wiktionary in English and Wiktionary in Bengali. Names the dictionaries do not know are left out, never guessed.
 */
final class NameDesk
{
    public const CHECK_PER_RUN = 8;

    public const DICTIONARIES = [
        'accessibledictionary.gov.bd' => 'https://accessibledictionary.gov.bd/bengali-to-english/?q=',
        'en.wiktionary.org' => 'https://en.wiktionary.org/wiki/',
        'bn.wiktionary.org' => 'https://bn.wiktionary.org/wiki/',
    ];

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
            'names' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['bn', 'roman', 'meaning_bn', 'meaning_en', 'origin'],
                'properties' => ['bn' => $str, 'roman' => $str, 'meaning_bn' => $str, 'meaning_en' => $str, 'origin' => ['type' => 'string', 'enum' => ['সংস্কৃত', 'বাংলা', 'আরবি', 'ফারসি', 'অন্য']]]]]]];
        $letter = NamePlan::LETTERS[$p->letter];
        [$gBn, $gEn] = NamePlan::GENDERS[$p->gender];
        [$d, $err] = Claude::json('janun',
            'You list Bengali given names for the "জেনে রাখুন" section of হাতপাখা, a Bengali literary magazine, for parents in West Bengal, Bangladesh and abroad. '
            .'Give 45 real, well-attested '.($p->gender === 'boy' ? 'boys\'' : 'girls\'').' names that begin with the letter given, mixing Hindu, Muslim and secular names that Bengalis actually use. '
            .'Prefer names that are ordinary Bengali or Sanskrit-derived words or well-known Arabic/Persian names found in Bengali dictionaries, with their dictionary meaning. Never invent a name or a meaning; leave out any you are unsure of. '
            .'bn = the name in Bengali script, standard spelling; roman = how it is usually written in English; meaning_bn = the meaning in two to six plain Bengali words; meaning_en = the same in two to six English words, as a dictionary gives it; origin = the language it comes from. '
            .'intro = one short paragraph for parents (no claims about popularity or numbers).',
            '<page letter="'.e($letter).'" gender="'.e($gEn).'">'.e($letter.' দিয়ে '.$gBn.' নাম').'</page>', $schema, (string) config('ai.anthropic.review_model'), 12000);
        $p->tries++;
        if (! is_array($d)) {
            $p->save();
            AuditLog::record('names.draft_failed', null, ['page' => NamePlan::slug($p->gender, $p->letter), 'error' => mb_substr((string) $err, 0, 200)]);

            return false;
        }
        $names = collect((array) ($d['names'] ?? []))
            ->map(fn ($n) => ['bn' => trim((string) ($n['bn'] ?? '')), 'roman' => mb_substr(trim((string) ($n['roman'] ?? '')), 0, 40), 'meaning_bn' => mb_substr(trim((string) ($n['meaning_bn'] ?? '')), 0, 80),
                'meaning_en' => mb_substr(trim((string) ($n['meaning_en'] ?? '')), 0, 80), 'origin' => (string) ($n['origin'] ?? ''), 'status' => 'new', 'found' => []])
            ->filter(fn ($n) => $n['bn'] !== '' && str_starts_with(\Normalizer::normalize($n['bn'], \Normalizer::FORM_C) ?: $n['bn'], $letter) && preg_match('/^[\x{0980}-\x{09FF}\x{200C}\x{200D}]+$/u', $n['bn']))
            ->unique('bn')->take(60)->values()->all();
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
            foreach (self::DICTIONARIES as $host => $base) {
                if (count($found) >= 2 || count($found) + $left < 2) {
                    break;   // the answer cannot change
                }
                $left--;
                $text = JanunDesk::fetchText($base.rawurlencode($n['bn']));
                if ($text !== null && self::gives($host, $text, $n)) {
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

    /** Does this dictionary page have the name as an entry, with a meaning that shares a word with ours? */
    public static function gives(string $host, string $text, array $n): bool
    {
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        $name = \Normalizer::normalize($n['bn'], \Normalizer::FORM_C) ?: $n['bn'];
        if ($host === 'accessibledictionary.gov.bd') {
            if (! preg_match('/Bengali Word\s+'.preg_quote($name, '/').'\s+English definition(.*?)(?:Bengali Word|$)/su', $text, $m)) {
                return false;
            }
            $entry = $m[1];
        } else {
            if (! str_contains($text, $name)) {
                return false;
            }
            $entry = $text;
        }
        $entry = mb_strtolower($entry);
        if ($host === 'bn.wiktionary.org') {
            $words = preg_split('/[\s,;।()\/-]+/u', (string) $n['meaning_bn']);

            return collect($words)->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOP_BN, true))->contains(fn ($w) => str_contains($entry, mb_strtolower($w)));
        }
        $words = preg_split('/[^a-z]+/', mb_strtolower((string) $n['meaning_en']));

        return collect($words)->filter(fn ($w) => strlen($w) >= 3 && ! in_array($w, self::STOP_EN, true))->contains(fn ($w) => preg_match('/\b'.preg_quote($w, '/').'/', $entry));
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
