<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\Turnstile;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The contact and advertising forms (D89), instead of an e-mail address. Guarded like comments: CSRF, a hidden
 * honeypot, a "too fast" check, Cloudflare Turnstile, per-IP limits (route: 5 a minute; here: 10 a day), length
 * limits, at most two links, plain text only. Messages wait in /desk → বার্তা; the owner gets a short notice
 * that never carries the visitor's text. Nothing is mailed back to the address typed in (no mail bombing).
 */
class ContactController extends Controller
{
    public function store(Request $request, string $kind)
    {
        $back = Url::to($kind === 'advertise' ? '/advertise' : '/contact-us').'#form';
        $fail = fn (string $field, string $msg) => throw ValidationException::withMessages([$field => $msg])->redirectTo($back);

        if (filled($request->input('website')) || ! CommentController::humanSpeedPublic((string) $request->input('started'))) {
            $fail('body', 'কিছু একটা ঠিক হয়নি। পাতাটি আবার খুলে চেষ্টা করুন।');
        }
        $ipKey = 'contact-day:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            $fail('body', 'আজ অনেক বার্তা এসেছে এই সংযোগ থেকে। কাল আবার চেষ্টা করুন।');
        }

        // A link typed without https:// (or with stray spaces) is still a link.
        if (filled($link = preg_replace('/\s+/u', '', (string) $request->input('link')))) {
            $request->merge(['link' => preg_match('~^https?://~i', $link) ? $link : 'https://'.$link]);
        }
        $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'string', 'email:filter', 'max:190'],
            'topic' => ['required', Rule::in(array_keys(ContactMessage::TOPICS[$kind]))],
            'body' => ['required', 'string', 'min:10', 'max:3000'],
            'link' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'organisation' => [$kind === 'advertise' ? 'required' : 'prohibited', 'nullable', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9০-৯+\-\s()]{6,30}$/u'],
            'when' => ['nullable', 'string', 'max:120'],
        ], [
            'name.required' => 'আপনার নাম লিখুন।',
            'email.required' => 'ই-মেল লিখুন, যাতে উত্তর দিতে পারি।',
            'email.email' => 'ই-মেলটি ঠিক দেখাচ্ছে না।',
            'topic.required' => 'কী বিষয়ে লিখছেন, বেছে নিন।',
            'topic.in' => 'কী বিষয়ে লিখছেন, বেছে নিন।',
            'body.required' => 'কী জানাতে চান, লিখুন।',
            'body.min' => 'আর একটু লিখুন (অন্তত ১০ অক্ষর)।',
            'body.max' => 'একটু ছোট করুন (৩০০০ অক্ষরের মধ্যে)।',
            'link.url' => 'লিংকটি পুরো দিন, যেমন https://hatpakha.com/…',
            'organisation.required' => 'প্রতিষ্ঠান বা উদ্যোগের নাম লিখুন।',
            'phone.regex' => 'ফোন নম্বরটি ঠিক দেখাচ্ছে না।',
        ]);
        if ($v->fails()) {
            throw (new ValidationException($v))->redirectTo($back);   // back to the form itself, not the top of the page
        }
        $data = $v->validated();

        $plain = fn (?string $s) => $s === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags(str_replace("\r\n", "\n", $s))));
        if (Comment::hasLink($data['name']) || Comment::hasLink((string) ($data['organisation'] ?? ''))) {
            $fail('name', 'নামে লিংক বা ঠিকানা দেওয়া যায় না।');
        }
        if (preg_match_all('~(https?://|www\.)~i', $data['body']) > 2) {
            $fail('body', 'বার্তায় দুটির বেশি লিংক দেওয়া যায় না।');
        }
        if (! Turnstile::passes($request->input('cf-turnstile-response'), $request->ip(), $kind)) {
            $fail('body', 'আপনি মানুষ কি না যাচাই হয়নি। আবার চেষ্টা করুন।');
        }
        RateLimiter::hit($ipKey, 86400);

        $msg = ContactMessage::create([
            'kind' => $kind,
            'topic' => $data['topic'],
            'name' => mb_substr(preg_replace('/\s+/u', ' ', $plain($data['name'])), 0, 80),
            'email' => strtolower(trim($data['email'])),
            'organisation' => $plain($data['organisation'] ?? null) ?: null,
            'phone' => $plain($data['phone'] ?? null) ?: null,
            'link' => $data['link'] ?? null,
            'interests' => filled($data['when'] ?? null) ? ['when' => $plain($data['when'])] : null,
            'body' => $plain($data['body']),
            'user_id' => $request->user()?->id,
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ]);

        $msg->notifyAdmins();

        return redirect($back)->with('contact_sent', $kind === 'advertise'
            ? 'ধন্যবাদ! আপনার প্রস্তাব পৌঁছেছে। আমরা ই-মেলে যোগাযোগ করব।'
            : 'ধন্যবাদ! আপনার বার্তা পৌঁছেছে। আমরা যত তাড়াতাড়ি সম্ভব ই-মেলে উত্তর দেব।');
    }
}
