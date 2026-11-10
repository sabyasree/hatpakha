# Architecture

How the code is organised. Rules live in `CLAUDE.md`; specs in `docs/SEO.md`, `docs/SECURITY.md`, `docs/DESIGN.md`.

## Stack
Laravel 13 · Filament 5 (admin at `/desk`) · Blade + Tailwind v4 (Vite) · MySQL 8 / MariaDB in production · SQLite in-memory for tests · Hostinger Cloud (LiteSpeed, PHP 8.4) behind Cloudflare.

## Data model
| Table | Purpose | Notes |
|---|---|---|
| `users` | Login accounts | `role` (reader → admin, `App\Enums\Role`), `legacy_password` (WordPress hash, cleared on first login), `legacy_wp_id`, encrypted TOTP secret + recovery codes |
| `authors` | Bylines | Linked to a user, or `is_guest` (Co-Authors guest), or `is_desk` (house byline "হাতপাখা ডেস্ক"). `published_count` drives full vs compact profile and noindex (< 3 posts) |
| `sections` | The 10 sections | Old 23 WordPress categories merge into these (`ImportWordPress::SECTION_MAP`), old category URLs 301 |
| `tags` | Tags | Only `is_controlled` tags are indexable |
| `moods` | Reader moods (6) | Many-to-many with posts |
| `issues` | 12 issues a year | `slug` = `yyyy-mm`, `kind` festival/monthly, `theme_key` → cover design |
| `posts` | Writings | `body_html` sanitised on save; `format` poem/story/article; `status` draft/pending/scheduled/published/archived/rejected; `legacy_wp_id`; soft deletes |
| `author_post` | Co-authors | `position` keeps byline order |
| `comments` | Reader comments | `feature_opt_out` must be respected |
| `certificates` | Author certificates | Public code (10 chars, no 0/O/1/I), snapshots of title/name so later edits don't change an issued certificate |
| `pages` | Static pages at `/{slug}/` | Same URL space as posts (post wins). Initial copy from `database/content/pages/*.html`; never overwritten by a re-import |
| `redirects` | 301 map | Checked by the fallback route; `hits` counted |
| `audit_logs` | Who did what | `AuditLog::record()` |

## Request flow (public)
1. `EnforceTrailingSlash` 301s `/x` → `/x/` (files, `/desk`, Livewire and WordPress system paths exempt).
2. Routes (`routes/web.php`): fixed routes first (home, feeds, sitemaps, robots, `/category/`, `/author/`, `/tag/`, `/sonkha/`, `/certificate/`), then 410 for WordPress attack paths, then `/{slug}/amp/` → 301, then `/{slug}/` posts, then the fallback (`LegacyController`) which looks up `redirects`.
3. Controllers build an `App\Seo\Seo` object (title, description, canonical, robots, OG) and JSON-LD via `App\Seo\JsonLd`; views render through `components/layouts/app`.
4. `SecurityHeaders` adds the CSP (per-request nonce, shared with Vite) and other headers.

## Slugs
WordPress stored Bengali slugs percent-encoded. We store them **decoded and NFC-normalised** (`App\Support\Slug::normalize`) and encode on output (`Slug::encode`), so `/%E0%A6%95.../` and `/ক.../` resolve to the same post.

## Legacy passwords
`config/auth.php` uses the `legacy-eloquent` provider (`App\Auth\LegacyAwareUserProvider`). If `password` is empty and `legacy_password` holds a WordPress hash (`$P$`/`$H$` phpass or `$wp$` WP 6.8), `App\Auth\LegacyWordPressPassword::check()` verifies it once and the user is re-hashed with bcrypt.

## Import (`php artisan hatpakha:import-wp`)
- Reads the `legacy` DB connection (`LEGACY_DB_*`, table prefix `wp_`).
- Idempotent: every row is matched on its legacy ID, so it can be re-run after a fresh dump.
- Order: sections → users → authors (users + Co-Authors guests) → tags → posts (all statuses, Yoast meta, featured image, authors, tags) → approved comments → redirects → counts.
- Language: Bengali letters → `bn`; Devanagari → `hi` (shayari stays in its section); else `en` → English section.
- Real-dump result: 1,728 users, 910 authors, 2,278 tags, 1,871 posts, 307 comments, 23 redirects, ≈17 s.

## Admin (`app/Filament`)
Panel `desk`, editors and above only, TOTP mandatory, Bengali labels. Resources: Posts, Authors, Sections, Issues, Comments, Redirects, Users (admins only).

## Caching
Laravel 13 will not unserialize Eloquent models from the cache, so cache **IDs and scalars** (see `HomeController`) and re-query.

## Tests
- `tests/Feature/SeoUrlTest.php`: URL policy, redirects, 410s, meta, JSON-LD, sitemaps, feeds.
- `tests/Feature/SecurityTest.php`: headers/CSP, XSS sanitising, legacy login re-hash, panel access by role.
- `tests/Unit/ImportHelpersTest.php`: slug, language detection, `autop`.
`tests/TestCase.php` keeps trailing slashes in test requests (Laravel trims them by default).

## Planned modules (not built yet)
Submissions (`/submit/`, Phase 3) · AI review layer, provider-neutral (Phase 3) · Issue planner, e-book (PDF/EPUB) + flipbook built in GitHub Actions (Phase 5) · Contests, certificate PDFs, newsletter (Phase 5).
