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
        'editor' => ['এআই সম্পাদক', 'জমা পড়া লেখা পড়ে সিদ্ধান্তের প্রস্তাব, লেখককে বার্তা, সংখ্যা, সম্পাদকীয়, মন্তব্য, শংসাপত্র, অন্য দৈনিক কাজ', ['review', 'editor', 'blind_test', 'issue_plan', 'editorial_draft', 'comment_screen', 'picks', 'followup', 'certificate', 'backlog_screen']],
        // D108: three serial writers, one genre each (drafts only; the owner publishes).
        'serial_mystery' => ['ধারাবাহিক লেখক: রহস্য', 'সাপ্তাহিক রহস্য-রোমাঞ্চ ধারাবাহিকের পরিকল্পনা আর প্রতি সপ্তাহের পর্বের খসড়া, ছবিসহ', ['serial']],
        'serial_ghost' => ['ধারাবাহিক লেখক: অলৌকিক', 'সাপ্তাহিক অলৌকিক গল্পের ধারাবাহিক: পরিকল্পনা আর প্রতি সপ্তাহের পর্বের খসড়া, ছবিসহ', ['serial']],
        'serial_family' => ['ধারাবাহিক লেখক: সম্পর্ক', 'সাপ্তাহিক সম্পর্কের গল্পের ধারাবাহিক: পরিকল্পনা আর প্রতি সপ্তাহের পর্বের খসড়া, ছবিসহ', ['serial']],
        'proofreader' => ['প্রুফ রিডার', 'জমার পরেই এবং পুরনো অপ্রকাশিত লেখায় বানান, যতিচিহ্ন, শিরোনাম ও ঘরোয়া রীতি দেখে সম্পাদককে জানায়', ['proof']],
        'plagiarism' => ['মৌলিকতা যাচাইকারী', 'নতুন লেখার কয়েকটি লাইন ওয়েবে খুঁজে দেখে লেখাটি আগে অন্য কোথাও আছে কি না', ['plagiarism']],
        'seo' => ['এসইও বিশেষজ্ঞ', 'অনুমোদনের পরে গুগল ও শেয়ারের সব তথ্য, ট্যাগ, পরের পড়া; মাসে কোন পুরনো লেখা ঘষামাজা দরকার', ['seo', 'seo_refresh']],
        'designer' => ['গ্রাফিক ডিজাইনার', 'লেখার ছবি, সংখ্যার প্রচ্ছদ, শেয়ার ও উদ্ধৃতির কার্ড, উৎসব ও প্রতিযোগিতার পোস্টার, শংসাপত্র', ['image_scene', 'image', 'cover', 'design']],
        'marketing' => ['মার্কেটিং বিশেষজ্ঞ', 'ফেসবুক, হোয়াটসঅ্যাপ ও ইনস্টাগ্রামের তৈরি পোস্ট, সাপ্তাহিক পরিকল্পনা, উৎসবের প্রচার, লেখক পরিচিতি', ['marketing']],
        'copywriter' => ['কপিরাইটার', 'পাঠক ও লেখকদের সব চিঠি (নিউজলেটার, লেখকদের মাসিক চিঠি, ঘোষণা) এক উষ্ণ, গোছানো ভাষায়', ['newsletter', 'copy']],
        'relations' => ['সম্পর্ক ব্যবস্থাপক', 'লেখা পাঠানোর পাতায় চ্যাটে প্রশ্নের উত্তর; অতিথিকে শুধু সাধারণ তথ্য, লেখককে শুধু নিজের লেখার খবর; না পারলে আপনাকে জানায়', ['chat']],
        'contest' => ['প্রতিযোগিতা ব্যবস্থাপক', 'প্রতিযোগিতার নিয়ম, জমা পড়া লেখার বাছাই তালিকা, ফলাফলের খসড়া; বিজয়ী আপনি বাছেন', ['contest']],
        'archivist' => ['সংগ্রাহক', 'পুরনো লেখা থেকে বিষয়ভিত্তিক সংকলন, আর্কাইভের বাছাই, ট্যাগ গোছানোর প্রস্তাব', ['archive']],
        'strategist' => ['কৌশলবিদ', 'বাজার ও অন্য পত্রিকার খোঁজখবর, আমাদের হিসাব মিলিয়ে মাসিক কৌশল ও অগ্রাধিকার; সম্পাদকের সঙ্গে পরিকল্পনা', ['strategy']],
        'scout' => ['ট্রেন্ড-সন্ধানী', 'প্রতি সপ্তাহে বাংলা সাহিত্য ও সংস্কৃতির খবর খোঁজে (বইমেলা, জন্মদিন, পুরস্কার, নতুন বই), যাতে দলের কাজ সময়োপযোগী হয়', ['trends']],
        'analyst' => ['বিশ্লেষক', 'কোন লেখা কত পড়া হচ্ছে, কোথা থেকে পাঠক আসছেন; মাসিক অন্তর্দৃষ্টি', ['analyst']],
        // D101: ছোটদের হাতপাখা
        'teacher' => ['শিক্ষক (ছোটদের হাতপাখা)', 'ছোটদের লেখার বয়স (৩–৫, ৬–৮, ৯–১২), কঠিন শব্দের মানে (বাংলা ও ইংরেজিতে), লেখাটি ছোটদের উপযুক্ত কি না', ['kids']],
        'narrator' => ['কথক (যন্ত্রকণ্ঠ)', 'ছোটদের লেখা আর বর্ণমালা পড়ে শোনায় (এআই কণ্ঠ, পাতায় তা লেখা থাকে); প্রতিটি পাঠ আপনার অনুমোদনের পরে', ['tts']],
        'chief' => ['প্রধান সম্পাদক (এআই)', 'পুরো দলের কাজ দেখে: কেউ আটকে আছে কি না, ভুল বা বাড়তি খরচ, প্রকাশের দিন খালি কি না; মাসিক দল-পর্যালোচনা ও খরচের পূর্বাভাস', ['chief']],
    ];

    /** Plain names for each kind of job (the "কী করেছে" list, D57). */
    public const PURPOSES = [
        'review' => 'লেখার রিপোর্ট', 'editor' => 'লেখার রিপোর্ট', 'blind_test' => 'অন্ধ পরীক্ষা', 'issue_plan' => 'সংখ্যার প্রস্তাব', 'editorial_draft' => 'সম্পাদকীয় খসড়া',
        'comment_screen' => 'মন্তব্য পড়া', 'picks' => 'সম্পাদকের পছন্দ', 'followup' => 'লেখককে মনে করানো', 'certificate' => 'শংসাপত্র', 'backlog_screen' => 'পুরনো জমা বাছাই', 'serial' => 'ধারাবাহিক লেখা',
        'proof' => 'প্রুফ দেখা', 'plagiarism' => 'মৌলিকতা যাচাই', 'seo' => 'এসইও তথ্য', 'seo_refresh' => 'এসইও ঘষামাজা',
        'image_scene' => 'ছবির ভাবনা', 'image' => 'ছবি আঁকা', 'cover' => 'সংখ্যার প্রচ্ছদ', 'design' => 'নকশা',
        'marketing' => 'পোস্ট তৈরি', 'newsletter' => 'সাপ্তাহিক চিঠি', 'copy' => 'চিঠি লেখা', 'chat' => 'চ্যাটের উত্তর', 'contest' => 'প্রতিযোগিতা',
        'archive' => 'সংকলন', 'analyst' => 'পাঠকের হিসাব', 'chief' => 'কাজের মান যাচাই', 'trends' => 'সাপ্তাহিক খবর খোঁজা', 'strategy' => 'বাজার গবেষণা ও কৌশল', 'kids' => 'ছোটদের লেখা দেখা', 'tts' => 'পড়ে শোনানো (যন্ত্রকণ্ঠ)',
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
    public static function stats(?\Carbon\CarbonInterface $from = null, ?\Carbon\CarbonInterface $to = null): array
    {
        $rows = AiUsage::where('created_at', '>=', $from ?? now()->startOfMonth())->when($to, fn ($q) => $q->where('created_at', '<', $to))->selectRaw('purpose, count(*) as n, sum(cost_usd) as c, max(created_at) as last')
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
