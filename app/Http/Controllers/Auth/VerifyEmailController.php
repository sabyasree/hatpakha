<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Url;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function notice()
    {
        return view('auth.verify', ['seo' => AuthController::seo('ইমেল যাচাই')]);
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect(Url::to('/account'))->with('status', 'ইমেল যাচাই হয়ে গেছে। ধন্যবাদ!');
    }

    public function resend(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'যাচাইয়ের লিংক আবার পাঠানো হয়েছে।');
    }
}
