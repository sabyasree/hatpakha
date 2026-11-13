<?php

namespace App\Filament\Resources\WriterInvites\Pages;

use App\Filament\Resources\WriterInvites\WriterInviteResource;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\WriterInvites;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWriterInvites extends ListRecords
{
    protected static string $resource = WriterInviteResource::class;

    /** What is happening, in a line, and the one switch. */
    public function getSubheading(): ?string
    {
        $s = WriterInvites::stats();
        $bn = fn (int $n) => \App\Support\Bn::num((string) $n);
        $wait = WriterInvites::waitingFor();

        return __('চিঠি লেখা বাকি :left · পাঠানোর অপেক্ষায় :waiting · পাঠানো হয়েছে :sent · “লিখব” :yes · “পরে” :later · লেখা এসেছে :pieces।',
            ['left' => $bn($s['left']), 'waiting' => $bn($s['waiting']), 'sent' => $bn($s['sent']), 'yes' => $bn($s['yes']), 'later' => $bn($s['later']), 'pieces' => $bn($s['pieces'])])
            .' '.($wait ? __('এখন চিঠি যাচ্ছে না').': '.__($wait).'।' : __('চিঠি লেখা হওয়ার বারো ঘণ্টা পরে নিজে থেকে যায়, দিনে ঘণ্টায় চারটি করে।'));
    }

    protected function getHeaderActions(): array
    {
        $on = WriterInvites::auto();

        return [
            Action::make('auto')->label($on ? __('নিজে থেকে পাঠানো বন্ধ করুন') : __('নিজে থেকে পাঠানো চালু করুন'))->color($on ? 'gray' : 'primary')
                ->visible(fn () => \App\Filament\Support\Deleting::isAdmin())->requiresConfirmation()
                ->modalDescription($on ? __('বন্ধ করলে নতুন চিঠি লেখা আর পাঠানো দুটোই থামবে। যা চলে গেছে তা ফেরে না।') : __('চালু করলে দল নিজে চিঠি লিখে বারো ঘণ্টা পরে পাঠাবে।'))
                ->action(function () use ($on) {
                    Setting::put(WriterInvites::AUTO, $on ? '0' : '1');
                    AuditLog::record('invites.auto', null, ['on' => ! $on]);
                    Notification::make()->title($on ? __('বন্ধ হয়েছে') : __('চালু হয়েছে'))->success()->send();
                }),
        ];
    }
}
