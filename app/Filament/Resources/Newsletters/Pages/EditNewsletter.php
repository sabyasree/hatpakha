<?php

namespace App\Filament\Resources\Newsletters\Pages;

use App\Filament\Resources\Newsletters\NewsletterResource;
use App\Models\Newsletter;
use App\Support\Bn;
use App\Support\NewsletterSender;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditNewsletter extends EditRecord
{
    protected static string $resource = NewsletterResource::class;

    public function getSubheading(): ?string
    {
        $r = $this->record;

        return __(Newsletter::STATUS[$r->status] ?? $r->status).($r->recipients ? ' · '.Bn::num($r->sent.' / '.$r->recipients).' '.__('জনকে পাঠানো') : '')
            .($r->failed ? ' · '.Bn::num((string) $r->failed).' '.__('টি যায়নি') : '');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless($this->record->editable(), 409);

        return ['items' => NewsletterResource::resolveItems($data['items'] ?? [])] + $data;
    }

    /** Items are stored with title/url; the form keeps only id + line. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = collect((array) ($data['items'] ?? []))->map(fn ($it) => ['id' => $it['id'] ?? null, 'line' => $it['line'] ?? '', 'title' => $it['title'] ?? null])->all();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->label(__('ই-মেল দেখুন'))->icon('heroicon-o-eye')->color('gray')
                ->modalHeading(fn (Newsletter $record) => $record->subject)->modalSubmitAction(false)->modalCancelActionLabel(__('বন্ধ'))->modalWidth('3xl')
                ->modalContent(fn (Newsletter $record) => new \Illuminate\Support\HtmlString('<iframe sandbox="" style="width:100%;height:70vh;border:0;border-radius:.75rem;background:#fff" srcdoc="'
                    .e(view('emails.newsletter', ['newsletter' => $record, 'unsubscribeUrl' => '#'])->render()).'"></iframe>')),
            // D61: the copywriter rewrites the draft (same facts), optionally with the owner's note.
            Action::make('rewrite')->label(__('কপিরাইটারকে দিয়ে আবার লেখান'))->icon('heroicon-o-sparkles')->color('gray')
                ->visible(fn (Newsletter $record) => $record->editable())
                ->schema([\Filament\Forms\Components\Textarea::make('note')->label(__('কী চান? (ঐচ্ছিক)'))->rows(3)->maxLength(500)
                    ->placeholder(__('যেমন: আরও ছোট করুন; শীতের কথা দিয়ে শুরু করুন; প্রতিযোগিতার কথা আগে বলুন'))])
                ->modalSubmitActionLabel(__('আবার লিখুন'))
                ->action(function (Newsletter $record, array $data) {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $ok = \App\Ai\Copywriter::improve($record->fresh(), (string) ($data['note'] ?? ''));
                    $this->record->refresh();
                    $this->fillForm();
                    Notification::make()->title($ok ? __('নতুন করে লেখা হয়েছে; দেখে নিন') : __('এখন হলো না; এআই থামানো আছে কি না দেখুন'))->{$ok ? 'success' : 'warning'}()->send();
                }),
            Action::make('test')->label(__('আমাকে একটা পাঠান'))->icon('heroicon-o-beaker')->color('gray')
                ->action(function (Newsletter $record) {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    NewsletterSender::test($record->fresh(), auth()->user());
                    Notification::make()->title(NewsletterSender::realMail() ? __('আপনার ই-মেলে পাঠানো হয়েছে') : __('এই সার্ভারে আসল ই-মেল নেই; লগে লেখা হয়েছে'))->success()->send();
                })->visible(fn (Newsletter $record) => $record->editable()),
            Action::make('send')->label(__('পাঠান'))->icon('heroicon-o-paper-airplane')->color('success')
                ->visible(fn (Newsletter $record) => $record->editable())
                ->requiresConfirmation()
                ->modalHeading(fn (Newsletter $record) => __('পাঠাবেন?').' '.$record->subject)
                ->modalDescription(fn (Newsletter $record) => Bn::num((string) NewsletterSender::count($record->audience)).' '.__('জন পাবেন')
                    .' ('.__(Newsletter::AUDIENCES[$record->audience]).'). '.__('প্রতি মিনিটে কয়েকটি করে যাবে; এই পাতায় অগ্রগতি দেখা যাবে।'))
                ->modalSubmitActionLabel(__('হ্যাঁ, পাঠান'))
                ->action(function (Newsletter $record) {
                    if (! NewsletterSender::realMail()) {
                        Notification::make()->title(__('আসল ই-মেল চালু হলে পাঠানো যাবে'))->warning()->send();

                        return;
                    }
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $n = NewsletterSender::queue($record->fresh(), auth()->user());
                    \App\Models\EditorTask::where('type', 'newsletter')->where('status', 'open')->where('subject_id', $record->id)->get()->each->close();
                    Notification::make()->title(Bn::num((string) $n).' '.__('জনের কাছে পাঠানো শুরু হলো'))->success()->send();
                    $this->redirect(NewsletterResource::getUrl('edit', ['record' => $record]));
                }),
            Action::make('cancel')->label(__('পাঠানো থামান'))->icon('heroicon-o-stop')->color('danger')->requiresConfirmation()
                ->visible(fn (Newsletter $record) => in_array($record->status, ['queued', 'sending'], true))
                ->action(fn (Newsletter $record) => $record->forceFill(['status' => 'cancelled'])->save()),
        ];
    }
}
