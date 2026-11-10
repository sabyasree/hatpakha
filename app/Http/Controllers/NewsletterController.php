<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Notifications\NewsletterConfirm;
use App\Seo\Seo;
use App\Support\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Newsletter sign-up with double opt-in (D58). The answer is the same whether or not the address is
 * already on the list (no way to probe who subscribes). A confirmation link is sent at most once
 * every 10 minutes per address.
 */
class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $from = in_array($request->input('from'), ['card', 'footer', 'home'], true) ? $request->input('from') : 'card';
        $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'email' => ['required', 'email:rfc', 'max:190'],
            'name' => ['nullable', 'string', 'max:80'],
        ], ['email.required' => 'ই-মেল ঠিকানা দিন।', 'email.email' => 'ই-মেল ঠিকানাটি ঠিক নেই।']);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput()->with('newsletter_from', $from)->withFragment('newsletter-'.$from);
        }
        $data = $v->validated();
        $done = back()->with(['newsletter' => 'প্রায় হয়ে গেছে! আপনার ই-মেলে একটি লিংক পাঠানো হয়েছে; সেটায় চাপলেই চিঠি আসা শুরু হবে।', 'newsletter_from' => $from])->withFragment('newsletter-'.$from);

        // Spam trap: real people never fill the hidden field.
        if (filled($request->input('website'))) {
            return $done;
        }
        if (! Turnstile::passes($request->input('cf-turnstile-response'), $request->ip(), 'newsletter')) {
            return back()->withErrors(['email' => 'যাচাই হয়নি, আবার চেষ্টা করুন।'])->with('newsletter_from', $from)->withFragment('newsletter-'.$from);
        }
        $email = mb_strtolower(trim($data['email']));
        $sub = NewsletterSubscriber::firstOrNew(['email' => $email]);
        if ($sub->exists && $sub->consented_at && ! $sub->suppressed && $sub->status === 'subscribed') {
            return $done;   // already on the list: nothing to do, same answer
        }
        if ($sub->confirm_sent_at && $sub->confirm_sent_at->gt(now()->subMinutes(10))) {
            return $done;
        }
        $sub->fill(['name' => $sub->name ?: strip_tags((string) ($data['name'] ?? '')) ?: null, 'source' => $sub->source ?: 'form',
            'user_id' => $sub->user_id ?: User::where('email', $email)->value('id')]);
        $sub->forceFill(['status' => $sub->status === 'subscribed' ? 'subscribed' : 'unconfirmed', 'confirm_sent_at' => now()])->save();
        Notification::route('mail', $email)->notify(new NewsletterConfirm(URL::temporarySignedRoute('newsletter.confirm', now()->addDays(7), ['subscriber' => $sub->id])));

        return $done;
    }

    public function confirm(NewsletterSubscriber $subscriber)
    {
        $subscriber->forceFill(['status' => 'subscribed', 'consented_at' => $subscriber->consented_at ?? now(), 'suppressed' => false])->save();
        AuditLog::record('newsletter.confirmed', $subscriber);

        return response()->view('newsletter-message', ['seo' => new Seo(title: 'চিঠি চালু হয়েছে', noindex: true),
            'title' => 'ধন্যবাদ! চিঠি চালু হয়েছে', 'text' => 'এখন থেকে সপ্তাহে একবার হাতপাখার নতুন লেখার খবর আপনার ই-মেলে যাবে। প্রতিটি চিঠির নিচে বন্ধ করার লিংক থাকবে।']);
    }

    public function unsubscribeWriter(User $user)
    {
        $user->forceFill(['mail_opt_out' => true])->save();
        AuditLog::record('newsletter.writer_unsubscribed', $user);

        return response()->view('newsletter-message', ['seo' => new Seo(title: 'চিঠি বন্ধ হয়েছে', noindex: true),
            'title' => 'চিঠি বন্ধ হয়েছে', 'text' => 'লেখকদের জন্য সাধারণ চিঠি আর পাঠানো হবে না। আপনার লেখার খবর (মনোনয়ন, বদলের অনুরোধ) আগের মতোই পাবেন।']);
    }
}
