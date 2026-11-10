<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Post;
use App\Seo\Seo;
use App\Support\RelaunchLetters;
use Illuminate\Http\Request;

/**
 * D104: the writer's answer to the apology letter, from the signed link in it (no sign-in needed).
 * Per piece: "publish" (it goes to the editor's review queue, nothing is published) or "don't publish".
 */
class RelaunchReplyController extends Controller
{
    private function pieces(Author $author)
    {
        return $author->posts()->whereIn('posts.id', RelaunchLetters::askable()->select('id'))->orderBy('submitted_at')->get();
    }

    public function show(Author $author)
    {
        $pieces = $this->pieces($author);
        abort_if($pieces->isEmpty(), 404);

        return view('relaunch-reply', ['author' => $author, 'pieces' => $pieces, 'done' => session('relaunch_done'),
            'seo' => new Seo(title: 'আপনার পুরনো লেখা', noindex: true)]);
    }

    public function store(Request $request, Author $author)
    {
        $pieces = $this->pieces($author);
        abort_if($pieces->isEmpty(), 404);
        $data = $request->validate([
            'answer' => ['required', 'array'],
            'answer.*' => ['required', 'in:yes,no'],
            'elsewhere' => ['nullable', 'array'],
        ]);
        $yes = 0;
        foreach ($pieces as $p) {
            $a = $data['answer'][$p->id] ?? null;
            if (! $a) {
                continue;
            }
            $p->forceFill(['relaunch_status' => $a, 'relaunch_answered_at' => now(), 'relaunch_elsewhere' => $a === 'yes' && ! empty($data['elsewhere'][$p->id])])->saveQuietly();
            $yes += $a === 'yes' ? 1 : 0;
        }
        AuditLog::record('relaunch.answered', null, ['author' => $author->id, 'yes' => $yes]);

        return redirect()->to($request->fullUrl())->with('relaunch_done', $yes ? 'yes' : 'no');
    }
}
