<?php

namespace App\Filament\Pages;

use App\Ai\AiSwitch;
use App\Enums\Role;
use Filament\Actions\Action;
use App\Models\AiReview;
use App\Models\AiUsage;
use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Admin-only: AI status, this month's spend, and the editable editorial rubric (PLAN.md §4). */
class AiSettings extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.ai-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'প্রশাসন';

    protected static ?string $navigationLabel = 'এআই সহকারী';

    protected static ?string $title = 'এআই সহকারী';

    protected static ?int $navigationSort = 90;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['rubric' => Setting::get('ai.rubric') ?: (string) file_get_contents(resource_path('ai/rubric.md'))]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Textarea::make('rubric')->label(__('সম্পাদকীয় মানদণ্ড (এআই এটা মেনে রিপোর্ট লেখে)'))->rows(22)->required()->maxLength(20000)
                ->helperText(__('বদলালে পরের রিপোর্ট থেকে কার্যকর। এআই শুধু পরামর্শ দেয়; সিদ্ধান্ত সম্পাদকের।')),
        ]);
    }

    /** One button: pause or resume all AI reports. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkKeys')->label(__('চাবি যাচাই'))->icon('heroicon-o-key')->color('gray')
                ->action(function () {
                    $names = ['anthropic' => 'Claude', 'openai' => 'OpenAI'];
                    $all = \App\Ai\KeyCheck::run();
                    $body = collect($all)->map(fn ($r, $p) => ($r['ok'] ? '✓ ' : '✗ ').$names[$p].': '.$r['message'])->implode("\n");
                    $ok = collect($all)->every(fn ($r) => $r['ok']);
                    Notification::make()->title($ok ? __('দুটো চাবিই কাজ করছে') : __('কিছু ঠিক করতে হবে'))->body($body)
                        ->{$ok ? 'success' : 'warning'}()->persistent()->send();
                }),
            Action::make('seoMode')
                ->label(fn () => \App\Ai\SeoExpert::automatic() ? __('এসইও: নিজে বসায়') : __('এসইও: আগে জিজ্ঞেস করে'))
                ->icon('heroicon-o-magnifying-glass')->color('gray')
                ->requiresConfirmation()
                ->modalDescription(fn () => \App\Ai\SeoExpert::automatic()
                    ? __('বদলে "আগে জিজ্ঞেস করে" করবেন? এসইও বিশেষজ্ঞের কাজ প্রতিটি লেখায় প্রস্তাব হয়ে অপেক্ষা করবে।')
                    : __('বদলে "নিজে বসায়" করবেন? অনুমোদনের পরে এসইও তথ্য নিজে থেকেই বসবে (লগ ও ফেরানোর সুবিধা থাকবে)।'))
                ->action(function () {
                    \App\Models\Setting::put('ai.seo_auto', \App\Ai\SeoExpert::automatic() ? '0' : '1');
                    \App\Models\AuditLog::record('seo.mode_changed', null, ['auto' => \App\Ai\SeoExpert::automatic()]);
                }),
            Action::make('pause')->label(__('এআই থামান'))->icon('heroicon-o-pause')->color('danger')
                ->visible(fn () => ! AiSwitch::paused())
                ->requiresConfirmation()->modalHeading(__('এআই থামাবেন?'))
                ->modalDescription(__('নতুন লেখার এআই রিপোর্ট আর তৈরি হবে না, খরচও হবে না। জমা আর সম্পাদনা আগের মতোই চলবে। যেকোনো সময় আবার চালু করা যায়।'))
                ->modalSubmitActionLabel(__('থামান'))
                ->action(function () {
                    AiSwitch::set(true);
                    Notification::make()->title(__('এআই থামানো হয়েছে'))->success()->send();
                }),
            Action::make('resume')->label(__('এআই চালু করুন'))->icon('heroicon-o-play')->color('success')
                ->visible(fn () => AiSwitch::paused())
                ->action(function () {
                    AiSwitch::set(false);
                    Notification::make()->title(__('এআই আবার চালু'))->success()->send();
                }),
        ];
    }

    public function save(): void
    {
        $rubric = (string) $this->form->getState()['rubric'];
        Setting::put('ai.rubric', $rubric);
        AuditLog::record('ai.rubric_changed', null, ['length' => mb_strlen($rubric)]);
        Notification::make()->title(__('মানদণ্ড রাখা হয়েছে'))->success()->send();
    }

    public function stats(): array
    {
        $spent = AiUsage::spentThisMonthUsd();

        return [
            'enabled' => (bool) config('ai.enabled'),
            'paused' => AiSwitch::paused(),
            'provider' => (string) config('ai.provider'),
            'keys' => ['anthropic' => filled(config('ai.anthropic.key')), 'openai' => filled(config('ai.openai.key'))],
            'spent' => $spent,
            'reviews' => AiReview::whereNull('blind_run')->where('created_at', '>=', now()->startOfMonth())->where('status', 'done')->count(),
            'google' => ['configured' => \App\Support\GoogleData::configured(), 'status' => \App\Support\ReaderInsights::status(), 'brief' => \App\Support\ReaderInsights::brief()],
            'avg' => AiReview::whereNull('blind_run')->where('status', 'done')->where('created_at', '>=', now()->startOfMonth())->count()
                ? $spent / max(1, AiReview::whereNull('blind_run')->where('status', 'done')->where('created_at', '>=', now()->startOfMonth())->count()) : 0,
        ];
    }

    /** D119: fetch Google Analytics + Search Console now instead of waiting for the morning. */
    public function refreshGoogle(): void
    {
        $errors = \App\Support\ReaderInsights::refresh();
        \Filament\Notifications\Notification::make()
            ->title($errors ? __('গুগল থেকে আনা গেল না') : __('গুগলের হিসাব আনা হয়েছে'))
            ->body($errors ? implode(' · ', $errors) : null)->{$errors ? 'warning' : 'success'}()->send();
    }
}
