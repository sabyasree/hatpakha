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
            Textarea::make('rubric')->label('সম্পাদকীয় মানদণ্ড (এআই এটা মেনে রিপোর্ট লেখে)')->rows(22)->required()->maxLength(20000)
                ->helperText('বদলালে পরের রিপোর্ট থেকে কার্যকর। এআই শুধু পরামর্শ দেয়; সিদ্ধান্ত সম্পাদকের।'),
        ]);
    }

    /** One button: pause or resume all AI reports. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('pause')->label('এআই থামান')->icon('heroicon-o-pause')->color('danger')
                ->visible(fn () => ! AiSwitch::paused())
                ->requiresConfirmation()->modalHeading('এআই থামাবেন?')
                ->modalDescription('নতুন লেখার এআই রিপোর্ট আর তৈরি হবে না, খরচও হবে না। জমা আর সম্পাদনা আগের মতোই চলবে। যেকোনো সময় আবার চালু করা যায়।')
                ->modalSubmitActionLabel('থামান')
                ->action(function () {
                    AiSwitch::set(true);
                    Notification::make()->title('এআই থামানো হয়েছে')->success()->send();
                }),
            Action::make('resume')->label('এআই চালু করুন')->icon('heroicon-o-play')->color('success')
                ->visible(fn () => AiSwitch::paused())
                ->action(function () {
                    AiSwitch::set(false);
                    Notification::make()->title('এআই আবার চালু')->success()->send();
                }),
        ];
    }

    public function save(): void
    {
        $rubric = (string) $this->form->getState()['rubric'];
        Setting::put('ai.rubric', $rubric);
        AuditLog::record('ai.rubric_changed', null, ['length' => mb_strlen($rubric)]);
        Notification::make()->title('মানদণ্ড রাখা হয়েছে')->success()->send();
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
            'avg' => AiReview::whereNull('blind_run')->where('status', 'done')->where('created_at', '>=', now()->startOfMonth())->count()
                ? $spent / max(1, AiReview::whereNull('blind_run')->where('status', 'done')->where('created_at', '>=', now()->startOfMonth())->count()) : 0,
        ];
    }
}
