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

    public function resend(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'যাচাইয়ের লিংক আবার পাঠানো হয়েছে।');
    }
}
