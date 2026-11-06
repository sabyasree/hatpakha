<?php

namespace App\Ai;

use App\Models\AiUsage;
use App\Models\Setting;

/**
 * The AI team (D49): seven agents, each with its own job, pause switch and cost line.
 * Every AI call is logged with a purpose; the purpose tells which agent did the work.
 */
final class Team
{
    /** key => [Bengali name, what it does, purposes it logs] */
    public const AGENTS = [
        'editor' => ['এআই সম্পাদক', 'জমা পড়া লেখা পড়ে সিদ্ধান্তের প্রস্তাব, লেখককে বার্তা, সংখ্যা, সম্পাদকীয়, মন্তব্য, অন্য দৈনিক কাজ', ['review', 'editor', 'blind_test', 'issue_plan', 'editorial_draft', 'comment_screen', 'picks', 'newsletter', 'followup']],
        'proofreader' => ['প্রুফ রিডার', 'জমার পরেই বানান, যতিচিহ্ন ও ভাষার খুঁটিনাটি দেখে সম্পাদককে জানায়', ['proof']],
        'seo' => ['এসইও বিশেষজ্ঞ', 'অনুমোদনের পরে গুগল ও শেয়ারের সব তথ্য, ট্যাগ, পরের পড়া', ['seo']],
        'designer' => ['গ্রাফিক ডিজাইনার', 'লেখার ছবি, সংখ্যার প্রচ্ছদ, শেয়ারের কার্ড', ['image_scene', 'image', 'cover']],
        'marketing' => ['মার্কেটিং বিশেষজ্ঞ', 'ফেসবুকে দেওয়ার মতো পোস্ট ও ছবি, সাপ্তাহিক পরিকল্পনা', ['marketing']],
        'relations' => ['সম্পর্ক ব্যবস্থাপক', 'সাইটের চ্যাটে পাঠক ও লেখকের প্রশ্নের উত্তর; না পারলে আপনাকে জানায়', ['chat']],
        'chief' => ['প্রধান সম্পাদক (এআই)', 'পুরো দলের কাজ দেখে: কেউ আটকে আছে কি না, ভুল বা বাড়তি খরচ; আপনাকে সতর্ক করে', ['chief']],
    ];

    public static function agentFor(string $purpose): ?string
    {
        foreach (self::AGENTS as $key => [, , $purposes]) {
            if (in_array($purpose, $purposes, true)) {
                return $key;
            }
        }

        return null;
    }

    public static function paused(string $agent): bool
    {
        return Setting::get("ai.agent.{$agent}.paused") === '1';
    }

    public static function setPaused(string $agent, bool $paused): void
    {
        abort_unless(isset(self::AGENTS[$agent]), 404);
        Setting::put("ai.agent.{$agent}.paused", $paused ? '1' : '0');
        \App\Models\AuditLog::record($paused ? 'ai.agent_paused' : 'ai.agent_resumed', null, ['agent' => $agent]);
    }

    /** Why this agent may not work right now (whole team off/paused, or this agent paused). */
    public static function blockedFor(string $purpose): ?string
    {
        $agent = self::agentFor($purpose);

        return $agent && self::paused($agent) ? 'paused: '.$agent : null;
    }

    /** @return array<string, array{calls:int, cost:float, last:?string}> work this month per agent */
    public static function stats(): array
    {
        $rows = AiUsage::where('created_at', '>=', now()->startOfMonth())->selectRaw('purpose, count(*) as n, sum(cost_usd) as c, max(created_at) as last')
            ->groupBy('purpose')->get();
        $out = [];
        foreach (array_keys(self::AGENTS) as $k) {
            $out[$k] = ['calls' => 0, 'cost' => 0.0, 'last' => null];
        }
        foreach ($rows as $r) {
            if ($k = self::agentFor($r->purpose)) {
                $out[$k]['calls'] += (int) $r->n;
                $out[$k]['cost'] += (float) $r->c;
                $out[$k]['last'] = max((string) $out[$k]['last'], (string) $r->last) ?: null;
            }
        }

        return $out;
    }
}
