# CLAUDE.md: rules for any AI agent (or developer) working on Hatpakha

Hatpakha (হাতপাখা) is a Bengali literary web magazine (ISSN 2321-3035, since 2012), being rebuilt from WordPress to **Laravel 13 + Filament 5 + MySQL** on **Hostinger Cloud hosting (PHP 8.4)**.

## 0. Before you do anything
1. Read **`docs/PROGRESS.md`**: the current phase, what is done, and what is next. Work only on what is listed as next, or what the owner asks for.
2. Read **`docs/DECISIONS.md`**: decisions already made. **Do not re-open a decision** unless the owner asks.
3. For any public page or URL: follow **`docs/SEO.md`**. For anything touching users, input, uploads, auth or secrets: follow **`docs/SECURITY.md`**. Both are binding.
4. For UI: follow **`docs/DESIGN.md`** (logo palette only) and the design canvas linked there.

## 1. Source of truth (in this order)
1. The owner's latest explicit instruction in the conversation.
2. `docs/DECISIONS.md`
3. `docs/SEO.md`, `docs/SECURITY.md`, `docs/DESIGN.md` (binding specs)
4. `PLAN.md` (strategy, roadmap), `docs/STRATEGY.md` (how we compete, 90-day plan) and `MONETIZATION.md`
5. `docs/ARCHITECTURE.md` (how the code is organised)

If two of these conflict, follow the higher one and **tell the owner about the conflict**.

## 2. Non-negotiable rules
- **Never break an old URL.** Old WordPress URLs (`/{slug}/`, `/category/…/`, `/author/…/`, `?p=ID`, `/amp/`) must keep working or 301. Changing a public URL needs a redirect and a test.
- **Trailing slash on every public URL.** Use `App\Support\Url::to()` or the model `url()` methods, never bare `url()` for public links.
- **All rich text goes through `App\Support\HtmlSanitizer`** (the Post model does this on save). Only `*_html` fields may be printed with `{!! !!}`.
- **The AI editor prepares, the owner approves (D42).** The AI does the desk work: reports, copy-edits, a recommended decision with a drafted note to the writer, metadata, cover illustrations, comment pre-screening, the monthly issue proposal and theme, and a draft editorial. **Nothing reaches readers or writers until a person clicks** (publish, send, approve). No auto-approve unless the owner explicitly enables it.
- **The AI never rewrites a writer's work.** Copy-edits only (spelling, punctuation, spacing, typos), applied by the server as short exact find → replace (`App\Ai\CopyEdit`); anything bigger counts as a word change, is unticked by default and needs the writer's OK. The AI may draft the monthly editorial, which is published under the owner's name only after they edit and approve it.
- **Secrets never enter git:** `.env`, keys, DB dumps, user data, the WordPress uploads folder. Real values live in GitHub Environments. `.env.example` has placeholders only.
- **No colours outside the logo palette** in the UI (`docs/DESIGN.md`). White text only on red or navy.
- **Real content only** on public pages: no placeholder brackets, no invented names, quotes, statistics or descriptions of real people's work.
- **Readers' comments** are featured only if not opted out (`comments.feature_opt_out`).
- **Tests must pass** (`php artisan test`) before every commit. Add or adjust tests for every SEO or security rule you touch.
- **Don't add dependencies casually.** A new Composer or npm package needs a reason in `docs/DECISIONS.md`.

## 3. Ask the owner before
Changing the stack, hosting, URL patterns, the design direction or palette, the issue calendar, monetisation (prices, paid features), anything that emails users in bulk, deleting or unpublishing content, enabling AI auto-approve, or adding a paid service.

## 4. After you finish a piece of work
1. Run `php artisan test` (and `npm run build` if you touched CSS or JS).
2. Update **`docs/PROGRESS.md`** (tick what's done; add what's next).
3. Add any new decision to **`docs/DECISIONS.md`** (date, decision, reason, who decided).
4. Commit with a clear message. Never commit to `main` directly; work on a branch.

## 5. Commands
```bash
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan hatpakha:import-wp            # import old WordPress DB (LEGACY_DB_* in .env)
php artisan hatpakha:role you@example.com admin   # give an existing user a role
php artisan hatpakha:password you@example.com     # set a password at a hidden prompt (--reset-2fa to redo 2FA)
php artisan hatpakha:images                       # WebP sizes for images used by posts (safe to re-run)
php artisan hatpakha:audit                        # content-audit + backlog-tier SUGGESTIONS (changes nothing)
php artisan hatpakha:ai-blind-test                # Claude vs OpenAI blind test (docs/AI.md; costs money, asks first)
php artisan hatpakha:ai-editor --backlog --images --dry   # AI editor batch: estimate first; prepares work, never publishes
php artisan hatpakha:url-audit --base=https://new.hatpakha.com --sitemap=https://hatpakha.com/sitemap.xml
php artisan hatpakha:move-host --dry                # launch: rewrite saved new.hatpakha.com → hatpakha.com (D90)
php artisan hatpakha:backup-db                     # database backup to storage/app/private/backups (runs before deploys + nightly)
php artisan hatpakha:staging-scrub                 # staging only, after loading a production backup (D92)
php artisan hatpakha:studio list                   # marketing cards/reels waiting to be drawn (the "Marketing studio" GitHub job draws them, D182)
php artisan test                          # must be green
php artisan serve
```
Admin panel: `/desk` (editor role or above, mandatory 2FA).

## 6. Code map (details: docs/ARCHITECTURE.md)
- `app/Models`: Post (sanitises on save), Author (incl. guest and desk bylines), Section, Tag, Mood, Issue, Comment, Certificate, Redirect, AuditLog, User (roles, legacy hash, 2FA)
- `app/Auth`: WordPress legacy password verification (re-hash on first login)
- `app/Seo`: `Seo` (head meta) and `JsonLd` (the only place structured data is built)
- `app/Http/Middleware`: `SecurityHeaders` (CSP with nonce), `EnforceTrailingSlash`
- `app/Console/Commands/ImportWordPress.php`: idempotent WordPress import
- `app/Ai`: provider-neutral AI review (`Reviewer`, `ReviewService` with cost guards); docs/AI.md
- `app/Filament`: admin panel resources
- `resources/views`: Blade pages; `components/` (layout, seo, fan motif, title card, ad slot)
- `tests/Feature/SeoUrlTest.php`, `tests/Feature/SecurityTest.php`: guardrail tests

## 6b. Gotchas
- Filament closures get arguments **by parameter name**: use `$query`, `$record`, `$state`, `$data` (a `$q` gets null).
- Laravel 13 refuses to unserialize models from the cache: cache IDs/scalars (D18).
- Pipe test output carefully: use `set -o pipefail` so a failing `php artisan test | tail` stops the commit.

## 7. Language
UI copy is **Bengali-first** (English section for English content). In /desk, write labels in Bengali inside `__()` and add the English to `lang/en.json` (D45; a test checks it). Code, comments and docs are in English. Write UI text simply; a native Bengali copy editor reviews it before launch.
