<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Seo\Seo;
use App\Support\Url;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Reader/writer accounts on the public site (Phase 3a). Bengali UI, docs/SECURITY.md §2:
 * throttled, no account enumeration, session regenerated, legacy WordPress passwords accepted once.
 */
class AuthController extends Controller
{
    public static function seo(string $title): Seo
    {
        return new Seo(title: $title, noindex: true);
    }

    public function showLogin(Request $request)
    {
        // "Log in to comment" brings the reader back to the post. On-site paths only (no open redirect).
        $next = (string) $request->query('next', '');
        if (str_starts_with($next, '/') && ! str_starts_with($next, '//') && ! preg_match('/[\s\\\\]/', $next)) {
            [$path, $hash] = array_pad(explode('#', $next, 2), 2, null);
            $request->session()->put('url.intended', Url::to(rtrim($path, '/')).($hash ? '#'.$hash : ''));
        }

        return view('auth.login', ['seo' => self::seo('লগ-ইন')]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'password' => ['required', 'string', 'max:200']]);

        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'ইমেল বা পাসওয়ার্ড মিলছে না।']);
        }
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(Url::to('/account'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/').'/');
    }

    public function showRegister()
    {
        // Encrypted timestamp: a form posted back in under 3 seconds is a bot.
        return view('auth.register', ['seo' => self::seo('অ্যাকাউন্ট খুলুন'), 'started' => Crypt::encryptString((string) now()->timestamp)]);
    }

    public function register(Request $request)
    {
        // Honeypot: real people never see or fill the "website" field.
        if (filled($request->input('website')) || ! $this->humanSpeed((string) $request->input('started'))) {
            throw ValidationException::withMessages(['email' => 'কিছু একটা ঠিক হয়নি। পাতাটি আবার খুলে চেষ্টা করুন।']);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
            'terms' => ['accepted'],
        ], [
            'email.unique' => 'এই ইমেলে আগেই অ্যাকাউন্ট আছে। লগ-ইন করুন, বা পাসওয়ার্ড ভুলে গেলে নতুন পাসওয়ার্ড নিন।',
            'terms.accepted' => 'শর্তাবলি মেনে নিতে হবে।',
            'password.confirmed' => 'দুটি পাসওয়ার্ড মেলেনি।',
        ]);

        $user = new User();
        $user->forceFill([
            'name' => strip_tags($data['name']),
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'role' => Role::Reader,
        ])->save();
        AuditLog::record('user.registered', $user);
        event(new Registered($user)); // sends the verification email when real mail is configured

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(Url::to('/account'));
    }

    public function showForgot()
    {
        return view('auth.forgot', ['seo' => self::seo('পাসওয়ার্ড ভুলে গেছেন')]);
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email', 'max:190']]);
        Password::sendResetLink(['email' => strtolower((string) $request->input('email'))]);

        // Same answer whether or not the account exists (no account enumeration).
        return back()->with('status', 'এই ইমেলে অ্যাকাউন্ট থাকলে নতুন পাসওয়ার্ড বানানোর লিংক পাঠানো হয়েছে। ইনবক্স আর স্প্যাম ফোল্ডার দেখুন।');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['seo' => self::seo('নতুন পাসওয়ার্ড'), 'token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ], ['password.confirmed' => 'দুটি পাসওয়ার্ড মেলেনি।']);

        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'legacy_password' => null, 'remember_token' => Str::random(60)])->save();
            AuditLog::record('user.password_reset', $user);
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'লিংকটি পুরনো বা ভুল। আবার "পাসওয়ার্ড ভুলে গেছেন" থেকে নতুন লিংক নিন।']);
        }

        return redirect(Url::to('/login'))->with('status', 'পাসওয়ার্ড বদলে গেছে। এবার লগ-ইন করুন।');
    }

    private function humanSpeed(string $started): bool
    {
        try {
            $t = (int) Crypt::decryptString($started);
        } catch (\Throwable) {
            return false;
        }

        return now()->timestamp - $t >= 3 && now()->timestamp - $t < 86400;
    }
}
