<?php

namespace App\Filament\Resources\Newsletters;

use App\Filament\Resources\Newsletters\Pages\CreateNewsletter;
use App\Filament\Resources\Newsletters\Pages\EditNewsletter;
use App\Filament\Resources\Newsletters\Pages\ListNewsletters;
use App\Models\Newsletter;
use App\Models\Post;
use App\Support\NewsletterSender;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Letters to readers and writers (D58). The AI team drafts; the owner tests and sends. */
class NewsletterResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;
    use \App\Filament\Concerns\AdminDeletesOnly;

    protected static ?string $model = Newsletter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $modelLabel = 'চিঠি';

    protected static ?string $pluralModelLabel = 'চিঠি';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(\App\Enums\Role::Admin) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return ($n = Newsletter::where('status', 'draft')->count()) ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Radio::make('audience')->label(__('কাকে পাঠাবেন'))->required()->default('readers')->inline()
                ->options(fn () => collect(Newsletter::AUDIENCES)->mapWithKeys(fn ($l, $k) => [$k => __($l).' ('.\App\Support\Bn::num((string) NewsletterSender::count($k)).')'])->all())
                ->helperText(__('পাঠক = যাঁরা চিঠি চেয়ে ই-মেল নিশ্চিত করেছেন, আর ই-মেল যাচাই করা সব অ্যাকাউন্ট (যাঁরা বন্ধ করেননি)। লেখক = যাচাই করা লেখক-অ্যাকাউন্ট। একজন দু\'বার পাবেন না।'))
                ->columnSpanFull(),
            TextInput::make('subject')->label(__('বিষয় (ই-মেলের শিরোনাম)'))->required()->maxLength(120)->columnSpanFull(),
            TextInput::make('preheader')->label(__('ইনবক্সে বিষয়ের পাশে যে লাইন দেখা যায় (ঐচ্ছিক)'))->maxLength(160)->columnSpanFull(),
            Textarea::make('intro')->label(__('শুরুর কথা'))->rows(5)->maxLength(3000)->columnSpanFull()
                ->helperText(__('অনুচ্ছেদের মাঝে একটি ফাঁকা লাইন দিন।')),
            Repeater::make('items')->label(__('লেখা (ঐচ্ছিক)'))->columnSpanFull()->reorderable()->collapsible()->defaultItems(0)->maxItems(12)
                ->addActionLabel(__('লেখা যোগ করুন'))
                ->itemLabel(fn (array $state) => $state['title'] ?? null)
                ->schema([
                    Select::make('id')->label(__('লেখা'))->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Post::published()->where('title', 'like', '%'.$search.'%')->latest('published_at')->limit(30)->pluck('title', 'id'))
                        ->getOptionLabelUsing(fn ($value) => Post::find($value)?->title),
                    TextInput::make('line')->label(__('এক লাইনে কেন পড়বেন'))->maxLength(240),
                ])->columns(2),
            Textarea::make('closing')->label(__('শেষের কথা (ঐচ্ছিক)'))->rows(3)->maxLength(2000)->columnSpanFull(),
            TextInput::make('cta_label')->label(__('বোতামের লেখা'))->maxLength(40)->placeholder(__('যেমন: লেখা পাঠান')),
            Select::make('cta_target')->label(__('বোতাম কোথায় নিয়ে যাবে'))->default('home')->selectablePlaceholder(false)->options(fn () => \App\Support\Lang::t([
                'home' => 'হাতপাখার প্রথম পাতা', 'submit' => 'লেখা পাঠানোর ফর্ম', 'submissions' => 'লেখা পাঠানোর নিয়ম', 'archive' => 'আর্কাইভ', 'issue' => 'সাম্প্রতিক সংখ্যা',
            ])),
        ])->disabled(fn (?Newsletter $record) => $record && ! $record->editable());
    }

    /** Title, writer and link are filled in from the piece itself (never typed by hand). */
    public static function resolveItems(array $items): array
    {
        return collect($items)->map(function ($it) {
            $p = Post::published()->with('authors')->find($it['id'] ?? 0);

            return $p ? ['id' => $p->id, 'title' => $p->title, 'author' => $p->authors->first()?->publicName() ?? '', 'url' => $p->url(), 'line' => strip_tags((string) ($it['line'] ?? ''))] : null;
        })->filter()->values()->all();
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('subject')->label(__('বিষয়'))->weight('bold')->wrap()->searchable()
                    ->description(fn (Newsletter $record) => __(Newsletter::SOURCES[$record->source] ?? $record->source)),
                TextColumn::make('audience')->label(__('কাকে'))->badge()->formatStateUsing(fn ($state) => __(Newsletter::AUDIENCES[$state] ?? $state)),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()->formatStateUsing(fn ($state) => __(Newsletter::STATUS[$state] ?? $state))
                    ->color(fn ($state) => ['draft' => 'warning', 'sent' => 'success', 'cancelled' => 'gray'][$state] ?? 'info'),
                TextColumn::make('sent')->label(__('পাঠানো'))->formatStateUsing(fn ($state, Newsletter $record) => $record->recipients ? \App\Support\Bn::num($state.' / '.$record->recipients) : '—'),
                TextColumn::make('clicks')->label(__('ক্লিক'))->state(fn (Newsletter $record) => $record->sent
                    ? \App\Support\Bn::num((string) \App\Support\NewsletterLinks::stats($record)['clickers']).' '.__('জন') : '—'),
                TextColumn::make('updated_at')->label(__('শেষ বদল'))->since(),
            ])
            ->recordActions([EditAction::make()->label(fn (Newsletter $record) => $record->editable() ? __('খুলুন') : __('দেখুন')),
                \Filament\Actions\Action::make('duplicate')->label(__('কপি করুন'))->icon('heroicon-o-document-duplicate')->color('gray')
                    ->action(fn (Newsletter $record) => redirect(self::getUrl('edit', ['record' => $record->duplicate(auth()->user())]))),
                \App\Filament\Support\Deleting::row()])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\App\Filament\Support\Deleting::bulk(\App\Models\Newsletter::class)])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListNewsletters::route('/'), 'create' => CreateNewsletter::route('/create'), 'edit' => EditNewsletter::route('/{record}/edit')];
    }

    /** D129: the owner picks the road for letters: Amazon SES or Brevo (both set up in ENV_FILE). Admins only. */
    public static function providerAction(): \Filament\Actions\Action
    {
        $describe = function (string $p) {
            $left = \App\Support\BulkMail::remainingToday($p);
            $limit = \App\Support\BulkMail::limit($p);

            $month = \App\Support\BulkMail::monthLeft($p);

            return ($limit > 0
                ? __('দিনে').' '.\App\Support\Bn::num((string) $limit).' '.__('টি পর্যন্ত; আজ আর').' '.\App\Support\Bn::num((string) $left).' '.__('টি যেতে পারে')
                : __('দিনের কোনো সীমা রাখা নেই')).($month !== null ? '। '.__('এ মাসে আর').' '.\App\Support\Bn::num((string) $month).' '.__('টি যেতে পারে') : '');
        };

        return \Filament\Actions\Action::make('provider')
            ->label(fn () => __('পাঠানোর পথ').': '.\App\Support\BulkMail::label())
            ->icon('heroicon-o-arrows-right-left')->color('gray')
            ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin) && \App\Support\BulkMail::available() !== [])
            ->modalHeading(__('চিঠি কোন পথে যাবে'))
            ->modalDescription(__('পাঠকদের চিঠি, ধারাবাহিক আর অনুসরণ করা লেখকের খবর এই পথে যায়। অ্যাকাউন্টের ই-মেল (যাচাই, পাসওয়ার্ড) আগের মতো Hostinger দিয়েই যায়। বদলালে পরের চিঠি থেকেই নতুন পথে যাবে, চলতি চিঠিও।'))
            ->schema([
                \Filament\Forms\Components\Radio::make('provider')->label(__('পথ'))->required()
                    ->default(fn () => \App\Support\BulkMail::provider())
                    // Every road is listed; one without settings is greyed out and says which ENV_FILE line is missing.
                    ->options(fn () => collect(\App\Support\BulkMail::PROVIDERS)->mapWithKeys(fn ($v, $p) => [$p => $v[0]])->all())
                    ->disableOptionWhen(fn (string $value) => ! in_array($value, \App\Support\BulkMail::available(), true))
                    ->descriptions(fn () => collect(\App\Support\BulkMail::PROVIDERS)->mapWithKeys(fn ($v, $p) => [$p => in_array($p, \App\Support\BulkMail::available(), true)
                        ? $describe($p)
                        : __('সেট করা নেই: ENV_FILE-এ').' '.($p === 'brevo' ? 'MAIL_BREVO_HOST' : 'MAIL_BULK_HOST').' '.__('লাইনটি যোগ করে আবার deploy করুন')])->all()),
                // D164: where Brevo reports hard bounces and complaints. Admins only; the address holds a secret.
                \Filament\Forms\Components\Placeholder::make('brevo_webhook')->label(__('Brevo-র ফেরত চিঠির খবর'))
                    ->visible(fn () => in_array('brevo', \App\Support\BulkMail::available(), true))
                    ->content(fn () => new \Illuminate\Support\HtmlString('<span style="font-size:.9rem">'.e(__('Brevo → Transactional → Settings → Webhook-এ এই ঠিকানাটি বসান, আর Hard bounce, Spam, Invalid email, Blocked, Unsubscribed টিক দিন। ঠিকানাটি কাউকে দেখাবেন না।'))
                        .'</span><code style="display:block;margin-top:.5rem;padding:.5rem .75rem;border-radius:.5rem;background:rgba(127,127,127,.15);word-break:break-all;user-select:all">'.e(\App\Http\Controllers\BrevoWebhookController::address()).'</code>')),
            ])
            ->modalSubmitActionLabel(__('রাখুন'))
            ->action(function (array $data) {
                \App\Support\BulkMail::choose($data['provider'], auth()->id());
                \Filament\Notifications\Notification::make()->title(__('এখন থেকে চিঠি যাবে').' '.\App\Support\BulkMail::label($data['provider']).' '.__('দিয়ে'))->success()->send();
            });
    }

    /** D190: how often the team drafts the readers' letter: every two weeks (the 1st and 3rd Sunday) or every week. Admins only. */
    public static function rhythmAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('rhythm')
            ->label(fn () => __('পাঠকের চিঠি').': '.(\App\Ai\EditorJobs::rhythm() === 'weekly' ? __('প্রতি সপ্তাহে') : __('দু সপ্তাহে একবার')))
            ->icon('heroicon-o-calendar-days')->color('gray')
            ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin) ?? false)
            ->modalHeading(__('পাঠকের চিঠি কত ঘন ঘন যাবে'))
            ->modalDescription(fn () => __('দল শনিবার সন্ধ্যায় চিঠির খসড়া তৈরি করে; আপনি দেখে পাঠান। পরের চিঠি').': '.\App\Support\Bn::date(\App\Ai\EditorJobs::nextLetter()).'।')
            ->schema([
                \Filament\Forms\Components\Radio::make('rhythm')->label(__('কত ঘন ঘন'))->required()->default(fn () => \App\Ai\EditorJobs::rhythm())
                    ->options(['biweekly' => __('দু সপ্তাহে একবার'), 'weekly' => __('প্রতি সপ্তাহে')])
                    ->descriptions(['biweekly' => __('মাসের প্রথম ও তৃতীয় রবিবার: মাসে দুটি চিঠি। চিঠিতে গত দু সপ্তাহের লেখা থাকে।'), 'weekly' => __('প্রতি রবিবার: মাসে চার-পাঁচটি চিঠি। ই-মেলের মাসিক সীমা দেখে নিন।')]),
            ])
            ->modalSubmitActionLabel(__('রাখুন'))
            ->action(function (array $data) {
                \App\Models\Setting::put(\App\Ai\EditorJobs::RHYTHM, $data['rhythm'] === 'weekly' ? 'weekly' : 'biweekly');
                \App\Models\AuditLog::record('newsletter.rhythm', null, ['rhythm' => $data['rhythm'], 'by' => auth()->id()]);
                \Filament\Notifications\Notification::make()->title(__('রাখা হয়েছে'))->success()->send();
            });
    }
}
