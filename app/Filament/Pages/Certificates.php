<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\EditorTask;
use App\Models\Post;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Certificates (D73): the four designs as they look, who signs, automatic issue on/off, writers' requests,
 * and every certificate given (open, withdraw, bring back). Certificates are drawn fresh each time they are
 * opened; only the record and its code are kept.
 */
class Certificates extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.certificates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'আরও';

    protected static ?string $navigationLabel = 'শংসাপত্র';

    protected static ?string $title = 'শংসাপত্র';

    protected static ?int $navigationSort = 33;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return ($n = EditorTask::open('certificate_request')->count()) ? (string) $n : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')->label(__('একটি লেখার শংসাপত্র দিন'))->icon('heroicon-o-academic-cap')
                ->modalDescription(__('পুরনো বা নতুন যেকোনো প্রকাশিত লেখা। লেখকের অ্যাকাউন্ট থাকলে তিনি ই-মেল পাবেন; না থাকলে নিচে ই-মেল দিলে সেখানে লিংক যাবে।'))
                ->schema([
                    Select::make('post')->label(__('লেখা'))->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Post::published()->certifiable()->where('title', 'like', '%'.$search.'%')->latest('published_at')->limit(30)->pluck('title', 'id'))
                        ->getOptionLabelUsing(fn ($value) => Post::find($value)?->title),
                    TextInput::make('email')->label(__('ই-মেলেও পাঠান (ঐচ্ছিক)'))->email()->maxLength(190),
                ])
                ->modalSubmitActionLabel(__('শংসাপত্র দিন'))
                ->action(function (array $data) {
                    $post = Post::published()->findOrFail($data['post']);
                    self::give($post, $data['email'] ?? null);
                }),
            // Owner (2026-11-12): certificates given to the desk's own pieces (classics, desk drafts, editorials) are
            // withdrawn in one click; each can still be brought back from its row ("ফিরিয়ে আনুন").
            Action::make('withdrawDesk')->label(fn () => __('ডেস্কের লেখার শংসাপত্র সরান').' ('.\App\Support\Bn::num((string) self::deskCertificates()->count()).')')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin) && self::deskCertificates()->exists())
                ->requiresConfirmation()
                ->modalDescription(__('হাতপাখা ডেস্কের নিজের লেখা (ক্লাসিক, ডেস্কের খসড়া, সম্পাদকীয়) কোনো লেখকের জমা নয়, তাই শংসাপত্র লাগে না। এগুলো বাতিল হবে; দরকারে প্রতিটি আবার ফিরিয়ে আনা যায়।'))
                ->action(function () {
                    $ids = self::deskCertificates()->pluck('id');
                    Certificate::whereIn('id', $ids)->update(['revoked_at' => now()]);
                    AuditLog::record('certificate.revoked_desk', null, ['count' => $ids->count()]);
                    Notification::make()->title(__('ডেস্কের লেখার শংসাপত্র সরানো হয়েছে').': '.\App\Support\Bn::num((string) $ids->count()))->success()->send();
                }),
            Action::make('settings')->label(__('সই ও নিয়ম'))->icon('heroicon-o-pencil-square')->color('gray')
                ->fillForm(fn () => [
                    'signer_name' => Setting::get('certificates.signer_name'),
                    'signer_title' => Setting::get('certificates.signer_title') ?: 'সম্পাদক, হাতপাখা',
                    'auto' => Setting::get('certificates.auto') === '1',
                ])
                ->schema([
                    TextInput::make('signer_name')->label(__('যিনি সই করবেন (নাম)'))->maxLength(80)->helperText(__('খালি রাখলে শুধু পদ দেখাবে।')),
                    TextInput::make('signer_title')->label(__('পদ'))->required()->maxLength(80),
                    FileUpload::make('signature')->label(__('সইয়ের ছবি (ঐচ্ছিক)'))->image()->storeFiles(false)->maxSize(2048)
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->helperText(__('সাদা কাগজে কালো কালিতে সই করে ছবি তুলুন। সাদা অংশ নিজে থেকে স্বচ্ছ হয়ে যাবে।')),
                    Toggle::make('remove_signature')->label(__('এখনকার সইয়ের ছবি সরান'))->visible(fn () => (bool) Setting::get('certificates.signature')),
                    Toggle::make('auto')->label(__('নতুন প্রকাশিত লেখার শংসাপত্র নিজে থেকে দিন'))
                        ->helperText(__('চালু থাকলে লেখা প্রকাশের কয়েক মিনিটের মধ্যে শংসাপত্র তৈরি হয়, অ্যাকাউন্ট থাকলে লেখক ই-মেল পান; লেখক "শংসাপত্র চাই" টিপলেও সঙ্গে সঙ্গে পান। বন্ধ থাকলে সব অনুরোধ এখানে আসে।')),
                ])
                ->modalSubmitActionLabel(__('রাখুন'))
                ->action(function (array $data) {
                    Setting::put('certificates.signer_name', trim((string) $data['signer_name']) ?: null);
                    Setting::put('certificates.signer_title', trim((string) $data['signer_title']));
                    if (($data['auto'] ?? false) && Setting::get('certificates.auto') !== '1') {
                        Setting::put('certificates.auto_since', now()->toIso8601String());   // from now on, never the whole archive at once
                    }
                    Setting::put('certificates.auto', ($data['auto'] ?? false) ? '1' : '0');
                    if ($data['remove_signature'] ?? false) {
                        self::dropSignature();
                    }
                    $file = is_array($data['signature'] ?? null) ? reset($data['signature']) : ($data['signature'] ?? null);
                    if ($file) {
                        self::dropSignature();
                        Setting::put('certificates.signature', \App\Support\Signature::store($file->getRealPath()));
                    }
                    AuditLog::record('certificate.settings');
                    Notification::make()->title(__('রাখা হয়েছে'))->success()->send();
                }),
        ];
    }

    /** Valid certificates of pieces that should not have one (the desk's own work). */
    public static function deskCertificates()
    {
        return Certificate::whereNull('revoked_at')->where('kind', 'publication')
            ->whereNotIn('post_id', Post::query()->certifiable()->select('id'));
    }

    /** Issue for a piece (all its writers), tell those with accounts, and optionally mail a link to an address. */
    public static function give(Post $post, ?string $email = null): void
    {
        if (! $post->isCertifiable()) {
            Notification::make()->title(__('এটি হাতপাখা ডেস্কের নিজের লেখা; শংসাপত্র লাগে না'))->warning()->send();

            return;
        }
        $new = Certificate::issueFor($post);
        $all = Certificate::where('post_id', $post->id)->where('kind', 'publication')->whereNull('revoked_at')->get();
        if (filled($email) && $all->isNotEmpty()) {
            foreach ($all as $c) {
                \Illuminate\Support\Facades\Notification::route('mail', $email)->notify(new \App\Notifications\WriterNote('হাতপাখা: আপনার লেখার শংসাপত্র',
                    'হাতপাখায় প্রকাশিত আপনার লেখা "'.$c->title_snapshot.'"-এর শংসাপত্র। নিচের লিংক খুলে ছবি (PNG) বা PDF হিসেবে নামাতে বা প্রিন্ট করতে পারবেন।',
                    $c->verifyUrl(), 'শংসাপত্র দেখুন ও নামান'));
            }
        }
        AuditLog::record('certificate.issued', $post, ['count' => count($new), 'mailed' => filled($email)]);
        Notification::make()->title($new ? __('শংসাপত্র দেওয়া হয়েছে') : __('শংসাপত্র আগেই দেওয়া ছিল'))->body(filled($email) ? __('লিংক ই-মেলে পাঠানো হয়েছে।') : null)->success()->send();
    }

    public function approveRequest(int $taskId): void
    {
        $task = EditorTask::open('certificate_request')->findOrFail($taskId);
        if ($task->subject instanceof Post && $task->subject->isLive()) {
            self::give($task->subject);
        }
        $task->close('done');
    }

    public function declineRequest(int $taskId): void
    {
        EditorTask::open('certificate_request')->findOrFail($taskId)->close('dismissed');
        Notification::make()->title(__('অনুরোধ সরানো হয়েছে'))->send();
    }

    public function withdraw(int $id): void
    {
        Certificate::findOrFail($id)->forceFill(['revoked_at' => now()])->save();
        AuditLog::record('certificate.revoked', null, ['certificate' => $id]);
        Notification::make()->title(__('শংসাপত্র বাতিল হয়েছে'))->body(__('ভুল হলে "ফিরিয়ে আনুন" টিপুন।'))->send();
    }

    public function restore(int $id): void
    {
        Certificate::findOrFail($id)->forceFill(['revoked_at' => null])->save();
        AuditLog::record('certificate.restored', null, ['certificate' => $id]);
        Notification::make()->title(__('ফিরিয়ে আনা হয়েছে'))->success()->send();
    }

    private static function dropSignature(): void
    {
        if ($old = Setting::get('certificates.signature')) {
            @unlink(public_path($old));
        }
        Setting::put('certificates.signature', null);
    }

    /** A sample of each design, drawn from real records where we have them (never saved). */
    public static function samples(): array
    {
        $real = fn (string $theme) => Certificate::with('post.issue')->whereNull('revoked_at')->latest('id')->limit(50)->get()->first(fn ($c) => $c->theme() === $theme);
        $make = fn (array $a) => new Certificate($a + ['issued_at' => now()]);

        return [
            'publication' => $real('publication') ?? $make(['kind' => 'publication', 'author_name_snapshot' => __('লেখকের নাম'), 'title_snapshot' => __('লেখার শিরোনাম')]),
            'issue' => $real('issue') ?? $make(['kind' => 'publication', 'author_name_snapshot' => __('লেখকের নাম'), 'title_snapshot' => __('লেখার শিরোনাম'), 'issue_label' => __('সংখ্যার নাম')]),
            'contest' => $real('contest') ?? $make(['kind' => 'contest', 'place' => 1, 'author_name_snapshot' => __('লেখকের নাম'), 'title_snapshot' => __('লেখার শিরোনাম'), 'issue_label' => __('প্রতিযোগিতার নাম')]),
            'honour' => $real('honour') ?? $make(['kind' => 'honour', 'author_name_snapshot' => __('লেখকের নাম'), 'title_snapshot' => __('মাসের লেখক')]),
        ];
    }

    public function data(): array
    {
        return [
            'samples' => self::samples(),
            'requests' => EditorTask::open('certificate_request')->with('subject')->latest()->get(),
            'recent' => Certificate::with('post')->latest('id')->limit(40)->get(),
            'counts' => Certificate::whereNull('revoked_at')->selectRaw('kind, count(*) n')->groupBy('kind')->pluck('n', 'kind'),
            'auto' => Setting::get('certificates.auto') === '1',
            'signer' => Certificate::signer(),
        ];
    }
}
