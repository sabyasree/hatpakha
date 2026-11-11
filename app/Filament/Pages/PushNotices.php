<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\PushAnnouncement;
use App\Models\PushSubscription;
use App\Support\Bn;
use App\Support\PushNotifier;
use App\Support\Url;
use App\Support\WebPush;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * D132: phone notifications in the desk. How many phones have them on and what they chose, how many were sent
 * and tapped (numbers only, no person), and the owner's announcement to every phone: written, previewed with
 * its count, and sent only when the owner presses the button (like a bulk letter, D42).
 */
class PushNotices extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.push-notices';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'ফোনে খবর';

    protected static ?string $title = 'ফোনে খবর';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public function getViewData(): array
    {
        $subs = PushSubscription::get(['id', 'user_id', 'topics', 'device']);
        $count = fn (string $prefix) => $subs->filter(fn ($s) => collect($s->topics ?? [])->contains(fn ($t) => str_starts_with($t, $prefix)))->count();
        $byTopic = $subs->flatMap(fn ($s) => $s->topics ?? [])->countBy();
        $name = function (string $topic) {
            [$kind, $id] = array_pad(explode(':', $topic, 2), 2, null);

            return match ($kind) {
                'section' => \App\Models\Section::find($id)?->name_bn,
                'serial' => \App\Models\Serial::find($id)?->title,
                'author' => \App\Models\Author::find($id)?->publicName(),
                default => null,
            };
        };
        $top = $byTopic->filter(fn ($n, $t) => str_contains($t, ':'))->sortDesc()->take(10)
            ->map(fn ($n, $t) => ['name' => $name($t) ?? $t, 'kind' => explode(':', $t)[0], 'n' => $n]);
        $since = now()->timezone('Asia/Kolkata')->subDays(29)->toDateString();
        $stats = DB::table('push_stats')->where('day', '>=', $since)->selectRaw('kind, sum(sent) as sent, sum(opened) as opened')->groupBy('kind')->get()->keyBy('kind');

        return [
            'configured' => WebPush::configured(),
            'phones' => $subs->count(),
            'accounts' => $subs->whereNotNull('user_id')->count(),
            'daily' => $count('daily'),
            'sections' => $count('section:'),
            'serials' => $count('serial:'),
            'writers' => $count('author:'),
            'devices' => $subs->countBy(fn ($s) => $s->device ?: '—')->sortDesc(),
            'top' => $top,
            'stats' => $stats,
            'announcements' => PushAnnouncement::latest('id')->limit(10)->get(),
            'n' => fn ($v) => Bn::num((string) $v),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('announce')->label(__('সবার ফোনে একটি ঘোষণা'))->icon('heroicon-o-megaphone')
                ->visible(fn () => WebPush::configured())
                ->modalHeading(__('সবার ফোনে একটি ঘোষণা'))
                ->modalDescription(__('নতুন সংখ্যা, প্রতিযোগিতা বা বড় খবরের জন্য। খুব কম পাঠান: বেশি খবরে মানুষ ফোনে খবর বন্ধ করে দেন। পাঠানোর আগে কতজন পাবেন দেখানো হবে।'))
                ->schema([
                    TextInput::make('title')->label(__('শিরোনাম'))->required()->maxLength(60)->placeholder('অগ্রহায়ণ সংখ্যা বেরোল'),
                    TextInput::make('body')->label(__('এক লাইনে খবর'))->required()->maxLength(160),
                    TextInput::make('url')->label(__('ছুঁলে কোন পাতা খুলবে'))->required()->default(fn () => Url::to('/'))
                        ->helperText(__('হাতপাখার কোনো পাতার ঠিকানা, যেমন সংখ্যা বা প্রতিযোগিতার পাতা।'))
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (! str_starts_with((string) $value, rtrim(url('/'), '/').'/')) {
                                $fail(__('শুধু হাতপাখার নিজের পাতা।'));
                            }
                        }),
                    Radio::make('audience')->label(__('কারা পাবেন'))->required()->default('all')->live()->options(fn () => [
                        'all' => __('ফোনে খবর চালু থাকা সবাই').' ('.Bn::num((string) PushNotifier::audience('all')->count()).')',
                        'daily' => __('শুধু যাঁরা রোজকার খবর চেয়েছেন').' ('.Bn::num((string) PushNotifier::audience('daily')->count()).')',
                    ]),
                ])
                ->modalSubmitActionLabel(__('পাঠানো শুরু করুন'))
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $n = PushNotifier::audience($data['audience'])->count();
                    $a = PushAnnouncement::create([
                        'title' => $data['title'], 'body' => $data['body'], 'url' => $data['url'], 'audience' => $data['audience'],
                        'recipients' => $n, 'created_by' => auth()->id(),
                    ]);
                    \App\Models\AuditLog::record('push.announce.queued', null, ['announcement' => $a->id, 'recipients' => $n]);
                    Notification::make()->title(Bn::num((string) $n).' '.__('টি ফোনে পাঠানো শুরু হবে (মিনিটে ২০০টি করে)'))->success()->send();
                }),
            Action::make('testAnnounce')->label(__('আগে নিজের ফোনে দেখুন'))->icon('heroicon-o-device-phone-mobile')->color('gray')
                ->visible(fn () => WebPush::configured())
                ->schema([
                    TextInput::make('title')->label(__('শিরোনাম'))->required()->maxLength(60),
                    TextInput::make('body')->label(__('এক লাইনে খবর'))->required()->maxLength(160),
                ])
                ->action(function (array $data) {
                    $n = WebPush::toUser(auth()->user(), ['title' => $data['title'], 'body' => $data['body'], 'url' => Url::to('/'), 'tag' => 'announce-test', 'kind' => 'test']);
                    Notification::make()->title($n ? __('আপনার ফোনে পাঠানো হয়েছে') : __('আপনার কোনো ফোনে খবর চালু নেই: অ্যাকাউন্ট → সেটিংস → ফোনে খবর'))->{$n ? 'success' : 'warning'}()->send();
                }),
        ];
    }

    public function cancelAnnouncement(int $id): void
    {
        PushAnnouncement::whereKey($id)->whereIn('status', ['queued', 'sending'])->update(['status' => 'cancelled']);
    }
}
