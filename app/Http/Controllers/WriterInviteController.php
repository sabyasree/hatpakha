<?php

namespace App\Http\Controllers;

use App\Models\WriterInvite;
use App\Seo\Seo;
use App\Support\WriterInvites;
use Illuminate\Http\Request;

/**
 * D188: a writer's answer to the invitation, from the signed links in the letter (no sign-in needed). Opening a link
 * only shows the page; the answer is kept when the writer presses the button (mail programs open links by themselves).
 */
class WriterInviteController extends Controller
{
    public function show(Request $request, WriterInvite $invite, string $answer)
    {
        return view('invite-answer', ['invite' => $invite, 'answer' => $answer, 'done' => session('invite_done'), 'action' => $request->fullUrl(),
            'last' => \App\Support\Bn::date(\Illuminate\Support\Carbon::parse(WriterInvites::LAST_DATE, 'Asia/Kolkata')), 'seo' => new Seo(title: 'ফিরে আসা সংখ্যা', noindex: true)]);
    }

    public function store(WriterInvite $invite, string $answer)
    {
        WriterInvites::answer($invite, $answer);

        return back()->with('invite_done', $answer);
    }
}
