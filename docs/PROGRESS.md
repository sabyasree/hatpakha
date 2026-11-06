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
- ✅ Tests: 95 passing (SEO URL policy, meta, JSON-LD, sitemaps, headers, XSS, legacy login, roles)
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

## Phase 3: Submissions + AI review 🟡
- ✅ 3a Writer accounts: `/register/`, `/login/` (old WordPress passwords work and are upgraded), `/forgot-password/` + reset (no account enumeration), `/account/` "আমার লেখা" (status of every piece, editor's note, name change, logout). Honeypot + minimum-time check on sign-up; throttles; email verification required only where real mail is configured; old accounts count as verified; Bengali emails (the /desk panel keeps its own)
- ✅ 3b Submission form `/submit/` (sign-in required): section, title, plain-text body (paste is clean; blank line = stanza/paragraph), word/line count, autosave every few seconds (first save turns the URL into the draft's own page), server-rendered preview identical to the site, originality declaration, 3 submissions/day, email verification where mail is real, edit & resubmit when changes are requested, status tracker on আমার লেখা. Typed HTML is shown as text, never executed. ⬜ Cover image upload (later in Phase 3, with re-encoding)
- ✅ 3c Editor review queue in /desk → লেখা: tabs অপেক্ষায় (new form submissions, oldest first) · পুরনো জমা (WordPress backlog by tier) · বদল চাওয়া · প্রকাশিত · সব. Actions মনোনীত (publish now or schedule; readable Latin URL from the title, D14), বদল চাই, বাতিল, each with kind editable templates; the writer gets one Bengali email and sees the note on আমার লেখা. Scheduled posts go live on their date without cron. Every decision is in the audit log 

- ✅ 3d AI review (docs/AI.md): report after each submission (no cron), AI score + report in /desk, re-run button, editable rubric + monthly spend in /desk → AI সহকারী; cost guards (off by default, key required, min words, pause button (D37), usage log); prompt-injection-safe input; refusal fallback. Never decides anything
- ✅ 3e Blind test: `php artisan hatpakha:ai-blind-test` (estimate first, stops above $6) + /desk → AI পরীক্ষা (anonymous A/B scoring, reveal at the end). 🟡 Owner: add keys to staging ENV_FILE, run it, score, set `AI_PROVIDER`
- ✅ No AI spending cap (D37); admin pause/resume button on এআই সহকারী and on আজকের কাজ, logged
- ✅ Blind test run on staging (30 pieces, est. $1.36). Scoring page redesigned: one piece at a time, reports A/B side by side, one question (A better / same / B better), progress bar, auto-next, reveal at the end. AI report view rebuilt with inline styles (utility classes don't exist in /desk)
- ✅ "চাবি যাচাই" button on এআই সহকারী: checks both keys and model names via the free model-lookup endpoints (no tokens)
- ✅ Owner added AI keys to the staging ENV_FILE
- ⬜ Cover image upload on the submission form (re-encode, strip metadata)

## Deploys
- ✅ Visitors see a Bengali "একটু পরেই ফিরছি" page (503, auto-retry every 30 s, noindex) for the whole deploy instead of 500s: maintenance mode now starts before any file is uploaded, and the page is pre-rendered so it works even while vendor/ is half uploaded. If a deploy fails, that page stays up until the next good deploy

## Desk UX (owner: "too many places to check") ✅
- /desk opens on **আজকের কাজ**: one inbox with every item waiting for a person (new submissions oldest first with "পড়ুন ও সিদ্ধান্ত নিন"; reader comments with প্রকাশ / স্প্যাম right there); an info box needing no action; admin box folded
- Side menu for editors: আজকের কাজ · লেখা · মন্তব্য, plus a folded "আরও" (সংখ্যা, লেখক, পাতা). Admins also get a folded "প্রশাসন" (বিভাগ, রিডাইরেক্ট, ব্যবহারকারী, কাজের খাতা, AI)
- Post page: মনোনীত / বদল চাই / বাতিল / AI রিপোর্ট buttons on top, "জমার তথ্য" box (writer, words, earlier pieces, originality, AI score, last note); after a decision it goes back to the list
- Whole desk in Bengali (Filament's own `bn` strings via `DeskLocale`), stock green/red replaced by palette colours
- Every screen rebuilt in Bengali: comments (moderation tabs, no fake "create"), authors (type, count, fixed URL), issues (12 cover themes, post count), sections and redirects (admin-only, URL can't change, paths validated, on-site targets only), users (role, 2FA, old WordPress role, spam flag, last login; new accounts get a password link; admins can't demote or delete themselves)
- Renaming a published post's URL now leaves a 301 automatically (logged)
- Read-only audit log ("কাজের খাতা", admin)
- No English left in /desk: the 194 strings missing from Filament's Bengali pack are translated in `lang/vendor/*/bn/` (a test fails if a Filament update adds untranslated ones); Bengali month/day names in the date picker and Bengali digits on screen (`public/js/desk-bn.js`, display only); own labels de-Englished (ঠিকানা, গুগল, এআই, ইংরেজি…). Left as-is: names (Claude, OpenAI), example paths, typed values in inputs, and content titles
- Delete is admin-only everywhere
- Checked in a real browser as editor and admin, including 2FA login

## Launch blockers (must be done before cutover)
- ~~Submission form (3b) and review queue (3c)~~ ✅
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
