<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AccountController;
use App\Support\Url;
use Closure;
use Illuminate\Http\Request;

/**
 * D59: a new account can do nothing (account page, submitting, commenting as a member) until its
 * email is verified. Enforced wherever real email is configured (otherwise nobody could verify).
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && AccountController::verificationRequired() && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'আগে ই-মেল যাচাই করুন।'], 403)
                : redirect(Url::to('/email/verify'));
        }

        return $next($request);
    }
}
