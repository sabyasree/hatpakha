<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\LearnStroke;
use App\Support\LearnCourse;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * D325 (owner, 2026-11-28: "stroke-order drawings… you create first set, and add option in desk to look and verify, and
 * also option to do change, and add later, keep it flexible"). Every letter with its numbered strokes: approve the machine
 * draft, redraw it stroke by stroke, make a new draft, delete it, or add any letter, sign or joined letter. Children see
 * only approved drawings (worksheets' first box, the lesson's tracing screen).
 */
class LearnStrokes extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.learn-strokes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencil;

    protected static string|\UnitEnum|null $navigationGroup = 'সংখ্যা ও প্রকাশ';

    protected static ?string $navigationLabel = 'বাংলা শিখি: লেখার ক্রম';

    protected static ?string $title = 'বাংলা শিখি: লেখার ক্রম';

    protected static ?string $slug = 'learn-strokes';

    protected static ?int $navigationSort = 8;

    public string $only = 'all';   // all | draft | approved

    public string $newGlyph = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = LearnStroke::where('status', 'draft')->count();

        return $n ? \App\Support\Bn::num((string) $n) : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('খসড়া, দেখে অনুমোদনের অপেক্ষায়');
    }

    protected function getViewData(): array
    {
        $order = array_flip(array_column(\App\Ai\KidsAlphabet::letters(), 0));
        $all = LearnStroke::all()->sortBy(fn ($s) => [$order[$s->glyph] ?? 999, $s->glyph])->values();

        return ['items' => $this->only === 'all' ? $all : $all->where('status', $this->only)->values(), 'counts' => $all->countBy('status')];
    }

    public static function glyphOk(string $g): bool
    {
        return $g !== '' && mb_strlen($g) <= 6 && (bool) preg_match('/^[\x{0980}-\x{09FF}\x{200C}\x{200D}]+$/u', $g);
    }

    /** Drawn at the desk: the person drew it themselves, so it is approved at once. */
    public function save(string $glyph, string $json): void
    {
        $this->store($glyph, $json, 'approved', 'desk');
    }

    /** A new machine draft (from the button, or when a letter is added). */
    public function saveDraft(string $glyph, string $json): void
    {
        $this->store($glyph, $json, 'draft', 'machine');
    }

    private function store(string $glyph, string $json, string $status, string $source): void
    {
        $glyph = LearnCourse::nfc(trim($glyph));
        $strokes = LearnStroke::clean(json_decode($json, true));
        if (! self::glyphOk($glyph) || $strokes === null) {
            Notification::make()->danger()->title(__('আঁকাটা রাখা গেল না: অন্তত একটা টান দিন (১২টার বেশি নয়)।'))->send();

            return;
        }
        LearnStroke::updateOrCreate(['glyph' => $glyph], ['strokes' => $strokes, 'status' => $status, 'source' => $source, 'user_id' => auth()->id()]);
        AuditLog::record('learn.strokes_'.$status, null, ['glyph' => $glyph, 'strokes' => count($strokes), 'source' => $source]);
        Notification::make()->success()->title(($status === 'approved' ? __('রাখা ও অনুমোদিত') : __('খসড়া রাখা হয়েছে')).': '.$glyph)->send();
    }

    public function approve(int $id): void
    {
        if ($s = LearnStroke::find($id)) {
            $s->update(['status' => 'approved', 'user_id' => auth()->id()]);
            AuditLog::record('learn.strokes_approved', null, ['glyph' => $s->glyph]);
        }
    }

    public function unapprove(int $id): void
    {
        LearnStroke::whereKey($id)->update(['status' => 'draft']);
    }

    public function remove(int $id): void
    {
        if ($s = LearnStroke::find($id)) {
            AuditLog::record('learn.strokes_removed', null, ['glyph' => $s->glyph]);
            $s->delete();
        }
    }
}
