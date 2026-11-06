# Progress

Update this file at the end of every work session. ✅ done · 🟡 in progress · ⬜ not started · ⛔ blocked (say on what).

## Phase 0: Access & audit ✅
- ✅ Site audit, plan, monetisation research (`PLAN.md`, `MONETIZATION.md`)
- ✅ Design canvas, 18 screens + 12 covers: https://claude.ai/artifact/4iCfy5Pt1DJqTyC2DNUUcG
- ✅ DB dump analysed (`docs/DB-AUDIT.md`)
- ⬜ Uploads folder copied to the server (outside the live `public_html`): **owner**
- ⬜ Google Search Console owner access: **owner**
- ⬜ SVG/high-res logo: **owner**

## Phase 1: Foundation 🟡
- ✅ Laravel 13 + Filament 5 scaffold (PHP platform pinned to 8.3 so the lock file works on 8.3 and 8.4)
- ✅ Data model: users (roles, legacy hash, 2FA), authors (incl. guest/desk), sections, tags, moods, issues, posts, comments, certificates, redirects, audit log
- ✅ `hatpakha:import-wp`: idempotent; tested on the real dump (1,393 published / 424 pending / 53 draft / 1,728 users / 2,278 tags / 307 comments)
- ✅ Public pages: home (fan hero, on-this-day, poem of the day, story pages, reader letters, travel stamps), post (poem and story modes), author (full/compact/guest/desk), section, tag, issue, certificate verification
- ✅ SEO: meta + canonical + JSON-LD (Periodical with ISSN), sitemaps, RSS, robots, URL policy with 301/410
- ✅ Security: CSP nonce, headers, sanitiser, legacy password re-hash, rate limiters, upload script blocking, legacy WP paths 410
- ✅ Admin `/desk`: branded, editor+ only, mandatory 2FA (authenticator app or email code, D20), admin-only user management
- ✅ Console: `hatpakha:role`, `hatpakha:password` (hidden prompt, audited)
- ✅ Staging/non-live sites are noindex (`SEO_INDEXABLE`, robots + `X-Robots-Tag`)
- ✅ Tests: 68 passing (SEO URL policy, meta, JSON-LD, sitemaps, headers, XSS, legacy login, roles)
- ✅ CI (tests + composer/npm audit) and SSH deploy workflows
- ✅ Old WordPress pages imported at their URLs; 7 rewritten as an editor (docs/PAGES-REVIEW.md, owner to confirm); plugin pages 301; /desk → পাতা
- ✅ Fixed: sanitizer was deleting text inside <span>/<div> (346 posts, 250 published); now unwrapped and kept, with regression tests
- ✅ WordPress shortcodes converted ([gallery], [caption], [embed] YouTube, page-builder); same-site links made relative
- ✅ Post polish (structure only, D23): 1,762 posts tidied; 441 poems turned from one-paragraph-per-line into stanzas; 109 repeated titles removed (prose only); 32 YouTube players restored; new reading typography for prose, poems, images, video, quotes, tables. Text verified identical against WordPress for every post
- ✅ Fonts self-hosted (`public/fonts/`, OFL): Anek Bangla (variable weight+width), Noto Serif Bengali, Galada; Bengali faces preloaded; site and /desk share one stylesheet; CSP no longer allows any font host
- ✅ Staging: WordPress imported (same counts as local), uploads copied (6.7 GB), owner promoted to admin
- ⬜ Uploads clean-up: remove plugin leftovers (e.g. `uploads/mailpoet` PHP cache) from the copy; review WordPress admin/editor accounts imported with panel access
- ✅ Image pipeline: `php artisan hatpakha:images` makes WebP copies (480/960/1440) of every image posts/pages use, next to the originals (never changed); pages serve <picture> + srcset, lazy-loaded; falls back to originals until the command has run. ✅ Run on staging: 3,824 WebP copies for 1,902 images, ~217 MB less to download; 13 images referenced by old posts are missing from uploads (list in the Phase 2 audit)
- ✅ Mobile polish (checked at 360px, no horizontal scroll): two-column card grids on phones; card titles scale with the card (container queries); no duplicate/missing card titles; two-column footer with 44px tap targets; 404/410 inside the site layout with Bengali poem/story suggestions; all off-palette colours replaced + palette guard test
- 🟡 Category merge: table ready for the owner in docs/CATEGORY-MAP.md (32 old categories → 10 sections; all old URLs 301)

## Phase 2: Migration ✅ (except the pre-launch URL re-check with a Search Console export)
- ✅ Re-import safety: posts saved in /desk get `edited_in_desk_at`; `hatpakha:import-wp` never overwrites them (`--overwrite-edited` = owner only). Delete buttons are admin-only
- ✅ Content audit (`php artisan hatpakha:audit`): SUGGESTIONS keep/improve/merge/noindex/remove with Bengali reasons for 1,393 published posts; admins apply or dismiss in /desk → লেখা (filters: অডিট সুপারিশ, অডিট সিদ্ধান্ত). Applying: noindex flag, or archive + 410, or archive + 301 to the original; every action logged and reversible. Local run: keep 1,311 · merge 9 · noindex 66 · remove 7
- ✅ Pending backlog tiers on the 424 pending posts: A recent 23 · B clean-up 41 · C old 360 (+ "review together" note on the 125-post account)
- ✅ Fixed: sanitizer blanked 5 long posts (regex limit); now impossible (fallback + regression test). **Staging needs a re-import.**
- ✅ `[pdf-embedder]` → link to the PDF; `[pageview]` iframes dropped
- ✅ URL inventory + redirect audit (`php artisan hatpakha:url-audit`): 8,040 old URLs (DB + live Yoast sitemaps) checked against staging; 8,023 OK, the 17 broken (nested `/category/a/b/`, two house-account author URLs, `/sitemap.html`) fixed. ⬜ Re-run before launch with a Search Console export (`--file=`)
- ✅ Owner approved the audit, page policies and category map (D30); submissions are form-only (D29): pages rewritten, `/submit/` holding page until Phase 3
- ✅ `hatpakha:audit --apply=… --by=<admin>` applies approved suggestions (logged, reversible); `hatpakha:import-wp --refresh-pages` updates pages from revised copy unless edited in /desk
- ✅ Archive issue শারদীয়া ১৪২৭ (`/sonkha/2020-10/`) from the 8 `puja-2020` posts; old category URL 301s to it
- ✅ Staging: pages refreshed, only the owner has /desk access, 82 approved audit decisions applied (7 × 410, 9 × 301, 66 noindex), old house-account author URLs 301 to the desk; re-import can no longer undo applied decisions
- ✅ Least privilege: the import never grants admin/editor; a re-import takes back panel roles that only came from WordPress (explicit grants via `hatpakha:role` or /desk are logged and kept). `php artisan hatpakha:role --list` shows who can open /desk. Found: 4 old WordPress admins incl. a vendor test account ("test test", @optimusedge.com)
- ✅ Spam flags (never deletes): 8 accounts (4 bot sign-ups on throwaway domains, 4 Facebook test accounts); real readers who never posted are not flagged
- ✅ Newsletter: 1,790 MailPoet addresses imported with source + status; 170 unsubscribed/bounced permanently suppressed; only 2 have confirmed consent (+26 unconfirmed form sign-ups). Nobody emailed. ⬜ Owner: approve a one-time re-consent email (Phase 5)

## Reader archive ✅
- `/archive/` (years with counts, issues, sections) and `/archive/{year}/` (grouped by month, year switcher); linked in header, footer and mobile bar; in the sitemap. Old WordPress date URLs (`/2016/03/`) 301 to the year (`/archive/2016/#m3`)

## Launch blockers (must be done before cutover)
- Phase 3 submission form at `/submit/` (D29: the only way to submit)
- Final URL audit with a Search Console export
- Cloudflare cache rules for production

## Next up (in order)
1. ✅ Owner: created the GitHub Environments (`staging`, `production`) and secrets (docs/DEPLOYMENT.md), create `new.hatpakha.com` and its database on Hostinger.
2. ✅ Staging live at new.hatpakha.com (app outside the web root via symlink; Cloudflare bypass rule for staging; DB imported; uploads copied).
3. 🟡 Owner: first admin login + 2FA on staging; uploads clean-up; list of imported admin/editor accounts.
4. Offer email 2FA only where real mail is configured (staging uses `log`, so authenticator only there).
5. Finish the Phase 1 leftovers above.
6. Before launch: Cloudflare cache rules for production (cache guest HTML; bypass `/desk*`, `/livewire*`, `/submit*`, `/account*` and requests with the session cookie).
7. ⚠️ Phase 2 must make the final re-import skip posts edited in /desk (today `hatpakha:import-wp` overwrites post bodies).
8. Content audit note: the newest published posts include English filler by the desk account (e.g. "10 Essential Python Concepts", Netflix, "Top 10…"): candidates for noindex/removal; owner decides.
9. Phase 2: full redirect inventory (crawl the old site + Search Console export) and redirect tests; content audit labels.

## Later phases (from PLAN.md §8)
- Phase 2 Migration · Phase 3 Submissions + AI review (+ blind provider test) · Phase 4 Launch · Phase 5 Issues, e-book/flipbook, contests, certificates, newsletter · Phase 6 Growth
