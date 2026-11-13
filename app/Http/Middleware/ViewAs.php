<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * "লেখকের চোখে দেখুন" (owner, D85): an admin sees the site and the account exactly as a writer or reader does.
 * Look only: for this request the page is drawn as that person, nothing is ever saved as them.
 *  - only an admin can start it, only for readers/writers (never another editor or admin);
 *  - only page views (GET/HEAD) are drawn as them; anything that would change something is refused;
 *  - /desk stays the admin's own; it ends after 30 minutes or with "বন্ধ করুন"; every start is in the audit log.
 */
class ViewAs
{
    public const MINUTES = 30;

    /** Start looking as $target (admins only, never as staff). Logged. Returns where to go. */
    public static function start(User $target): ?string
    {
        $admin = Auth::user();
        if (! $admin?->hasRole(Role::Admin) || $target->hasRole(Role::Editor)) {
            return null;
        }
        session()->put('view_as', ['user' => $target->id, 'by' => $admin->id, 'at' => now()->timestamp]);
        \App\Models\AuditLog::record('user.view_as', $target);

        return \App\Support\Url::to('/account');
    }

    /** Busiest writers first: the best demo of what a writer with many pieces sees. */
    /** A person picked from the search (not in the first 30) still needs a label, or the form calls the choice invalid. */
    public static function choiceLabel(mixed $id): ?string
    {
        return self::choices('', (int) $id)[(int) $id] ?? null;
    }

    public static function choices(string $search = '', ?int $id = null): array
    {
        return User::query()->whereIn('role', [Role::Reader->value, Role::Writer->value, Role::TrustedWriter->value])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->when($id !== null, fn ($q) => $q->where('users.id', $id))
            ->leftJoin('authors', 'authors.user_id', '=', 'users.id')
            ->leftJoin('author_post', 'author_post.author_id', '=', 'authors.id')
            ->groupBy('users.id', 'users.name', 'users.role')
            ->orderByRaw('count(author_post.post_id) desc')->orderBy('users.id')->limit(30)
            ->selectRaw('users.id, users.name, users.role, count(author_post.post_id) n')->get()
            ->mapWithKeys(fn ($u) => [$u->id => $u->name.' · '.$u->role->label().' · '.\App\Support\Bn::num((string) $u->n).' লেখা'])->all();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $as = $request->hasSession() ? $request->session()->get('view_as') : null;
        if (! $as) {
            return $next($request);
        }
        // The real person is the one the session is logged in as, never whoever this process last drew a page for.
        $admin = User::find($request->session()->get(Auth::guard('web')->getName()) ?? Auth::id());
        $target = User::find($as['user'] ?? 0);
        $expired = now()->timestamp - (int) ($as['at'] ?? 0) > self::MINUTES * 60;
        if (! $admin || ! $admin->hasRole(Role::Admin) || (int) ($as['by'] ?? 0) !== $admin->id || ! $target || $target->hasRole(Role::Editor) || $expired) {
            $request->session()->forget('view_as');
            if ($expired && $admin) {
                $request->session()->now('status', 'লেখকের চোখে দেখা শেষ হয়েছে।');
            }

            return $next($request);
        }
        if ($request->is('desk', 'desk/*', 'livewire*', 'filament/*', 'view-as/stop*', 'logout*')) {
            return $next($request);   // the admin's own desk, and the way out
        }
        if (! $request->isMethodSafe()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'দেখার সময়ে কিছু বদলানো যায় না।', 'answer' => 'আপনি এখন শুধু দেখছেন, তাই সহায়ককে প্রশ্ন পাঠানো যায় না।'], 403)
                : back()->with('status', 'আপনি এখন শুধু দেখছেন, তাই কিছু বদলানো বা পাঠানো যায় না।');
        }
        Auth::setUser($target);   // this request only; the session stays the admin's
        $request->attributes->set('view_as', ['name' => $target->name, 'admin' => $admin->name]);
        try {
            $response = $next($request);
        } finally {
            Auth::setUser($admin);
        }
        $response->headers->set('Cache-Control', 'private, no-store');   // never cached as anyone

        return $response;
    }
}
