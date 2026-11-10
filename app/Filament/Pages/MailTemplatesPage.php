<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Support\MailTemplates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/** Owner, 2026-11-10 (D123): every e-mail the site sends: when, to whom, how it looks, and the owner's own wording. */
class MailTemplatesPage extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.mail-templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?string $navigationLabel = 'ই-মেলের নমুনা';

    protected static ?string $title = 'ই-মেলের নমুনা';

    protected static ?string $slug = 'mail-templates';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public function previewAction(): Action
    {
        return Action::make('preview')->label(__('দেখুন'))->icon('heroicon-o-eye')->color('gray')->size('sm')
            ->modalHeading(fn (array $arguments) => __(MailTemplates::all()[$arguments['key']][0] ?? ''))
            ->modalDescription(__('নমুনা নাম ও লেখা দিয়ে, পাঠকের ইনবক্সে ঠিক যেমন দেখাবে।'))
            ->modalContent(fn (array $arguments) => view('filament.partials.mail-preview', ['html' => MailTemplates::preview($arguments['key'])]))
            ->modalWidth('3xl')->modalSubmitAction(false)->modalCancelActionLabel(__('বন্ধ'));
    }

    public function editAction(): Action
    {
        return Action::make('edit')->label(__('বদলান'))->icon('heroicon-o-pencil-square')->size('sm')
            ->modalHeading(fn (array $arguments) => __('বদলান: ').__(MailTemplates::all()[$arguments['key']][0] ?? ''))
            ->modalWidth('3xl')
            ->fillForm(fn (array $arguments) => MailTemplates::text($arguments['key']))
            ->schema(fn (array $arguments) => [
                Text::make(new HtmlString('<div style="font-size:.9rem;line-height:1.7">'.e(__('ফাঁকা লাইন দিয়ে অনুচ্ছেদ আলাদা করুন। বোতাম যেখানে চান, সেখানে আলাদা লাইনে লিখুন:')).' <b>[বোতাম]</b>'
                    .(($p = MailTemplates::all()[$arguments['key']][3] ?? []) ? '<br>'.e(__('বসানো যায় (পাঠানোর সময় নিজে থেকে বসে যায়):')).'<br>'
                        .collect($p)->map(fn ($d, $k) => '<b>'.e($k).'</b>: '.e(__($d)))->implode('<br>') : '').'</div>')),
                TextInput::make('subject')->label(__('বিষয় (ই-মেলের শিরোনাম)'))->required()->maxLength(200),
                TextInput::make('greeting')->label(__('সম্বোধন'))->maxLength(200),
                Textarea::make('body')->label(__('লেখা'))->required()->rows(12)->maxLength(5000),
                TextInput::make('button')->label(__('বোতামের লেখা'))->maxLength(80)
                    ->visible(fn () => str_contains(MailTemplates::all()[$arguments['key']][6] ?? '', MailTemplates::BUTTON)),
                TextInput::make('salutation')->label(__('শেষে নাম'))->maxLength(120),
            ])
            ->modalSubmitActionLabel(__('রাখুন'))
            ->action(function (array $data, array $arguments) {
                MailTemplates::save($arguments['key'], $data, auth()->id());
                AuditLog::record('mail_template.saved', null, ['key' => $arguments['key']]);
                Notification::make()->title(__('রাখা হয়েছে। এখন থেকে এই লেখাতেই ই-মেল যাবে।'))->success()->send();
            });
    }

    public function resetAction(): Action
    {
        return Action::make('reset')->label(__('আগের লেখায় ফেরান'))->icon('heroicon-o-arrow-uturn-left')->color('gray')->size('sm')
            ->requiresConfirmation()->modalDescription(__('আপনার বদল মুছে আসল লেখা ফিরে আসবে।'))
            ->visible(fn (array $arguments) => MailTemplates::edited($arguments['key']))
            ->action(function (array $arguments) {
                MailTemplates::reset($arguments['key']);
                AuditLog::record('mail_template.reset', null, ['key' => $arguments['key']]);
                Notification::make()->title(__('আসল লেখা ফিরে এসেছে'))->success()->send();
            });
    }
}
