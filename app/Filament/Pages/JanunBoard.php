<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\BanglaMonth;
use App\Models\FestivalTime;
use App\Models\JanunPage;
use App\Support\BanglaDate;
use App\Support\FestivalPlan;
use App\Support\Handover;
use App\Support\JanunPlan;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * D309 (owner, 2026-11-27: "yes, build the desk list"): what the জেনে রাখুন job is doing, in one read-only page:
 * every planned page with its state, date and why it was held; each festival's checked times; the calendar's months.
 */
class JanunBoard extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.janun-board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'সংখ্যা ও প্রকাশ';   // D279

    protected static ?string $navigationLabel = 'জেনে রাখুন';

    protected static ?string $title = 'জেনে রাখুন';

    protected static ?string $slug = 'janun';

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';   // D279: for information
    }

    public static function getNavigationBadge(): ?string
    {
        return ($n = JanunPage::where('status', 'held')->count()) ? \App\Support\Bn::num((string) $n) : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return \App\Support\DeskInbox::join([[JanunPage::where('status', 'held')->count(), 'টি পাতা আটকে আছে']]);
    }

    /** Why a page is where it is, in plain words. */
    public static function reason(JanunPage $p): string
    {
        $c = (array) $p->check;
        $parts = [];
        if (($c['removed'] ?? 0) > 0) {
            $parts[] = \App\Support\Bn::num((string) $c['removed']).'টি অংশ যাচাইয়ে ভুল বা অনিশ্চিত, তাই বাদ';
        }
        $dropped = collect((array) ($c['mantras'] ?? []))->where('kept', false)->pluck('name');
        if ($dropped->isNotEmpty()) {
            $parts[] = 'মন্ত্র বাদ (দুটি সূত্রে হুবহু মেলেনি): '.$dropped->implode(', ');
        }
        if (($c['poem']['poem'] ?? null) === 'not matched') {
            $parts[] = 'কবিতার লেখা দুটি সূত্রে হুবহু মেলেনি';
        }

        return match ($p->status) {
            'held' => 'আটকে আছে: '.($parts ? implode('; ', $parts).'; ' : '').'যাচাইয়ের পরে পাতা খুব ছোট হয়ে গেছে, তাই প্রকাশ হবে না',
            'planned' => $p->tries > 0 ? 'লেখা তৈরি হয়নি ('.\App\Support\Bn::num((string) $p->tries).' বার চেষ্টা'.($p->tries >= 3 ? '; আর চেষ্টা হবে না' : '').')' : 'অপেক্ষায় (ঘণ্টায় একটি করে লেখা হয়)',
            'drafted' => 'লেখা তৈরি; যাচাই চলছে',
            default => implode('; ', $parts),
        };
    }

    protected function getViewData(): array
    {
        $order = array_keys(JanunPlan::PAGES);
        $pages = JanunPage::all()->sortBy(fn ($p) => array_search($p->key, $order, true))->values();
        $festivals = collect(FestivalPlan::FESTIVALS)->map(function ($f, $key) {
            $rows = FestivalTime::where('festival', $key)->orderBy('cycle')->orderBy('position')->get()->groupBy('cycle');

            return ['key' => $key, 'name' => $f[0], 'total' => count($f[2]), 'years' => $rows];
        })->values();
        $months = BanglaMonth::orderBy('year')->orderBy('month')->get()->groupBy('year');

        $holidays = \App\Models\HolidayList::orderBy('year')->orderBy('country')->get();
        $tithi = \App\Models\TithiDay::where('day', '>=', now('Asia/Kolkata')->toDateString())->get();

        return ['holidays' => $holidays, 'tithi' => ['ok' => $tithi->where('status', 'verified')->count(), 'held' => $tithi->where('status', 'held')->values(), 'last' => $tithi->max('day')], 'pages' => $pages, 'counts' => $pages->countBy('status'), 'on' => Handover::on('janun'), 'festivals' => $festivals, 'months' => $months];
    }
}
