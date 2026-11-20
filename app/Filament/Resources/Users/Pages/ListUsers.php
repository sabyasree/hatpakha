<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * D244 (owner: "how many people logged in with Google, and how many the normal way; I need the count in desk").
     * Accounts that have ever signed in with Google against those that use e-mail and password only, and which way
     * people came in during the last 30 days. Accounts marked as spam are left out.
     */
    public function getSubheading(): ?string
    {
        $n = fn (int $x) => \App\Support\Bn::num(number_format($x));
        $real = fn () => \App\Models\User::query()->whereNull('spam_reason');
        $since = now()->subDays(30);

        return __('Google দিয়ে সাইন ইন করেছেন: :g জন · শুধু ই-মেল আর পাসওয়ার্ডে: :p জন। গত ৩০ দিনে ঢুকেছেন: Google দিয়ে :g30 জন, পাসওয়ার্ড দিয়ে :p30 জন।', [
            'g' => $n($real()->whereNotNull('google_id')->count()), 'p' => $n($real()->whereNull('google_id')->count()),
            'g30' => $n($real()->where('last_login_via', 'google')->where('last_login_at', '>=', $since)->count()),
            'p30' => $n($real()->where('last_login_via', 'password')->where('last_login_at', '>=', $since)->count()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // D85 (owner: "instead of a new account, can admin view as a writer, one with many posts, as a demo").
            \Filament\Actions\Action::make('viewAs')->label(__('লেখকের চোখে দেখুন'))->icon('heroicon-o-eye')->color('gray')
                ->modalHeading(__('একজন লেখক বা পাঠকের চোখে সাইটটি দেখুন'))
                ->modalDescription(__('তাঁর অ্যাকাউন্ট আর সাইট ঠিক যেমন তিনি দেখেন, তেমন দেখাবে। শুধু দেখা যাবে: কিছু বদলানো, পাঠানো বা মুছে ফেলা যাবে না। ৩০ মিনিট পরে বা "বন্ধ করুন" টিপলে শেষ হবে। কে কবে দেখলেন তা লগে থাকে।'))
                ->schema([
                    \Filament\Forms\Components\Select::make('user')->label(__('কার চোখে'))->required()->searchable()
                        ->options(fn () => \App\Http\Middleware\ViewAs::choices())
                        ->getSearchResultsUsing(fn (string $search) => \App\Http\Middleware\ViewAs::choices($search))
                        ->getOptionLabelUsing(fn ($value) => \App\Http\Middleware\ViewAs::choiceLabel($value))
                        ->default(fn () => array_key_first(\App\Http\Middleware\ViewAs::choices()))
                        ->helperText(__('সবচেয়ে বেশি লেখা যাঁর, তিনি প্রথমে।')),
                ])
                ->modalSubmitActionLabel(__('দেখুন'))
                ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin))
                ->action(function (array $data) {
                    $to = ($u = \App\Models\User::find($data['user'])) ? \App\Http\Middleware\ViewAs::start($u) : null;
                    $to ? $this->redirect($to) : \Filament\Notifications\Notification::make()->title(__('এই অ্যাকাউন্টটি দেখা যাবে না'))->danger()->send();
                }),
            CreateAction::make(),
        ];
    }
}
