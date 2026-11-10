<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Url;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect(Url::to('/account'));
        }
        // Launch day, 2026-11-10: accounts that came from WordPress never got a verification e-mail (only new sign-ups
        // did), yet this page says one was sent. Send it the first time they land here, then at most once per 30 minutes.
        if (\App\Http\Controllers\AccountController::verificationRequired() && self::claimSend($request->user()->id)) {
            // A mail problem must never break this page; it's logged, and "লিংক আবার পাঠান" stays available.
            rescue(fn () => $request->user()->sendEmailVerificationNotification());
        }

        return view('auth.verify', ['seo' => AuthController::seo('ইমেল যাচাই')]);
    }

    /** D59: the signed link verifies the address and signs the writer in (no password needed again). */
    public function verify(Request $request, int $id, string $hash)
    {
        $user = \App\Models\User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        $firstUse = ! $user->hasVerifiedEmail();
        if ($firstUse) {
            $user->markEmailAsVerified();
            event(new \Illuminate\Auth\Events\Verified($user));
            \App\Models\AuditLog::record('user.email_verified', $user);
        }
        if (\Illuminate\Support\Facades\Auth::id() === $user->id) {
            return redirect(Url::to('/account'))->with('status', 'ইমেল যাচাই হয়ে গেছে। হাতপাখায় স্বাগত!');
        }
        // Signs in only on the link's first use, and never accounts with 2FA or desk access:
        // an old or forwarded link must not work like a password.
        if ($firstUse && blank($user->app_authentication_secret) && ! $user->hasRole(\App\Enums\Role::Editor)) {
            \Illuminate\Support\Facades\Auth::logout();
            \Illuminate\Support\Facades\Auth::login($user);
            $request->session()->regenerate();

            return redirect(Url::to('/account'))->with('status', 'ইমেল যাচাই হয়ে গেছে। হাতপাখায় স্বাগত!');
        }

        return redirect(Url::to('/login'))->with('status', 'ইমেল যাচাই হয়ে গেছে। এবার লগ-ইন করুন।');
    }

    /** True once per 30 minutes per account (the registration e-mail counts too). */
    public static function claimSend(int $userId): bool
    {
        return \Illuminate\Support\Facades\Cache::add('verify.sent.'.$userId, 1, now()->addMinutes(30));
    }

    public function resend(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'যাচাইয়ের লিংক আবার পাঠানো হয়েছে।');
    }
}
