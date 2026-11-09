<?php

namespace App\Filament\Resources\WritingRules;

use App\Enums\Role;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\WritingRules\Pages\CreateWritingRule;
use App\Filament\Resources\WritingRules\Pages\EditWritingRule;
use App\Filament\Resources\WritingRules\Pages\ListWritingRules;
use App\Models\Section;
use App\Models\Serial;
use App\Models\WritingFeedback;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * D113 (owner): the standing rules the AI writers follow, from human readers' feedback or written by the owner.
 * Edit, switch off or delete any of them; a newer rule wins over an older one.
 */
class WritingRuleResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = WritingFeedback::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'লেখার নির্দেশ';

    protected static ?string $pluralModelLabel = 'লেখার নির্দেশিকা';

    protected static string|\UnitEnum|null $navigationGroup = 'আরও';

    protected static ?int $navigationSort = 18;

    protected static ?string $slug = 'writing-rules';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('apply_future', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Textarea::make('lesson')->label(__('নির্দেশ (এআই লেখকেরা এটাই মেনে চলবে)'))->required()->rows(4)->maxLength(2000),
            Radio::make('scope')->label(__('কোন লেখায়'))->options(\App\Support\Lang::t(WritingFeedback::SCOPES))->required()->default('all')->live(),
            Select::make('section_id')->label(__('বিভাগ'))->options(fn () => Section::where('is_active', true)->orderBy('sort')->pluck('name_bn', 'id'))
                ->visible(fn ($get) => $get('scope') === 'section')->required(fn ($get) => $get('scope') === 'section'),
            Select::make('serial_id')->label(__('ধারাবাহিক'))->options(fn () => Serial::latest('id')->pluck('title', 'id'))
                ->visible(fn ($get) => $get('scope') === 'serial')->required(fn ($get) => $get('scope') === 'serial'),
            Toggle::make('active')->label(__('চালু'))->default(true),
            Textarea::make('body')->label(__('মূল মতামত'))->rows(3)->disabled()->dehydrated(false)->visible(fn (?WritingFeedback $record) => $record?->post_id),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('lesson')->label(__('নির্দেশ'))->wrap()->placeholder(__('লেখা হচ্ছে…')),
                TextColumn::make('scope')->label(__('কোথায়'))->formatStateUsing(fn ($state, WritingFeedback $record) => $record->scopeLabel()),
                TextColumn::make('giver')->label(__('কার মতামত'))->placeholder(__('সম্পাদক'))->description(fn (WritingFeedback $r) => $r->created_at?->format('d/m/Y')),
                ToggleColumn::make('active')->label(__('চালু')),
            ])
            ->recordActions([
                Action::make('piece')->label(__('লেখাটি'))->icon('heroicon-o-document-text')->color('gray')
                    ->visible(fn (WritingFeedback $r) => (bool) $r->post_id)->url(fn (WritingFeedback $r) => PostResource::getUrl('edit', ['record' => $r->post_id])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWritingRules::route('/'), 'create' => CreateWritingRule::route('/create'), 'edit' => EditWritingRule::route('/{record}/edit')];
    }
}
