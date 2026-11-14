<?php

namespace App\Filament\Resources\Serials;

use App\Enums\Role;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Serials\Pages\EditSerial;
use App\Filament\Resources\Serials\Pages\ListSerials;
use App\Models\AuditLog;
use App\Models\EditorTask;
use App\Models\Post;
use App\Models\Serial;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/** D108: serial stories. The AI serial writers propose and draft; the owner starts, edits and publishes each episode. */
class SerialResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = Serial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'ধারাবাহিক';

    protected static ?string $pluralModelLabel = 'ধারাবাহিক';

    protected static string|\UnitEnum|null $navigationGroup = 'আরও';

    protected static ?int $navigationSort = 19;

    public const STATUS = ['proposed' => 'প্রস্তাব', 'active' => 'চলছে', 'paused' => 'থামানো', 'finished' => 'শেষ'];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;   // the serial writers propose; see "নতুন ধারাবাহিক চাই"
    }

    public static function getNavigationBadge(): ?string
    {
        return ($n = Serial::where('status', 'proposed')->count()) ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('title')->label(__('নাম'))->required()->maxLength(150),
            Textarea::make('logline')->label(__('এক-দুই লাইনে গল্প (পাঠক দেখবেন)'))->rows(2)->maxLength(400),
            Select::make('day')->label(__('নতুন পর্ব কোন দিন'))->options(\App\Support\Lang::t(Serial::DAYS))->required(),
            // D193: a writer's own serial: who proposed it and their plan for the whole story
            Section::make(__('লেখকের প্রস্তাব'))->visible(fn (?Serial $record) => (bool) $record?->user_id)->schema([
                \Filament\Forms\Components\Placeholder::make('writer_plan')->hiddenLabel()->content(fn (?Serial $record) => new HtmlString(
                    '<p><b>'.e(__('লেখক')).':</b> '.e((string) \App\Models\User::find($record?->user_id)?->name).' · <b>'.e(__('আনুমানিক পর্ব')).':</b> '.e(\App\Support\Bn::num((string) (int) $record?->planned_count)).'</p>'
                    .'<p style="white-space:pre-line;margin-top:.5rem">'.e((string) $record?->plan).'</p>'
                    .'<p style="opacity:.75;margin-top:.5rem">'.e(__('প্রথম পর্বটি “লেখা” পাতায় অপেক্ষায় আছে। সেটি মনোনীত করলে ধারাবাহিক শুরু হবে; না নিলে ধারাবাহিকও শুরু হবে না। এই ধারাবাহিকের পর্ব লেখক নিজেই লেখেন, এআই লেখে না।')).'</p>')),
            ]),
            Section::make(__('গল্পের পরিকল্পনা (শুধু ডেস্কে)'))->collapsible()->visible(fn (?Serial $record) => ! $record?->user_id)->schema([
                \Filament\Forms\Components\Placeholder::make('bible_view')->hiddenLabel()->content(fn (?Serial $record) => new HtmlString(self::bibleHtml($record))),
            ]),
            Section::make(__('পর্ব'))->collapsible()->schema([
                \Filament\Forms\Components\Placeholder::make('episodes_view')->hiddenLabel()->content(fn (?Serial $record) => new HtmlString(self::episodesHtml($record))),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('নাম'))->searchable()->description(fn (Serial $s) => __($s->genre ?? '')),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()
                    ->formatStateUsing(fn ($state) => __(self::STATUS[$state] ?? $state))
                    ->color(fn ($state) => ['proposed' => 'warning', 'active' => 'success'][$state] ?? 'gray'),
                TextColumn::make('day')->label(__('দিন'))->formatStateUsing(fn ($state) => __(Serial::DAYS[(int) $state] ?? '')),
                TextColumn::make('episodes_count')->counts('episodes')->label(__('পর্ব')),
            ])
            ->recordActions([
                EditAction::make()->label(__('খুলুন')),
                Action::make('open')->label(__('পাতা দেখুন'))->icon('heroicon-o-eye')->color('gray')
                    ->visible(fn (Serial $record) => $record->isPublic())->url(fn (Serial $record) => $record->url(), shouldOpenInNewTab: true),
                \App\Filament\Support\Deleting::row(),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\App\Filament\Support\Deleting::bulk(\App\Models\Serial::class)])]);
    }

    public static function start(Serial $s): void
    {
        $s->activate();
    }

    private static function bibleHtml(?Serial $s): string
    {
        if (! $s) {
            return '';
        }
        $b = (array) $s->bible;
        $chars = collect((array) ($b['characters'] ?? []))->map(fn ($c) => '<li><b>'.e($c['name'] ?? '').'</b>: '.e($c['role'] ?? '').'</li>')->implode('');
        $eps = collect((array) ($b['episodes'] ?? []))->map(fn ($e, $i) => '<li><b>'.e(\App\Support\Bn::num((string) ($i + 1)).'. '.($e['title'] ?? '')).'</b>: '.e($e['beat'] ?? '').'</li>')->implode('');
        $cover = $s->cover_path ? '<img src="'.e(asset($s->cover_path)).'" alt="" style="max-width:22rem;border-radius:.6rem;margin-bottom:.8rem">' : '';

        return $cover.'<p><b>'.e(__('পটভূমি')).':</b> '.e((string) ($b['setting'] ?? '')).'</p>'
            .'<p style="margin-top:.6rem"><b>'.e(__('চরিত্র')).'</b></p><ul style="padding-left:1.2rem;list-style:disc">'.$chars.'</ul>'
            .'<p style="margin-top:.6rem"><b>'.e(__('পর্বের পরিকল্পনা')).'</b></p><ol style="padding-left:1.2rem">'.$eps.'</ol>';
    }

    private static function episodesHtml(?Serial $s): string
    {
        if (! $s) {
            return '';
        }
        $rows = $s->episodes()->get()->map(fn (Post $p) => '<li style="padding:.3rem 0"><a href="'.e(PostResource::getUrl('edit', ['record' => $p])).'" style="text-decoration:underline;font-weight:600">'
            .e($p->title).'</a> · '.e(__(['draft' => 'খসড়া', 'scheduled' => 'নির্ধারিত', 'published' => 'প্রকাশিত', 'pending' => 'অপেক্ষায়'][$p->status] ?? $p->status))
            .($p->proposed_image_path ? ' · '.e(__('ছবি দেখার অপেক্ষায়')) : '').'</li>')->implode('');

        return $rows ? '<ul>'.$rows.'</ul>' : e(__('এখনও কোনো পর্ব নেই।'));
    }

    public static function getPages(): array
    {
        return ['index' => ListSerials::route('/'), 'edit' => EditSerial::route('/{record}/edit')];
    }
}
