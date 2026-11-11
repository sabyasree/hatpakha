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

        $s = $r->sent ? \App\Support\NewsletterLinks::stats($r) : null;
        $bounced = $r->sent ? $r->deliveries()->whereNotNull('bounce')->count() : 0;
        $left = in_array($r->status, ['queued', 'sending'], true) ? \App\Support\BulkMail::remainingToday() : null;

        return __(Newsletter::STATUS[$r->status] ?? $r->status).($r->recipients ? ' · '.Bn::num($r->sent.' / '.$r->recipients).' '.__('জনকে পাঠানো') : '')
            .($r->failed ? ' · '.Bn::num((string) $r->failed).' '.__('টি যায়নি') : '')
            .($s ? ' · '.Bn::num((string) $s['clickers']).' '.__('জন লিংকে ক্লিক করেছেন').' ('.Bn::num((string) round(100 * $s['clickers'] / max(1, $r->sent))).'%)' : '')
            .($bounced ? ' · '.Bn::num((string) $bounced).' '.__('টি ঠিকানা অচল বা অভিযোগ, আর পাঠানো হবে না') : '')
            .(in_array($r->status, ['queued', 'sending'], true) ? ' · '.\App\Support\BulkMail::label().' '.__('দিয়ে যাচ্ছে') : '')
            .($left === 0 ? ' · '.__('আজকের সীমা শেষ; বাকিগুলো কাল যাবে') : '');
    }

    /** D117: which links people clicked (each person counted once per link). */
    public static function resultsHtml(Newsletter $r): string
    {
        $s = \App\Support\NewsletterLinks::stats($r);
        $rows = ['home' => __('উপরের “হাতপাখা” নাম'), 'cta' => $r->cta_label ?: __('হাতপাখা খুলুন')];
        foreach ((array) $r->items as $i => $it) {
            $rows[(string) $i] = (string) ($it['title'] ?? '');
        }
        $html = '<p>'.e(Bn::num((string) $r->sent).' '.__('জনকে পাঠানো').' · '.Bn::num((string) $s['clickers']).' '.__('জন অন্তত একটি লিংকে ক্লিক করেছেন')).'</p><table style="width:100%;margin-top:.6rem;border-collapse:collapse">';
        foreach ($rows as $k => $label) {
            $html .= '<tr style="border-top:1px solid rgba(127,127,127,.2)"><td style="padding:.4rem 0">'.e($label).'</td><td style="text-align:right;font-weight:700">'.e(Bn::num((string) ($s['links'][$k] ?? 0))).' '.e(__('জন')).'</td></tr>';
        }

        return $html.'</table><p style="margin-top:.8rem;opacity:.7;font-size:.85rem">'.e(__('খোলার সংখ্যা দেখানো হয় না: অনেক মেল-অ্যাপ নিজে থেকেই ছবি খোলে, তাই সেই সংখ্যা ভুল হয়। ক্লিকই আসল আগ্রহ। কিছু অফিসের মেল-সুরক্ষা লিংক আগে থেকে খুলে দেখে, তাই দু-একটি ক্লিক বেশি দেখাতে পারে।')).'</p>';
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
            NewsletterResource::providerAction(),   // D129
            Action::make('results')->label(__('ফলাফল'))->icon('heroicon-o-chart-bar')->color('gray')
                ->visible(fn () => $this->record->sent > 0)
                ->modalHeading(__('কে কোন লিংকে ক্লিক করেছেন'))
                ->modalContent(fn () => new \Illuminate\Support\HtmlString(self::resultsHtml($this->record)))
                ->modalSubmitAction(false)->modalCancelActionLabel(__('বন্ধ')),
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
                // Owner (2026-11-11): send the test to any address, e.g. Gmail or mail-tester.com's one-off address.
                ->schema([\Filament\Forms\Components\TextInput::make('to')->label(__('কোন ঠিকানায়'))->email()->required()->maxLength(190)
                    ->default(fn () => auth()->user()->email)
                    ->helperText(__('যেমন নিজের Gmail, বা mail-tester.com-এর দেওয়া ঠিকানা (স্প্যাম স্কোর আর কারণ দেখায়)।'))])
                ->modalSubmitActionLabel(__('পাঠান'))
                ->action(function (Newsletter $record, array $data) {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    NewsletterSender::test($record->fresh(), auth()->user(), $data['to']);
                    Notification::make()->title(NewsletterSender::realMail() ? __('পাঠানো হয়েছে').': '.$data['to'] : __('এই সার্ভারে আসল ই-মেল নেই; লগে লেখা হয়েছে'))->success()->send();
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
            // Owner (2026-11-11): 149 letters failed and the desk didn't say why.
            Action::make('why')->label(__('কেন যায়নি'))->icon('heroicon-o-exclamation-triangle')->color('warning')
                ->visible(fn (Newsletter $record) => $record->failed > 0)
                ->modalHeading(__('যে চিঠিগুলো যায়নি, তার কারণ'))
                ->modalContent(fn (Newsletter $record) => new \Illuminate\Support\HtmlString(collect(NewsletterSender::failures($record))
                    ->map(fn ($c, $err) => '<div style="margin-bottom:1rem"><strong>'.e(Bn::num((string) $c)).'টি:</strong> '.e(NewsletterSender::explain($err))
                        .'<br><code style="font-size:.8rem;word-break:break-all;opacity:.75">'.e($err).'</code></div>')->implode('')))
                ->modalSubmitAction(false)->modalCancelActionLabel(__('বন্ধ')),
            Action::make('retry')->label(__('যেগুলো যায়নি, আবার পাঠান'))->icon('heroicon-o-arrow-path')->color('success')
                ->visible(fn (Newsletter $record) => $record->failed > 0 || ($record->status === 'cancelled' && $record->deliveries()->whereNull('sent_at')->exists()))
                ->requiresConfirmation()
                ->modalDescription(__('আগে কারণটি ঠিক করুন (“কেন যায়নি” দেখুন)। যাঁরা আগেই পেয়েছেন, তাঁরা আবার পাবেন না।'))
                ->modalSubmitActionLabel(__('হ্যাঁ, আবার পাঠান'))
                ->action(function (Newsletter $record) {
                    $n = NewsletterSender::retry($record);
                    Notification::make()->title(Bn::num((string) $n).' '.__('টি আবার পাঠানোর তালিকায়; প্রতি মিনিটে কয়েকটি করে যাবে'))->success()->send();
                    $this->redirect(NewsletterResource::getUrl('edit', ['record' => $record]));
                }),
            Action::make('cancel')->label(__('পাঠানো থামান'))->icon('heroicon-o-stop')->color('danger')->requiresConfirmation()
                ->visible(fn (Newsletter $record) => in_array($record->status, ['queued', 'sending'], true))
                ->action(fn (Newsletter $record) => $record->forceFill(['status' => 'cancelled'])->save()),
            Action::make('duplicate')->label(__('কপি করুন'))->icon('heroicon-o-document-duplicate')->color('gray')
                ->requiresConfirmation()->modalHeading(__('এই চিঠির একটি কপি?'))
                ->modalDescription(__('একই লেখা, লেখার তালিকা আর প্রাপক নিয়ে একটি নতুন খসড়া হবে। এটি যেমন আছে তেমনই থাকবে।'))
                ->modalSubmitActionLabel(__('কপি করুন'))
                ->action(function (Newsletter $record) {
                    $copy = $record->duplicate(auth()->user());
                    Notification::make()->title(__('কপি তৈরি হয়েছে; এখন এটি খসড়া'))->success()->send();
                    $this->redirect(NewsletterResource::getUrl('edit', ['record' => $copy]));
                }),
            \App\Filament\Support\Deleting::page(static::$resource),
        ];
    }
}
