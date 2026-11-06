<?php

namespace App\Filament\Pages;

use App\Enums\Role;
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

    protected static ?string $navigationLabel = 'AI সহকারী';

    protected static ?string $title = 'AI সহকারী';

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
            Textarea::make('rubric')->label('সম্পাদকীয় মানদণ্ড (AI এটা মেনে রিপোর্ট লেখে)')->rows(22)->required()->maxLength(20000)
                ->helperText('বদলালে পরের রিপোর্ট থেকে কার্যকর। AI শুধু পরামর্শ দেয়; সিদ্ধান্ত সম্পাদকের।'),
        ]);
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
            'provider' => (string) config('ai.provider'),
            'keys' => ['anthropic' => filled(config('ai.anthropic.key')), 'openai' => filled(config('ai.openai.key'))],
            'spent' => $spent,
            'budget' => (float) config('ai.monthly_budget_usd'),
            'reviews' => AiReview::whereNull('blind_run')->where('created_at', '>=', now()->startOfMonth())->where('status', 'done')->count(),
            'avg' => AiReview::whereNull('blind_run')->where('status', 'done')->where('created_at', '>=', now()->startOfMonth())->count()
                ? $spent / max(1, AiReview::whereNull('blind_run')->where('status', 'done')->where('created_at', '>=', now()->startOfMonth())->count()) : 0,
        ];
    }
}
