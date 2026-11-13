<?php

namespace App\Filament\Resources\Newsletters\Pages;

use App\Ai\EditorJobs;
use App\Filament\Resources\Newsletters\NewsletterResource;
use App\Models\NewsletterSubscriber;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListNewsletters extends ListRecords
{
    protected static string $resource = NewsletterResource::class;

    public function getSubheading(): ?string
    {
        $n = fn ($v) => \App\Support\Bn::num((string) $v);

        $b = \App\Support\NewsletterSender::breakdown();

        return __('চিঠি পান').': '.$n($b['readers']).' '.__('জন পাঠক').' · '.$n($b['writers']).' '.__('জন লেখক')
            .' · '.__('মোট অ্যাকাউন্ট').' '.$n($b['accounts']).' ('.__('চিঠি বন্ধ রেখেছেন').' '.$n($b['opted_out'])
            .', '.__('স্প্যাম বলে চিহ্নিত').' '.$n($b['spam']).')'
            .(\App\Support\NewsletterSender::realMail() ? '' : ' · '.__('এই সার্ভারে আসল ই-মেল চালু নেই: খসড়া ও পরীক্ষা চলবে, পাঠানো যাবে না'));
    }

    protected function getHeaderActions(): array
    {
        return [
            NewsletterResource::rhythmAction(),     // D190
            NewsletterResource::providerAction(),   // D129
            // The AI team writes the letter (D58); the owner reads, edits and sends.
            Action::make('aiDraft')->label(__('এআই-কে খসড়া লিখতে বলুন'))->icon('heroicon-o-sparkles')->color('gray')
                ->schema([
                    \Filament\Forms\Components\Radio::make('kind')->label(__('কোন চিঠি'))->required()->default('weekly')->live()->options([
                        'weekly' => __('পাঠকদের সাপ্তাহিক চিঠি (নতুন লেখা, না থাকলে আর্কাইভ থেকে)'),
                        'writers' => __('লেখকদের চিঠি (পরের সংখ্যার বিষয়, প্রতিযোগিতা, লেখা পাঠানোর ডাক)'),
                        'topic' => __('নিজের বিষয়'),
                    ]),
                    \Filament\Forms\Components\Radio::make('audience')->label(__('কাকে'))->default('both')->inline()
                        ->options(fn () => \App\Support\Lang::t(\App\Models\Newsletter::AUDIENCES))->visible(fn ($get) => $get('kind') === 'topic'),
                    \Filament\Forms\Components\Textarea::make('topic')->label(__('বিষয়'))->rows(3)->maxLength(1000)
                        ->placeholder(__('যেমন: শারদীয়া সংখ্যার জন্য লেখা পাঠানোর শেষ তারিখ ২০ সেপ্টেম্বর'))
                        ->visible(fn ($get) => $get('kind') === 'topic')->required(fn ($get) => $get('kind') === 'topic'),
                ])
                ->modalSubmitActionLabel(__('খসড়া তৈরি করুন'))
                ->action(function (array $data) {
                    $n = match ($data['kind']) {
                        'writers' => EditorJobs::writersLetter(force: true),
                        'topic' => EditorJobs::topicLetter((string) $data['topic'], $data['audience'] ?? 'both', auth()->user()),
                        default => EditorJobs::newsletter(force: true),
                    };
                    if (! $n) {
                        Notification::make()->title(__('এখন হলো না; এআই থামানো আছে কি না দেখুন'))->warning()->send();

                        return;
                    }
                    $this->redirect(NewsletterResource::getUrl('edit', ['record' => $n]));
                }),
            CreateAction::make()->label(__('নিজে লিখুন')),
        ];
    }
}
