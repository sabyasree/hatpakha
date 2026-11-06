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
        self::checkHuman($request, 'email', 'login');

        $credentials = ['email' => strtolower($data['email']), 'password' => $data['password']];
        if (! Auth::validate($credentials)) {
            AuditLog::record('user.login_failed', null, ['email_hash' => hash('sha256', $credentials['email'])]);
            throw ValidationException::withMessages(['email' => 'ইমেল বা পাসওয়ার্ড মিলছে না।']);
        }
        $user = User::where('email', $credentials['email'])->firstOrFail();
        // D59: with 2FA on, the password alone is not enough; the code is asked on the next page.
        if (filled($user->app_authentication_secret)) {
            $request->session()->put('2fa', ['id' => $user->id, 'remember' => $request->boolean('remember'), 'at' => now()->timestamp]);

            return redirect(Url::to('/login/2fa'));
        }

        return $this->finishLogin($request, $user, $request->boolean('remember'));
    }

    public function showTwoFactor(Request $request)
    {
        if (! $request->session()->has('2fa')) {
            return redirect(Url::to('/login'));
        }

        return view('auth.two-factor', ['seo' => self::seo('দুই-ধাপের যাচাই')]);
    }

    public function twoFactor(Request $request)
    {
        $pending = (array) $request->session()->get('2fa');
        $user = User::find($pending['id'] ?? 0);
        if (! $user || now()->timestamp - (int) ($pending['at'] ?? 0) > 600) {
            $request->session()->forget('2fa');

            return redirect(Url::to('/login'))->withErrors(['email' => 'সময় পেরিয়ে গেছে। আবার লগ-ইন করুন।']);
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $code = preg_replace('/\s+/', '', $data['code']);
        $mfa = \Filament\Auth\MultiFactor\App\AppAuthentication::make();
        $ok = preg_match('/^\d{6}$/', $code)
            ? $mfa->verifyCode($code, $user->app_authentication_secret, shouldPreventCodeReuse: true)
            : $this->useRecoveryCode($user, $code);
        if (! $ok) {
            AuditLog::record('user.2fa_failed', $user);
            throw ValidationException::withMessages(['code' => 'কোডটি মিলছে না। অ্যাপের নতুন কোড দিন।']);
        }
        $request->session()->forget('2fa');

        return $this->finishLogin($request, $user, (bool) ($pending['remember'] ?? false));
    }

    private function useRecoveryCode(User $user, string $code): bool
    {
        $codes = (array) $user->app_authentication_recovery_codes;
        foreach ($codes as $i => $hash) {
            if (is_string($hash) && password_verify($code, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['app_authentication_recovery_codes' => array_values($codes)])->save();
                AuditLog::record('user.2fa_recovery_used', $user);

                return true;
            }
        }

        return false;
    }

    private function finishLogin(Request $request, User $user, bool $remember)
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(Url::to('/account'));
    }

    /** Turnstile on the public auth forms (D59), when it is switched on. */
    public static function checkHuman(Request $request, string $field, string $action): void
    {
        if (! \App\Support\Turnstile::passes($request->input('cf-turnstile-response'), $request->ip(), $action)) {
            throw ValidationException::withMessages([$field => 'আপনি মানুষ কি না যাচাই হয়নি। আবার চেষ্টা করুন।']);
        }
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
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()->uncompromised()],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'শর্তাবলি মেনে নিতে হবে।',
            'password.confirmed' => 'দুটি পাসওয়ার্ড মেলেনি।',
            'password.uncompromised' => 'এই পাসওয়ার্ডটি অন্য কোনো সাইটের ফাঁস হওয়া তালিকায় আছে। অন্য একটি পাসওয়ার্ড দিন।',
        ]);
        self::checkHuman($request, 'email', 'register');
        $email = strtolower($data['email']);
        $realMail = \App\Http\Controllers\AccountController::verificationRequired();
        $check = redirect(Url::to('/register/check-email'))->with('register_email', $email);

        // D59: never say whether an address already has an account. Its owner gets a note instead.
        if ($existing = User::where('email', $email)->first()) {
            if ($realMail) {
                $existing->notify(new \App\Notifications\AccountExistsNotice());

                return $check;
            }
            throw ValidationException::withMessages(['email' => 'এই ইমেলে আগেই অ্যাকাউন্ট আছে। লগ-ইন করুন।']);   // test servers only (no real mail)
        }

        $user = new User();
        $user->forceFill([
            'name' => strip_tags($data['name']),
            'email' => $email,
            'password' => $data['password'],
            'role' => Role::Reader,
            'mail_opt_out' => ! $request->boolean('newsletter', true),
        ])->save();
        AuditLog::record('user.registered', $user);
        event(new Registered($user)); // sends the verification email when real mail is configured

        if ($realMail) {
            return $check;   // signed in only by the link in the e-mail
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect(Url::to('/account'));
    }

    public function checkEmail(Request $request)
    {
        return view('auth.check-email', ['seo' => self::seo('ই-মেল দেখুন'), 'email' => (string) $request->session()->get('register_email', '')]);
    }

    public function showForgot()
    {
        return view('auth.forgot', ['seo' => self::seo('পাসওয়ার্ড ভুলে গেছেন')]);
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email', 'max:190']]);
        self::checkHuman($request, 'email', 'forgot');
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
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()->uncompromised()],
        ], ['password.confirmed' => 'দুটি পাসওয়ার্ড মেলেনি।', 'password.uncompromised' => 'এই পাসওয়ার্ডটি অন্য কোনো সাইটের ফাঁস হওয়া তালিকায় আছে। অন্য একটি পাসওয়ার্ড দিন।']);

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
