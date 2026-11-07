<?php

namespace App\Filament\Pages;

use App\Ai\KidsAlphabet;
use App\Ai\TeamJobs;
use App\Enums\Role;
use App\Models\EditorTask;
use App\Models\Post;
use App\Models\Section;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * D101: ছোটদের হাতপাখা at the desk. The alphabet (picture + AI voice per letter, approve or remove), the children's
 * pieces with their age, word meanings and reading, and buttons to set the AI teacher, narrator and designer to work.
 */
class KidsCorner extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.kids-corner';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static string|\UnitEnum|null $navigationGroup = 'আরও';

    protected static ?string $navigationLabel = 'ছোটদের হাতপাখা';

    protected static ?string $title = 'ছোটদের হাতপাখা';

    protected static ?int $navigationSort = 21;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = collect(KidsAlphabet::state())->filter(fn ($s) => (! empty($s['pic']) && empty($s['pic_ok'])) || (! empty($s['audio']) && empty($s['audio_ok'])))->count();

        return $waiting ? (string) $waiting : null;
    }

    protected function getHeaderActions(): array
    {
        $job = fn (string $name, string $label, string $agent, string $key, string $icon) => Action::make($name)->label(__($label))->icon($icon)->color('gray')
            ->action(function () use ($agent, $key) {
                TeamJobs::queue($agent, $key);
                Notification::make()->title(__('এআই দল কাজটি নিয়েছে'))->body(__('কয়েক মিনিটে এখানে দেখা যাবে।'))->success()->send();
            });

        return [
            $job('pics', 'বর্ণমালার ছবি', 'designer', 'alphabet', 'heroicon-o-photo'),
            $job('voice', 'বর্ণমালার কণ্ঠ', 'narrator', 'alphabet', 'heroicon-o-speaker-wave'),
            $job('teach', 'লেখার বয়স ও শব্দের মানে', 'teacher', 'all', 'heroicon-o-academic-cap'),
            $job('read', 'লেখা পড়ে শোনানো', 'narrator', 'all', 'heroicon-o-microphone'),
        ];
    }

    public function decide(int $i, string $what, bool $ok): void
    {
        abort_unless(in_array($what, ['pic', 'audio'], true), 422);
        KidsAlphabet::decide($i, $what, $ok);
        if (collect(KidsAlphabet::state())->every(fn ($s) => (empty($s['pic']) || ! empty($s['pic_ok'])) && (empty($s['audio']) || ! empty($s['audio_ok'])))) {
            EditorTask::open('kids_alphabet')->get()->each->close();
        }
    }

    public function approveAll(string $what): void
    {
        abort_unless(in_array($what, ['pic', 'audio'], true), 422);
        foreach (KidsAlphabet::state() as $i => $s) {
            if (! empty($s[$what])) {
                KidsAlphabet::decide((int) $i, $what, true);
            }
        }
        Notification::make()->title(__('অনুমোদিত'))->success()->send();
    }

    public function data(): array
    {
        $sec = Section::where('slug', Section::KIDS)->first();

        return [
            'letters' => KidsAlphabet::letters(),
            'state' => KidsAlphabet::state(),
            'section' => $sec,
            'pieces' => $sec ? Post::with('authors')->where('section_id', $sec->id)->whereIn('status', ['draft', 'pending', 'scheduled', 'published'])->orderByRaw("status = 'published'")->latest('updated_at')->get() : collect(),
        ];
    }
}
