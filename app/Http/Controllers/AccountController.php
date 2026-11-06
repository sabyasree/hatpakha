<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\AuthController;
use App\Models\Post;
use App\Support\Url;
use Illuminate\Http\Request;

/** "আমার লেখা": the writer's own drafts, submissions and published pieces (Phase 3a/3b). */
class AccountController extends Controller
{
    public const STATUS = [
        'draft' => ['খসড়া', 'এখনও জমা দেওয়া হয়নি'],
        'pending' => ['জমা পড়েছে', 'সম্পাদকমণ্ডলী পড়ছেন'],
        'changes' => ['বদল চাওয়া হয়েছে', 'সম্পাদকের মন্তব্য দেখে ঠিক করে আবার জমা দিন'],
        'confirm' => ['মনোনীত, আপনার সম্মতি চাই', 'সম্পাদক কিছু শব্দ বদলাতে চান: দেখে সম্মতি দিন'],
        'scheduled' => ['মনোনীত', 'প্রকাশের তারিখ ঠিক হয়েছে'],
        'published' => ['প্রকাশিত', ''],
        'rejected' => ['এবার মনোনীত হয়নি', ''],
        'archived' => ['আর্কাইভ', ''],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $posts = Post::query()->with('section')
            ->whereHas('authors', fn ($a) => $a->where('user_id', $user->id))
            ->orderByRaw("case status when 'changes' then 0 when 'draft' then 1 when 'pending' then 2 when 'scheduled' then 3 else 4 end")
            ->latest('updated_at')->get();

        return view('account.index', [
            'seo' => AuthController::seo('আমার লেখা'),
            'user' => $user,
            'posts' => $posts,
            // The writer's own certificates (D12, D56), each with its public verification page.
            'certificates' => \App\Models\Certificate::whereNull('revoked_at')->whereIn('author_id', \App\Models\Author::where('user_id', $user->id)->select('id'))->latest('issued_at')->get(),
            'needsVerification' => self::verificationRequired() && ! $user->hasVerifiedEmail(),
        ]);
    }

    public function updateName(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:80']]);
        $request->user()->forceFill(['name' => strip_tags($data['name'])])->save();

        return redirect(Url::to('/account'))->with('status', 'নাম বদলানো হয়েছে।');
    }

    /** Email verification is enforced only where real email is sent (staging uses the log mailer). */
    public static function verificationRequired(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null, ''], true);
    }
}
