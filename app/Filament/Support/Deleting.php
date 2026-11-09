<?php

namespace App\Filament\Support;

use App\Enums\Role;
use App\Models\AuditLog;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Delete buttons for every desk list and edit page (owner, 2026-11-09: "there are no delete options").
 * Admins only (CLAUDE.md §3: deleting content is the owner's call). Always asks first, says what happens,
 * skips records that can't be deleted safely (with the reason), and writes the audit log.
 * Pieces go to the trash (restorable); everything else is deleted for good.
 *
 * The rules per kind of record (what the confirmation says, and when deleting is refused) are in rules().
 */
final class Deleting
{
    public static function isAdmin(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    private static function soft(Model $m): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($m), true);
    }

    /**
     * What deleting means for each kind of record, and when it is refused (never break an old URL, never orphan pieces).
     *
     * @return array{0: ?string, 1: ?Closure} [warning, blocked(Model): ?string]
     */
    public static function rules(string $class): array
    {
        return match ($class) {
            \App\Models\Post::class => [__('প্রকাশিত লেখা হলে পাঠকেরা আর দেখতে পাবেন না; তার লিংক “আর নেই” (৪১০) দেখাবে।'), null],
            \App\Models\Author::class => [__('লেখকের পাতাটি আর থাকবে না।'),
                fn ($r) => $r->posts()->withTrashed()->exists() ? __('লেখকের নামে লেখা আছে; আগে লেখাগুলো অন্য লেখকের নামে দিন') : null],
            \App\Models\Section::class => [__('বিভাগের পাতাটি আর থাকবে না।'),
                fn ($r) => $r->posts()->withTrashed()->exists() ? __('এই বিভাগে লেখা আছে; আগে লেখাগুলো অন্য বিভাগে সরান') : null],
            \App\Models\Issue::class => [__('সংখ্যার লেখাগুলো থাকবে, শুধু এই সংখ্যা থেকে আলাদা হবে।'),
                fn ($r) => $r->status === 'published' ? __('প্রকাশিত সংখ্যা মোছা যায় না (পুরনো লিংক ভাঙবে); আগে সংখ্যাটি খুলে অবস্থা বদলান') : null],
            \App\Models\Page::class => [__('পাতাটি আর থাকবে না।'),
                fn ($r) => $r->status === 'published' ? __('প্রকাশিত পাতা মোছা যায় না (পুরনো লিংক ভাঙবে); আগে পাতাটি খুলে অপ্রকাশিত করুন') : null],
            \App\Models\Newsletter::class => [__('চিঠি ও তার পাঠানোর হিসাব মুছে যাবে। যাঁরা আগে পেয়েছেন, তাঁদের কাছে চিঠি থেকে যাবে।'),
                fn ($r) => in_array($r->status, ['queued', 'sending'], true) ? __('চিঠিটি এখন পাঠানো হচ্ছে; আগে বাতিল করুন') : null],
            \App\Models\User::class => [__('অ্যাকাউন্টটি মুছে যাবে; এই মানুষের প্রকাশিত লেখা থেকে যাবে।'),
                fn ($r) => $r->is(auth()->user()) ? __('নিজের অ্যাকাউন্ট এখান থেকে মোছা যায় না') : null],
            \App\Models\Serial::class => [__('ধারাবাহিকের পাতা ও অনুসরণকারীদের তালিকা মুছে যাবে; পর্বগুলো সাধারণ লেখা হিসেবে থেকে যাবে।'), null],
            \App\Models\Collection::class => [__('সংকলনের পাতাটি আর থাকবে না; লেখাগুলো যেমন আছে তেমনই থাকবে।'), null],
            \App\Models\Contest::class => [__('প্রতিযোগিতার লেখাগুলো থাকবে, শুধু প্রতিযোগিতার সঙ্গে যোগ মুছে যাবে।'), null],
            \App\Models\Redirect::class => [__('পুরনো লিংকটি আর নতুন জায়গায় যাবে না, পাঠক “পাওয়া যায়নি” দেখবেন।'), null],
            \App\Models\WritingFeedback::class => [__('এই নির্দেশিকা আর কোনো লেখায় মানা হবে না।'), null],
            \App\Models\Comment::class => [__('মন্তব্যটি মুছে যাবে।'), null],
            default => [null, null],
        };
    }

    /** Why this record must not be deleted, or null. */
    public static function blockedReason(Model $record): ?string
    {
        $blocked = self::rules($record::class)[1];

        return $blocked ? $blocked($record) : null;
    }

    /** The delete button on an edit page: after deleting, back to the list. */
    public static function page(string $resource): Action
    {
        return self::row()->successRedirectUrl($resource::getUrl('index'));
    }

    public static function row(): Action
    {
        return Action::make('delete')->label(__('মুছুন'))->icon('heroicon-o-trash')->color('danger')
            ->visible(fn (?Model $record = null) => self::isAdmin() && (! $record || ! (self::soft($record) && $record->trashed())))
            ->requiresConfirmation()
            ->modalHeading(__('মুছে ফেলবেন?'))
            ->modalDescription(fn (?Model $record = null) => self::describe($record, $record ? self::rules($record::class)[0] : null))
            ->modalSubmitActionLabel(__('হ্যাঁ, মুছুন'))
            ->action(function (Model $record, Action $action) {
                if (! self::isAdmin()) {
                    return;
                }
                if ($why = self::blockedReason($record)) {
                    Notification::make()->title(__('মোছা গেল না'))->body($why)->warning()->send();

                    return;
                }
                self::remove($record);
                Notification::make()->title(self::soft($record) ? __('ট্র্যাশে সরানো হয়েছে; চাইলে ফেরানো যায়।') : __('মুছে ফেলা হয়েছে'))->success()->send();
                $action->success();
            });
    }

    public static function bulk(string $class): BulkAction
    {
        $warning = self::rules($class)[0];

        return BulkAction::make('deleteSelected')->label(__('বেছে নেওয়াগুলো মুছুন'))->icon('heroicon-o-trash')->color('danger')
            ->visible(fn () => self::isAdmin())
            ->requiresConfirmation()
            ->modalHeading(__('বেছে নেওয়াগুলো মুছে ফেলবেন?'))
            ->modalDescription(trim(($warning ? $warning.' ' : '').__('যেগুলো নিরাপদে মোছা যায় না, সেগুলো বাদ থাকবে।')))
            ->modalSubmitActionLabel(__('হ্যাঁ, মুছুন'))
            ->action(function (Collection $records) {
                if (! self::isAdmin()) {
                    return;
                }
                $done = 0;
                $skipped = [];
                foreach ($records as $r) {
                    if ($why = self::blockedReason($r)) {
                        $skipped[] = $why;

                        continue;
                    }
                    self::remove($r);
                    $done++;
                }
                $n = fn (int $i) => \App\Support\Bn::num((string) $i);
                Notification::make()->title($n($done).__('টি মুছে ফেলা হয়েছে'))
                    ->body($skipped ? $n(count($skipped)).__('টি বাদ রইল: ').implode(' · ', array_unique($skipped)) : null)
                    ->{$skipped ? 'warning' : 'success'}()->send();
            })->deselectRecordsAfterCompletion();
    }

    /** Trash (pieces): bring back, or delete for good. */
    public static function trashActions(): array
    {
        $trashed = fn (?Model $record = null) => self::isAdmin() && $record && self::soft($record) && $record->trashed();

        return [
            Action::make('restoreTrashed')->label(__('ফিরিয়ে আনুন'))->icon('heroicon-o-arrow-uturn-left')->color('success')
                ->visible($trashed)
                ->action(function (Model $record) {
                    $record->restore();
                    AuditLog::record(self::event($record, 'restored'), $record instanceof \App\Models\Post ? $record : null, ['id' => $record->getKey()]);
                    Notification::make()->title(__('ফিরিয়ে আনা হয়েছে'))->success()->send();
                }),
            Action::make('forceDelete')->label(__('চিরতরে মুছুন'))->icon('heroicon-o-x-circle')->color('danger')
                ->visible($trashed)->requiresConfirmation()->modalHeading(__('চিরতরে মুছে ফেলবেন?'))
                ->modalDescription(__('এটি আর ফেরানো যাবে না। লেখা, তার মন্তব্য, প্রতিক্রিয়া ও এআই রিপোর্ট সব মুছে যাবে।'))
                ->modalSubmitActionLabel(__('হ্যাঁ, চিরতরে মুছুন'))
                ->action(function (Model $record) {
                    AuditLog::record(self::event($record, 'force_deleted'), null, ['id' => $record->getKey(), 'title' => self::title($record)]);
                    $record->forceDelete();
                    Notification::make()->title(__('চিরতরে মুছে ফেলা হয়েছে'))->success()->send();
                }),
        ];
    }

    public static function trashBulk(): array
    {
        return [
            BulkAction::make('restoreSelected')->label(__('বেছে নেওয়াগুলো ফিরিয়ে আনুন'))->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn () => self::isAdmin())
                ->action(function (Collection $records) {
                    $n = 0;
                    foreach ($records as $r) {
                        if (self::soft($r) && $r->trashed()) {
                            $r->restore();
                            AuditLog::record(self::event($r, 'restored'), $r instanceof \App\Models\Post ? $r : null, ['id' => $r->getKey()]);
                            $n++;
                        }
                    }
                    Notification::make()->title(\App\Support\Bn::num((string) $n).__('টি ফিরিয়ে আনা হয়েছে'))->success()->send();
                })->deselectRecordsAfterCompletion(),
            BulkAction::make('forceDeleteSelected')->label(__('বেছে নেওয়াগুলো চিরতরে মুছুন'))->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn () => self::isAdmin())->requiresConfirmation()
                ->modalDescription(__('শুধু ট্র্যাশে থাকা লেখাগুলো চিরতরে মুছবে। এটি আর ফেরানো যাবে না।'))
                ->action(function (Collection $records) {
                    $n = 0;
                    foreach ($records as $r) {
                        if (self::soft($r) && $r->trashed()) {
                            AuditLog::record(self::event($r, 'force_deleted'), null, ['id' => $r->getKey(), 'title' => self::title($r)]);
                            $r->forceDelete();
                            $n++;
                        }
                    }
                    Notification::make()->title(\App\Support\Bn::num((string) $n).__('টি চিরতরে মুছে ফেলা হয়েছে'))->success()->send();
                })->deselectRecordsAfterCompletion(),
        ];
    }

    public static function remove(Model $record): void
    {
        AuditLog::record(self::event($record, 'deleted'), $record instanceof \App\Models\Post ? $record : null, ['id' => $record->getKey(), 'title' => self::title($record)]);
        $record->delete();
    }

    private static function describe(?Model $record, ?string $warning): string
    {
        $soft = $record && self::soft($record);

        return trim(($warning ? $warning.' ' : '').($soft
            ? __('লেখাটি ট্র্যাশে যাবে; “ট্র্যাশ” থেকে ফেরানো যায়।')
            : __('এটি আর ফেরানো যাবে না।')));
    }

    private static function event(Model $m, string $what): string
    {
        return \Illuminate\Support\Str::snake(class_basename($m)).'.'.$what;
    }

    private static function title(Model $m): string
    {
        return mb_substr((string) ($m->title ?? $m->name ?? $m->subject ?? $m->from_path ?? $m->email ?? '#'.$m->getKey()), 0, 120);
    }
}
