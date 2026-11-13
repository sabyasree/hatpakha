<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\PieceReview;
use App\Models\Post;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** D168: giving a piece to human reviewers (যাচাইকারী) from /desk, and the one form for it. */
final class PieceReviews
{
    /** People the admin marked as reviewers (never the person assigning). */
    public static function reviewerOptions(): array
    {
        return User::where('is_reviewer', true)->whereNull('spam_reason')->orderBy('name')->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name.' · '.$u->email])->all();
    }

    /**
     * Assigns the piece; each new reviewer gets one e-mail. Someone who already has it keeps their review (a withdrawn
     * one is opened again). Returns how many were newly asked.
     *
     * @param  list<int|string>  $reviewerIds
     */
    public static function assign(Post $post, array $reviewerIds, ?string $note, ?string $due, User $by): int
    {
        $asked = 0;
        foreach (User::whereIn('id', $reviewerIds)->where('is_reviewer', true)->get() as $reviewer) {
            $review = PieceReview::firstOrNew(['post_id' => $post->id, 'reviewer_id' => $reviewer->id]);
            if ($review->exists && $review->status !== 'withdrawn') {
                continue;
            }
            $review->fill([
                'assigned_by' => $by->id, 'request_note' => filled($note) ? mb_substr(trim(strip_tags($note)), 0, 1000) : null,
                'due_on' => $due ?: null, 'status' => 'assigned', 'verdict' => null, 'rating' => null, 'liked' => null,
                'improve' => null, 'after_ai' => null, 'submitted_at' => null, 'desk_read_at' => null, 'opened_at' => null,
            ])->save();
            AuditLog::record('review.assigned', $post, ['reviewer' => $reviewer->id, 'by' => $by->id]);
            $reviewer->notify(new \App\Notifications\TemplateMail('review_assigned', [
                '{লেখা}' => (string) $post->title,
                '{অনুরোধ}' => (string) ($review->request_note ?? ''),
                '{তারিখ}' => $review->due_on ? Bn::date($review->due_on) : '',
            ], Url::to('/account/review/'.$review->id)));
            $asked++;
        }

        return $asked;
    }

    /** The "যাচাইয়ে পাঠান" action for a piece (posts list and edit page). */
    public static function assignAction(): Action
    {
        return Action::make('sendForReview')->label(__('যাচাইয়ে পাঠান'))->icon('heroicon-o-user-group')->color('gray')
            ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin))
            ->modalHeading(__('মানুষ যাচাইকারীকে পড়তে দিন'))
            ->modalDescription(__('যাচাইকারী নিজের অ্যাকাউন্টে লেখাটি পড়ে মত দেবেন (লেখকের নাম দেখবেন না, প্রকাশিত লেখা ছাড়া)। নিজের মত দেওয়ার পরে এআই-এর রিপোর্ট দেখতে পাবেন। মতামত শুধু ডেস্কে আসে, লেখকের কাছে যায় না।'))
            ->schema(fn (Post $record) => [
                \Filament\Forms\Components\CheckboxList::make('reviewers')->label(__('কাকে কাকে'))->required()
                    ->options(self::reviewerOptions())
                    ->disableOptionWhen(fn (string $value) => PieceReview::where('post_id', $record->id)->where('reviewer_id', $value)->where('status', '!=', 'withdrawn')->exists())
                    ->helperText(empty(self::reviewerOptions())
                        ? __('এখনও কোনো যাচাইকারী নেই। প্রশাসন → ব্যবহারকারী থেকে কারও অ্যাকাউন্টে "যাচাইকারী" চালু করুন।')
                        : __('যাঁদের আগেই দেওয়া হয়েছে তাঁদের নাম ধূসর।')),
                \Filament\Forms\Components\Textarea::make('note')->label(__('যাচাইকারীকে অনুরোধ (ঐচ্ছিক)'))->rows(3)->maxLength(1000)
                    ->placeholder(__('যেমন: শেষটা কেমন লাগল, বিশেষ করে জানাবেন।')),
                \Filament\Forms\Components\DatePicker::make('due')->label(__('কবের মধ্যে (ঐচ্ছিক)'))->native(false)->minDate(today()),
            ])
            ->modalSubmitActionLabel(__('পাঠান'))
            ->action(function (Post $record, array $data) {
                $n = self::assign($record, (array) $data['reviewers'], $data['note'] ?? null, $data['due'] ?? null, auth()->user());
                Notification::make()->title($n ? __('যাচাইয়ে পাঠানো হয়েছে') : __('নতুন কাউকে পাঠানো হয়নি'))
                    ->body($n ? __(':n জনকে ই-মেলে জানানো হয়েছে।', ['n' => Bn::num((string) $n)]) : __('বাছা সবাই আগেই লেখাটি পেয়েছেন।'))
                    ->{$n ? 'success' : 'warning'}()->send();
            });
    }
}
