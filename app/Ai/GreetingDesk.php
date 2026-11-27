<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\GreetingSet;
use App\Models\Setting;
use App\Support\GreetingPlan;
use App\Support\JanunPlan;
use Carbon\CarbonImmutable;

/**
 * D315 (owner: festival greetings, automatic once checked): one set a day at most. Claude writes our own short wishes
 * for four kinds of readers and, if it is sure of one, a poet's line (only poets out of copyright, JanunPlan::POETS).
 * The check, before anything is shown:
 *   - every line in Bengali script, a sensible length, no links, no repeats;
 *   - a second reading by the proofreader drops lines with a spelling mistake, a wrong custom, anything unkind or any
 *     well-known line copied from elsewhere;
 *   - the poet's line is kept only when the server opens two independent pages that give it letter for letter.
 * A set with fewer than GreetingPlan::MIN_LINES lines left is held for the desk, never shown.
 */
final class GreetingDesk
{
    public static function run(CarbonImmutable $now): bool
    {
        foreach (array_keys(GreetingPlan::OCCASIONS) as $slug) {
            GreetingSet::firstOrCreate(['slug' => $slug]);
        }
        if ($s = GreetingSet::where('status', 'drafted')->orderBy('id')->first()) {
            self::check($s);

            return true;
        }
        if (Setting::get('greeting.day') === $now->toDateString()) {
            return false;
        }
        $order = array_flip(GreetingPlan::order($now));
        $next = GreetingSet::where('status', 'planned')->where('tries', '<', 3)->get()->sortBy(fn ($s) => $order[$s->slug] ?? 99)->first();
        if (! $next) {
            return false;
        }
        Setting::put('greeting.day', $now->toDateString());
        self::draft($next);

        return true;
    }

    public static function draft(GreetingSet $s): bool
    {
        [$bn, $en, , , $brief] = GreetingPlan::OCCASIONS[$s->slug];
        $str = ['type' => 'string'];
        $line = ['type' => 'object', 'additionalProperties' => false, 'required' => ['bn', 'en'], 'properties' => ['bn' => $str, 'en' => $str]];
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['intro', 'description', 'groups', 'poet', 'faq'], 'properties' => [
            'intro' => $str, 'description' => $str,
            'groups' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['key', 'lines'],
                'properties' => ['key' => ['type' => 'string', 'enum' => array_keys(GreetingPlan::GROUPS)], 'lines' => ['type' => 'array', 'items' => $line]]]],
            'poet' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['text', 'poet', 'work'], 'properties' => ['text' => $str, 'poet' => $str, 'work' => $str]],
            'faq' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['q', 'a'], 'properties' => ['q' => $str, 'a' => $str]]],
        ]];
        $system = 'You write festival and occasion greetings for the "জেনে রাখুন" section of হাতপাখা (Hatpakha), a Bengali literary web magazine read in West Bengal, Bangladesh and by Bengalis abroad, mostly on phones. '
            .'Every greeting is our own, newly written, in warm, simple প্রমিত বাংলা (Bangla Academy spelling); never copy a greeting, quotation or verse from a website, card or book. '
            .'groups: for each key (chhoto = one short line for a picture or a status, up to 90 characters; poribar = family and elders; bondhu = friends, light and playful allowed; office = colleagues, clients, polite) write 6 to 8 greetings of one to three sentences. '
            .'en = a plain English rendering of the same greeting, for families abroad. No emoji, no hashtags, no links, no names of real people, no claims about dates. Respect every faith: nothing a believer would find careless. '
            .'poet = one to four lines from a poem or song by ONLY one of these poets ('.implode(', ', array_keys(JanunPlan::POETS)).'; never Kazi Nazrul Islam, Jibanananda Das or any later poet: their work is in copyright), from a work published in the poet\'s lifetime, that suit the occasion, exactly as in the standard edition; work = the poem or song\'s name. If you are not certain of every letter, give empty strings. '
            .'intro = two short paragraphs: what the occasion means and how Bengalis greet each other on it. description = 120-155 characters for Google, with the Bengali name and the word শুভেচ্ছা. '
            .'faq = 3 short questions people really ask about greeting on this occasion (e.g. what to write to an elder), with short answers.';
        [$d, $err] = Claude::json('janun', $system, '<occasion bn="'.e($bn).'" en="'.e($en).'">'.e($brief).'</occasion>', $schema, (string) config('ai.anthropic.review_model'), 16000);
        $s->tries++;
        if (! is_array($d)) {
            $s->save();
            AuditLog::record('greeting.draft_failed', null, ['slug' => $s->slug, 'error' => mb_substr((string) $err, 0, 200)]);

            return false;
        }
        $s->forceFill(['body' => $d, 'seo_description' => JanunPlan::clip((string) ($d['description'] ?? '')) ?: null, 'status' => 'drafted'])->save();

        return true;
    }

    /** The rules a line must pass before the proofreader even sees it. */
    public static function fine(string $bn, string $group): bool
    {
        $bn = trim($bn);
        $len = mb_strlen($bn);
        $letters = mb_strlen(JanunDesk::letters($bn));
        $visible = mb_strlen(preg_replace('/[\s\p{P}।]+/u', '', $bn));

        return $len >= ($group === 'chhoto' ? 8 : 20) && $len <= ($group === 'chhoto' ? GreetingPlan::CARD_MAX : 420)
            && $visible > 0 && $letters / $visible >= 0.9
            && ! preg_match('~https?://|www\.|#|@|[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]~u', $bn);
    }

    public static function check(GreetingSet $s): void
    {
        $body = (array) $s->body;
        $removed = [];
        $seen = [];
        $groups = [];
        foreach ((array) ($body['groups'] ?? []) as $g) {
            $key = (string) ($g['key'] ?? '');
            if (! isset(GreetingPlan::GROUPS[$key])) {
                continue;
            }
            foreach ((array) ($g['lines'] ?? []) as $l) {
                $bn = trim((string) ($l['bn'] ?? ''));
                $sig = JanunDesk::letters($bn);
                if (! self::fine($bn, $key) || isset($seen[$sig])) {
                    $removed[] = ['bn' => mb_substr($bn, 0, 120), 'why' => isset($seen[$sig]) ? 'repeat' : 'form'];

                    continue;
                }
                $seen[$sig] = true;
                $groups[$key][] = ['bn' => $bn, 'en' => trim((string) ($l['en'] ?? ''))];
            }
        }
        $flat = collect($groups)->flatMap(fn ($ls, $k) => collect($ls)->map(fn ($l, $i) => [$k, $i, $l['bn']]))->values();
        $bad = self::proofread($s, $flat->map(fn ($r) => $r[2])->all());
        if ($bad === null) {
            return;   // the proofreader did not answer: next run
        }
        foreach ($bad as $i => $why) {
            if ($r = $flat[$i] ?? null) {
                $removed[] = ['bn' => mb_substr($r[2], 0, 120), 'why' => mb_substr($why, 0, 160)];
                unset($groups[$r[0]][$r[1]]);
            }
        }
        $groups = collect(GreetingPlan::GROUPS)->keys()->filter(fn ($k) => ! empty($groups[$k]))
            ->map(fn ($k) => ['key' => $k, 'lines' => array_values($groups[$k])])->values()->all();
        $body['groups'] = $groups;
        $poet = self::checkPoet((array) ($body['poet'] ?? []));
        if ($poet === false) {
            return;   // the search did not work: next run
        }
        $body['poet'] = $poet;
        $count = collect($groups)->sum(fn ($g) => count($g['lines']));
        $live = $count >= GreetingPlan::MIN_LINES && count($groups) >= 3 && filled($body['intro'] ?? '');
        $s->forceFill(['body' => $body, 'check' => ['removed' => array_slice($removed, 0, 40), 'lines' => $count, 'poet' => $poet ? 'matched' : 'none', 'at' => now()->toIso8601String()],
            'status' => $live ? 'live' : 'held', 'checked_at' => now(), 'published_at' => $live ? now() : null])->save();
        AuditLog::record($live ? 'greeting.live' : 'greeting.held', null, ['slug' => $s->slug, 'lines' => $count]);
        if ($live) {
            \App\Support\IndexNow::ping([$s->url(), \App\Support\Url::to('/janun/greetings')]);
        }
    }

    /** @return array<int, string>|null line number => why it was dropped; null when the proofreader could not be asked */
    private static function proofread(GreetingSet $s, array $lines): ?array
    {
        if ($lines === []) {
            return [];
        }
        $str = ['type' => 'string'];
        [$d] = Claude::json('janun',
            'You are the proofreader of হাতপাখা, a Bengali literary magazine. You get greetings written for '.GreetingPlan::OCCASIONS[$s->slug][1].'. '
            .'List ONLY the numbers of greetings that must not be published: a spelling or grammar mistake by Bangla Academy rules, a wrong or doubtful custom, anything careless about any faith, unkind, crude or sad on a happy day, '
            .'a claim about a date, or a well-known greeting, quotation or verse copied from elsewhere (we print only our own). Keep good ones; do not rewrite anything.',
            collect($lines)->map(fn ($l, $i) => '<g n="'.$i.'">'.e($l).'</g>')->implode("\n"),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['drop'], 'properties' => ['drop' => ['type' => 'array', 'items' => ['type' => 'object',
                'additionalProperties' => false, 'required' => ['n', 'why'], 'properties' => ['n' => ['type' => 'integer'], 'why' => $str]]]]],
            (string) config('ai.anthropic.review_model'), 6000);
        if (! is_array($d)) {
            return null;
        }

        return collect((array) ($d['drop'] ?? []))->mapWithKeys(fn ($x) => [(int) ($x['n'] ?? -1) => (string) ($x['why'] ?? '')])->all();
    }

    /** @return array|null|false the poet's line with the two pages that give it; null = none; false = ask again later */
    private static function checkPoet(array $p): array|null|false
    {
        $text = trim((string) ($p['text'] ?? ''));
        $poet = trim((string) ($p['poet'] ?? ''));
        if ($text === '' || ! isset(JanunPlan::POETS[$poet]) || mb_strlen(JanunDesk::letters($text)) < 12 || substr_count($text, "\n") > 4) {
            return null;
        }
        [$d] = Claude::search('factcheck',
            'Find these lines by this Bengali poet on independent, reliable web pages (e.g. Wikisource, Rabindra Rachanabali, publishers, well-known literature sites; not hatpakha.com; not forums, quote-card or video pages). '
            .'Answer ONLY with JSON: {"pages":[{"url":"https://..."}]}',
            '<lines poet="'.e($poet).'" work="'.e((string) ($p['work'] ?? '')).'">'.e($text).'</lines>', 4);
        if (! Claude::$fake && (Claude::$lastSearch['ok'] ?? 0) === 0) {
            return false;
        }
        $want = JanunDesk::letters($text);
        $found = [];
        foreach (collect((array) ($d['pages'] ?? []))->pluck('url')->filter()->unique()->take(6) as $url) {
            $host = preg_replace('/^www\./', '', strtolower((string) parse_url((string) $url, PHP_URL_HOST)));
            if ($host === '' || str_contains($host, 'hatpakha') || isset($found[$host])) {
                continue;
            }
            $page = JanunDesk::fetchText((string) $url);
            // the lines AND the poet's name on the same page: a line in copyright passed off as Tagore's must not pass
            if ($page !== null && str_contains(JanunDesk::letters($page), $want) && str_contains(JanunDesk::letters($page), JanunDesk::letters($poet))) {
                $found[$host] = (string) $url;
            }
            if (count($found) >= 2) {
                break;
            }
        }

        return count($found) >= 2 ? ['text' => $text, 'poet' => $poet, 'work' => mb_substr(trim((string) ($p['work'] ?? '')), 0, 120), 'urls' => array_values($found)] : null;
    }
}
