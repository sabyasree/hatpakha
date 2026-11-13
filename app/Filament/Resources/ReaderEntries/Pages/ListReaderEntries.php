<?php

namespace App\Filament\Resources\ReaderEntries\Pages;

use App\Enums\Role;
use App\Filament\Resources\ReaderEntries\ReaderEntryResource;
use App\Models\ReaderPrompt;
use App\Support\Bn;
use App\Support\Kolom;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListReaderEntries extends ListRecords
{
    protected static string $resource = ReaderEntryResource::class;

    public function getSubheading(): ?string
    {
        $open = ReaderPrompt::current();
        $waiting = ReaderPrompt::where('status', 'closed')->latest('id')->first();

        return ($open ? __('এ সপ্তাহের বিষয়: ').$open->title.' ('.Bn::num((string) $open->entries()->count()).__('টি লেখা এসেছে)') : __('এখন কোনো বিষয় খোলা নেই।'))
            .($waiting ? ' · '.__('প্রকাশের অপেক্ষায়: ').$waiting->title.' ('.Bn::num((string) $waiting->picked()->count()).__('টি বাছাই)') : '');
    }

    protected function getHeaderActions(): array
    {
        $admin = fn () => auth()->user()?->hasRole(Role::Admin);

        return [
            // The owner's click: nothing a reader wrote is public before this (D42).
            Action::make('publish')->label(__('বাছাই প্রকাশ করুন'))->icon('heroicon-o-megaphone')->color('success')
                ->visible(fn () => $admin() && ReaderPrompt::where('status', 'closed')->whereHas('entries', fn ($q) => $q->where('status', 'picked'))->exists())
                ->requiresConfirmation()
                ->modalHeading(fn () => __('বাছাই প্রকাশ করবেন?').' '.ReaderPrompt::where('status', 'closed')->latest('id')->first()?->title)
                ->modalDescription(__('"বাছাই" চিহ্নিত লেখাগুলো পাঠকের কলম পাতায় নামসহ দেখা যাবে, আর ফেসবুক-ইনস্টাগ্রামের জন্য একটি পোস্ট তৈরি হবে। আগে তালিকাটা দেখে নিন: বাদ দিতে বা যোগ করতে পারেন।'))
                ->action(function () {
                    $p = ReaderPrompt::where('status', 'closed')->latest('id')->first();
                    $ok = $p && Kolom::publish($p, auth()->id());
                    Notification::make()->title($ok ? __('প্রকাশ হয়েছে') : __('প্রকাশ করার মতো বাছাই নেই'))->{$ok ? 'success' : 'warning'}()->send();
                }),
            Action::make('pickNow')->label(__('এখনই বন্ধ করে এআই দিয়ে বাছাই'))->icon('heroicon-o-sparkles')->color('gray')
                ->visible(fn () => $admin() && ReaderPrompt::current() !== null)->requiresConfirmation()
                ->modalDescription(__('এ সপ্তাহের লেখা নেওয়া বন্ধ হবে, এআই সেরা দশটি প্রস্তাব করবে। সাধারণত শনিবার সন্ধ্যায় নিজে থেকেই হয়।'))
                ->action(function () {
                    Kolom::pickWeek();
                    Notification::make()->title(__('বাছাই তৈরি। দেখে প্রকাশ করুন।'))->success()->send();
                }),
            Action::make('openNow')->label(__('নতুন বিষয় খুলুন'))->icon('heroicon-o-plus')->color('gray')
                ->visible(fn () => $admin() && ReaderPrompt::current() === null)->requiresConfirmation()
                ->modalDescription(__('তালিকার পরের বিষয়টি খুলবে, আর পাঠকদের ডাকার একটি পোস্ট মার্কেটিং পাতায় তৈরি হবে। সাধারণত সোমবার সকালে নিজে থেকেই হয়।'))
                ->action(function () {
                    $p = Kolom::openWeek();
                    Notification::make()->title(__('নতুন বিষয়: ').$p?->title)->success()->send();
                }),
            Action::make('theme')->label(__('এ সপ্তাহের বিষয় বদলান'))->icon('heroicon-o-pencil')->color('gray')
                ->visible(fn () => $admin() && ReaderPrompt::current() !== null)
                ->fillForm(fn () => ReaderPrompt::current()?->only(['title', 'ask']) ?? [])
                ->schema([
                    \Filament\Forms\Components\TextInput::make('title')->label(__('বিষয়'))->required()->maxLength(200),
                    \Filament\Forms\Components\Textarea::make('ask')->label(__('পাঠকদের কী লিখতে বলছি'))->required()->rows(3)->maxLength(600),
                ])
                ->action(function (array $data) {
                    ReaderPrompt::current()?->update(['title' => trim(strip_tags($data['title'])), 'ask' => trim(strip_tags($data['ask']))]);
                    Notification::make()->title(__('বদলানো হয়েছে'))->body(__('ডাকার পোস্টটি আগেই তৈরি হয়ে থাকলে মার্কেটিং পাতায় সেটি নিজে বদলে নিন।'))->success()->send();
                }),
            // D176: the owner's switch for "by itself"
            Action::make('auto')->icon('heroicon-o-bolt')->color(fn () => Kolom::auto() ? 'success' : 'gray')
                ->label(fn () => Kolom::auto() ? __('নিজে থেকে প্রকাশ: চালু') : __('নিজে থেকে প্রকাশ: বন্ধ'))
                ->visible($admin)->requiresConfirmation()
                ->modalHeading(fn () => Kolom::auto() ? __('নিজে থেকে প্রকাশ বন্ধ করবেন?') : __('নিজে থেকে প্রকাশ চালু করবেন?'))
                ->modalDescription(__('চালু থাকলে: সোমবার বিষয় খোলে ও ডাকার পোস্ট ফেসবুকে যায়; শনিবার এআই সেরা দশটি বাছে; রবিবার সকাল ৮টায় সেগুলো নিজে থেকে প্রকাশ হয় আর ফলের পোস্ট ফেসবুকে যায়। এআই যে লেখা পড়ে পাশ করেনি, তা কখনও নিজে থেকে প্রকাশ হয় না। বন্ধ থাকলে প্রতি সপ্তাহে আপনি "বাছাই প্রকাশ করুন" টিপবেন।'))
                ->action(function () {
                    \App\Models\Setting::put(Kolom::AUTO, Kolom::auto() ? '0' : '1');
                    \App\Models\AuditLog::record('kolom.auto', null, ['on' => Kolom::auto()]);
                    Notification::make()->title(Kolom::auto() ? __('চালু হল') : __('বন্ধ হল'))->success()->send();
                }),
            Action::make('page')->label(__('পাতাটি দেখুন'))->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(\App\Support\Url::to('/kolom'))->openUrlInNewTab(),
        ];
    }
}
