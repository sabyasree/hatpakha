<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\Role;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only history of every decision taken in /desk (who, what, when). Admins only. */
class AuditLogResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'কাজের খাতা';

    protected static ?string $pluralModelLabel = 'কাজের খাতা';

    protected static string|\UnitEnum|null $navigationGroup = 'প্রশাসন';

    protected static ?int $navigationSort = 80;

    /** Plain-language names for the logged actions. */
    public const ACTIONS = [
        'submission.approved' => 'লেখা মনোনীত', 'submission.changes' => 'বদল চাওয়া হলো', 'submission.rejected' => 'লেখা বাতিল',
        'comment.approved' => 'মন্তব্য প্রকাশ', 'comment.spam' => 'মন্তব্য স্প্যাম', 'comment.pending' => 'মন্তব্য অপেক্ষায় ফেরত',
        'post.audit_applied' => 'অডিট সুপারিশ মানা হলো', 'post.audit_dismissed' => 'অডিট সুপারিশ বাদ', 'post.cover_removed' => 'প্রচ্ছদ সরানো', 'ai.agent_paused' => 'এআই সদস্য থামানো', 'ai.agent_resumed' => 'এআই সদস্য চালু', 'seo.applied' => 'এসইও তথ্য বসানো', 'marketing.posted' => 'মার্কেটিং পোস্ট করা', 'chat.escalated' => 'চ্যাটের প্রশ্ন আপনাকে', 'contest.created' => 'প্রতিযোগিতা তৈরি', 'contest.winners' => 'প্রতিযোগিতার বিজয়ী', 'collection.approved' => 'সংকলন অনুমোদন', 'tag.merged' => 'ট্যাগ মেলানো', 'author.honoured' => 'লেখককে সম্মান', 'post.audio_uploaded' => 'লেখকের পাঠ জমা', 'post.audio_approved' => 'লেখকের পাঠ অনুমোদন', 'seo.undone' => 'এসইও বদল ফেরানো', 'seo.mode_changed' => 'এসইও নিয়ম বদল', 'issue.proposed' => 'সংখ্যার প্রস্তাব (এআই)', 'issue.approved' => 'সংখ্যা অনুমোদন', 'editorial.drafted' => 'সম্পাদকীয় খসড়া (এআই)', 'editorial.published' => 'সম্পাদকীয় প্রকাশ', 'post.ai_images_approved' => 'এআই-এর ছবি অনুমোদন', 'post.copyedits_applied' => 'সংশোধন বসানো', 'submission.confirm_requested' => 'লেখকের সম্মতি চাওয়া', 'submission.writer_objected' => 'লেখকের আপত্তি', 'submission.received' => 'নতুন জমা', 'submission.resubmitted' => 'আবার জমা', 'post.slug_changed' => 'লেখার ঠিকানা বদল (৩০১)',
        'user.role_changed' => 'ভূমিকা বদল', 'user.created' => 'নতুন অ্যাকাউন্ট', 'user.reset_link_sent' => 'পাসওয়ার্ড লিংক পাঠানো',
        'ai.rubric_changed' => 'এআই মানদণ্ড বদল', 'ai.blind_test_revealed' => 'এআই পরীক্ষার ফল খোলা', 'ai.paused' => 'এআই থামানো', 'ai.resumed' => 'এআই আবার চালু',
        'user.registered' => 'নতুন নিবন্ধন', 'user.password_reset' => 'পাসওয়ার্ড রিসেট', 'user.password_set' => 'পাসওয়ার্ড সেট (কমান্ড)',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function canCreate(): bool { return false; }

    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool { return false; }

    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'subject']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('কখন'))->dateTime('d M Y, H:i')->sortable()
                    ->description(fn (AuditLog $record) => $record->created_at?->diffForHumans()),
                TextColumn::make('user.name')->label(__('কে'))->placeholder(__('সিস্টেম / কমান্ড'))->searchable(),
                TextColumn::make('action')->label(__('কী'))->badge()
                    ->formatStateUsing(fn ($state) => __(self::ACTIONS[$state] ?? $state)),
                TextColumn::make('subject')->label(__('কোনটি'))->wrap()
                    ->state(fn (AuditLog $record) => $record->subject?->title ?? $record->subject?->name ?? $record->subject?->email
                        ?? ($record->subject_type ? class_basename($record->subject_type).' #'.$record->subject_id : '—')),
                TextColumn::make('meta')->label(__('বিস্তারিত'))->wrap()->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (AuditLog $record) => $record->meta ? json_encode($record->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—'),
            ])
            ->filters([
                SelectFilter::make('action')->label(__('কাজ'))->options(\App\Support\Lang::t(self::ACTIONS)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
