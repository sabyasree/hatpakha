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
- ✅ 3b Submission form `/submit/` (sign-in required): section, title, plain-text body (paste is clean; blank line = stanza/paragraph), word/line count, autosave every few seconds (first save turns the URL into the draft's own page), server-rendered preview identical to the site, originality declaration, 3 submissions/day, email verification where mail is real, edit & resubmit when changes are requested, status tracker on আমার লেখা. Typed HTML is shown as text, never executed. ✅ Cover image (3f, below)
- ✅ 3c Editor review queue in /desk → লেখা: tabs অপেক্ষায় (new form submissions, oldest first) · পুরনো জমা (WordPress backlog by tier) · বদল চাওয়া · প্রকাশিত · সব. Actions মনোনীত (publish now or schedule; readable Latin URL from the title, D14), বদল চাই, বাতিল, each with kind editable templates; the writer gets one Bengali email and sees the note on আমার লেখা. Scheduled posts go live on their date without cron. Every decision is in the audit log 

- ✅ 3d AI review (docs/AI.md): report after each submission (no cron), AI score + report in /desk, re-run button, editable rubric + monthly spend in /desk → AI সহকারী; cost guards (off by default, key required, min words, pause button (D37), usage log); prompt-injection-safe input; refusal fallback. Never decides anything
- ✅ 3e Blind test: `php artisan hatpakha:ai-blind-test` (estimate first, stops above $6) + /desk → AI পরীক্ষা (anonymous A/B scoring, reveal at the end). ✅ Run on staging (30 pieces); owner judged blind: **Claude won** (D38). `AI_PROVIDER=anthropic`
- ✅ No AI spending cap (D37); admin pause/resume button on এআই সহকারী and on আজকের কাজ, logged
- ✅ Blind test run on staging (30 pieces, est. $1.36). Scoring page redesigned: one piece at a time, reports A/B side by side, one question (A better / same / B better), progress bar, auto-next, reveal at the end. AI report view rebuilt with inline styles (utility classes don't exist in /desk)
- ✅ "চাবি যাচাই" button on এআই সহকারী: checks both keys and model names via the free model-lookup endpoints (no tokens)
- ✅ Owner added AI keys to the staging ENV_FILE

- ✅ Reader comments (D39): form under every post for guests (name only) and signed-in readers; guests always moderated, accounts trusted after 2 approved; "log in to comment" returns to the post; opt-out from পাঠকের চিঠি; honeypot, timing, rate limits, optional Turnstile (`TURNSTILE_SITE_KEY/SECRET`); আজকের কাজ shows অতিথি/অ্যাকাউন্ট. ⚠ If guest HTML is ever cached at Cloudflare, the form's CSRF token must be fetched fresh (note for the production cache rules)
- ✅ 3f Cover image on the submission form (optional): Bengali file button with instant preview; server sniffs the real type (JPG/PNG/WebP, ≤ 8 MB, ≥ 800 px wide), re-encodes with GD (EXIF/GPS and hidden payloads gone, phone rotation kept), random name under storage/app/public/covers (served at /storage/covers/, scripts blocked by .htaccess), WebP sizes; writer can replace/remove; editors see it in জমার তথ্য and can remove it (logged). Deploy runs `storage:link`

- ✅ Formatting editor on /submit/ (D40): toolbar in Bengali; poems keep line/stanza rules; paste from Word/Docs keeps bold/italic; server allows only the safe set. Preview (কেমন দেখাবে) now shows the cover and byline; submitted pieces show their cover

- ✅ Toolbar extended (D41): alignment, clear formatting, strikethrough, super/subscript; one scrollable row on phones; /desk editor gets the same options and keeps writers' alignment

## AI editor (D42/D43) 🟡
- ✅ Per piece: report + copy-edits (server-checked find → replace) + recommended decision + drafted note + teaser + tags + Google fields; approve dialog ticks safe fixes, fills metadata, suggests next slot (Mon/Wed/Fri 09:00 IST), starts from the AI's note; word changes → writer confirms on আমার লেখা
- ✅ Cover illustrations (Claude scene → OpenAI image, house style, re-encoded); new pieces: toggle in approve dialog; old pieces: ছবি অনুমোদন grid (tick, approve in batches, redraw, drop); credit line on the page
- ✅ Monthly issue proposal (from the 20th, automatic on the admin's dashboard; or on demand): lineup, theme from the calendar, title, cover lines; owner approves on the issue page (untick to drop pieces, release date); editorial drafted under the owner's name, published after the owner edits
- ✅ Comment pre-screen (ঠিক আছে / স্প্যাম / দেখে নিন) + "approve all AI-ok"
- ✅ আজকের কাজ redesigned: greeting, four numbers, "আপনার অনুমোদনের অপেক্ষায়" (issue, editorial, pictures, submissions with AI verdict, fixes, thumbnail), comments with AI verdicts, AI editor panel (status, spend, pause), next issue, two-week publishing calendar; desk shows India time
- ✅ Continuous shift (D44): scheduler routine (10 min / hourly / daily / 08:00 brief), heartbeat + last-24-hours activity on আজকের কাজ, warning when the clock isn't running
- 🟡 Owner: add the cron line in hPanel (docs/DEPLOYMENT.md) for staging (and later production)
- 🟡 Owner: set `OPENAI_IMAGE_MODEL` (+ `OPENAI_IMAGE_PRICE`) in ENV_FILE; then the batch makes pictures
- 🟡 Backlog batch on staging (`hatpakha:ai-editor --backlog --images`, started by a `[run-ai-editor]` deploy, runs in the background, log: storage/logs/ai-editor.log)

## Desk language ✅ (D45)
- বাংলা / English switch in the user menu (top right), per user; every desk label, page, button, filter, date and number follows it; `lang/en.json` + coverage test

## AI SEO expert ✅ (D46)
- After approval: Google title/description, focus phrase, tags (existing ones reused), picture alt text, WhatsApp/Facebook text, "read next" picks (used on the page); hourly fill-missing for the archive; undo + "ask me first" switch
- ✅ Fixed (D47): AI/writer pictures 404 on Hostinger → stored in public/media/covers

## More editor jobs ✅ (D48)
- Follow-ups, welcome notes, weekly picks (home page), Sunday newsletter draft (consented readers only, unsubscribe), monthly report, weekly site health; all on আজকের কাজ → "সম্পাদকের অন্য কাজ"
- ✅ Contests and the AI team (below)

## AI team ✅ (D49–D70) — /desk → প্রশাসন → এআই দল (pause any member)
- **Editor**: package per piece, issue + editorial, comments, picks, follow-ups, publication certificates (owner ticks, writers see them on their account page)
- **Proofreader** (D50): after each submission and hourly on old *unpublished* pieces (never published ones); house style; title typo fix in the approve dialog
- **Plagiarism checker** (D50): web search on a few lines of each new piece; report + task when found
- **SEO expert**: after approval; monthly refresh proposals for old pieces still found through search (D54)
- **Graphic designer** (D51): AI pictures, issue cover art + our Bengali masthead, share/quote cards → PNG
- **Marketing** (D51): ready-to-post Facebook/WhatsApp/Instagram text per piece, issue, contest; weekly plan; monthly writer spotlight; newsletter (/desk → মার্কেটিং; owner posts by hand)
- **Relationship manager** (D52): chat on /submissions/ and /submit/ only; guests get public answers, writers their own pieces' status; no tools
- **Contest manager** (D53): rules draft, shortlist, results draft + certificates on the owner's click; contest choice in the submit form
- **Archivist** (D55): monthly collection on /archive/ (after approval), yearly anthology list, tag tidy-up with 301s
- **Analyst** (D54): own privacy-friendly read counter; monthly reader insights
- **Editor-in-chief** (D49, D56): hourly checks + deadline watch (empty publishing days, next issue not approved by the 25th, ended contests), weekly quality review, monthly team review and cost forecast
- **Newsletters** (D58): public sign-up with double opt-in; /desk → চিঠি to readers, writers or both (AI drafts weekly + monthly, owner tests and sends, batched); needs real SMTP in the server .env to actually send
- **Accounts** (D59): verify-before-anything, no account probing, Turnstile on auth forms, breached-password check, optional 2FA (TOTP + recovery codes) with a nudge; redesigned /account/ (tabs) and author pages with the writer's own photo, cover, bio and links; all verified accounts get the newsletter unless they switch it off
- **Turnstile** finished (D60): action + hostname checks, fail-closed; **draw/redraw a cover** from any post or in bulk (D60)
- **Copywriter** for all letters; AI team page with the plan (D61); **shared knowledge + trend scout** for the team, and the editor's plan `docs/STRATEGY.md` (D62)
- ✅ **Strategist** + edit link on public pages + short AI wording in privacy/terms (D63)
- ✅ **Reader features** (D64): site search, likes, reading list ("পরে পড়ব"), follow a writer (with new-piece notices), new writer card under each post
- ✅ **Speed** (D65): guest pages cacheable at the edge (Cloudflare rule steps in DEPLOYMENT.md); ⬜ owner adds the Cloudflare cache rule
- ✅ **Honoured writers** (D66): মাসের লেখক proposal, badge + editor's praise on writer pages and home
- ✅ **Writers' audio readings** (D67): upload from the account page, owner approves; ⬜ check Hostinger's PHP upload limit is ≥ 25 MB
- ✅ **Issues as books** (D68): page-turning reader view, EPUB, print-ready PDF (6×9 in) and store listing text; Amazon KDP doesn't take Bengali → Google Play Books
- ✅ **Relaunch letter** draft + post, **online-only** campaigns, English filler options (D69); **undo** for covers and marketing, weekly plan pinned (D70)
- ✅ **Certificate template** (D71): design on the check page, PNG + PDF download, share; issue/open/withdraw from the post's শংসাপত্র menu; e-mail to the writer
- ✅ **Certificates finished** (D73): automatic issue, manual/bulk for old pieces, writer requests, four designs, QR code, signature settings, desk page with previews
- ✅ **Give the AI team work by hand** (D72): run now / redo per member, requests in your own words, results with links
- ⬜ Owner: set the signer's name and signature picture on /desk → আরও → শংসাপত্র
- ⬜ Owner: read and send the relaunch letter (চিঠি); decide the English filler piles (STRATEGY.md §6)
- ⬜ Later: serial fiction, reader tips (after payments are approved), volunteer editors (the editor role with 2FA already exists; add them from ব্যবহারকারী)
- ⬜ Later: automatic Facebook posting (owner turns on), anthology e-book file, Search Console data for the SEO expert, privacy-policy wording for the read counter (owner)

## Deploys
- ✅ Visitors see a Bengali "একটু পরেই ফিরছি" page (503, auto-retry every 30 s, noindex) for the whole deploy instead of 500s: maintenance mode now starts before any file is uploaded, and the page is pre-rendered so it works even while vendor/ is half uploaded. If a deploy fails, that page stays up until the next good deploy

## Desk UX (owner: "too many places to check") ✅
- /desk opens on **আজকের কাজ**: one inbox with every item waiting for a person (new submissions oldest first with "পড়ুন ও সিদ্ধান্ত নিন"; reader comments with প্রকাশ / স্প্যাম right there); an info box needing no action; admin box folded
- Side menu for editors: আজকের কাজ · লেখা · মন্তব্য, plus a folded "আরও" (সংখ্যা, লেখক, পাতা). Admins also get a folded "প্রশাসন" (বিভাগ, রিডাইরেক্ট, ব্যবহারকারী, কাজের খাতা, AI)
- Post page: মনোনীত / বদল চাই / বাতিল / AI রিপোর্ট buttons on top, "জমার তথ্য" box (writer, words, earlier pieces, originality, AI score, last note); after a decision it goes back to the list
- Whole desk in Bengali (Filament's own `bn` strings via `DeskLocale`), stock green/red replaced by palette colours
- Every screen rebuilt in Bengali: comments (moderation tabs, no fake "create"), authors (type, count, fixed URL), issues (12 cover themes, post count), sections and redirects (admin-only, URL can't change, paths validated, on-site targets only), users (role, 2FA, old WordPress role, spam flag, last login; new accounts get a password link; admins can't demote or delete themselves)
- Admin "লেখকের চোখে দেখুন" (D85): see the account and site exactly as a writer or reader does; look only, 30 minutes, logged. Replaces the test writer account.
- Helper chat (সহায়ক) on all account tabs, with a guide to the account pages (D86).
- Marketing: one-tap share to Facebook, WhatsApp, Instagram (D87).
- Share previews: 1200×630 picture for every piece, preview bots allowed on staging, clean title and description (D88).
- Contact and advertising forms with a /desk inbox (বার্তা) instead of an e-mail address (D89).
- Writers ask the editor ("বদল চাই") to change a submitted/published piece; live audio locked too (D94).
- New sections ঐতিহ্য ও উৎসব + রান্নাঘরের গল্প, English pieces in English, grouped menu with English in the top bar, desk drafts with fact-check gate (D95). Next: owner rewrites + checks the two example drafts; invite writers for these sections in the relaunch letter.
- Themed collections (/sonkolon/): 8 seeds, archivist proposes from the whole archive, owner arranges and publishes in /desk → সংকলন; শীতের লেখা queued first (D97). All 8 queued; monthly seasonal calendar prepares each a month ahead (D98). Next: owner reviews and publishes the 8 proposals in /desk → সংকলন.
- Renaming a published post's URL now leaves a 301 automatically (logged)
- Read-only audit log ("কাজের খাতা", admin)
- No English left in /desk: the 194 strings missing from Filament's Bengali pack are translated in `lang/vendor/*/bn/` (a test fails if a Filament update adds untranslated ones); Bengali month/day names in the date picker and Bengali digits on screen (`public/js/desk-bn.js`, display only); own labels de-Englished (ঠিকানা, গুগল, এআই, ইংরেজি…). Left as-is: names (Claude, OpenAI), example paths, typed values in inputs, and content titles
- Delete is admin-only everywhere
- Checked in a real browser as editor and admin, including 2FA login

## Launch blockers (must be done before cutover)
- After launch (D92): staging → main releases, nightly + pre-deploy backups, staging refresh + scrub; docs/DEPLOYMENT.md → After launch
- Launch plan (D90): copy staging to production, same APP_KEY, `hatpakha:move-host`; steps in docs/DEPLOYMENT.md → Cutover
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

## ছোটদের হাতপাখা (D101), 2026-11-07
- [x] Section `chhotoder` + menu group ছোটদের + `/bornomala/` alphabet page (approved pictures/voices only, tap to hear)
- [x] Piece page: age badge, শব্দের মানে, bigger text, AI-voice label, no comments, no ads; section age filter; home box
- [x] AI agents শিক্ষক (age + glossary) and কথক (OpenAI TTS); designer draws the alphabet; /desk → ছোটদের হাতপাখা for approvals
- [x] 15 Wikisource classics imported as drafts (check against the source link, restore stanza breaks)
- [x] Voice picker (D102): samples in every voice, separate choice for the alphabet and for stories, redo old recordings
- [x] Google Bengali voices (D103), bn-IN-Standard-A by default
- [ ] Owner: add GOOGLE_TTS_KEY to the ENV_FILE secret (staging, later production)
- [ ] Owner: make voice samples, choose voices by ear, then run the four jobs in /desk → ছোটদের হাতপাখা and approve pictures/voices
- [ ] Owner: check and publish the classics; পিঁপড়ে আর পিঁপড়ীর কথা has a suspected caption line inside the text
- [ ] Later: invite parents and writers to send children's pieces; a native copy editor checks the alphabet words

## Relaunch (D104), 2026-11-07
- [x] Relaunch plan in docs/STRATEGY.md §3b
- [x] Old submissions: AI reads each once (worth asking / too weak); owner can change; never published without the writer's yes
- [x] Apology letters start by themselves on hatpakha.com after the owner approves the text; writer answers per piece from a signed link
- [ ] Owner: check the AI's list and the letter text (/desk → পুরনো লেখকদের চিঠি), then approve
- [ ] Owner: message the 145 core writers; plan the ফিরে আসা সংখ্যা; 6-week schedule before announcing
- [ ] Later: automatic bounce handling for the "we're back" letter

## ধারাবাহিক (D108), 2026-11-08
- [x] Three AI serial writers (রহস্য, অলৌকিক, সম্পর্ক): story plan, weekly episode drafts with pictures, one week ahead
- [x] /dharabahik/ pages, "ক্রমশ…" and next/previous episode, follow a serial for new-episode e-mails, home shelf
- [x] Every AI-written piece gets a designer cover proposal
- [ ] Owner: read the three proposals in /desk → ধারাবাহিক, edit episode 1, approve its picture, publish (that starts the serial)
- [ ] Next: "আপনার বই" (book-making for writers), plan agreed first

## Phase 1 sections and human feedback (D112, D113), 2026-11-09
- [x] ফিরে পড়া, ফিরে দেখা · জনপদের গল্প · রহস্যময় বাংলা: sections, desk briefs, jobs, share pictures, 3 drafts each queued
- [x] "মানুষের মতামত" on AI pieces: rewrite this piece (owner accepts, can go back) and/or a standing rule; /desk → লেখার নির্দেশিকা
- [ ] Owner: edit and publish the first drafts (about one per section per week); give a few pieces to real readers and record their feedback
- [ ] After 8 weeks: keep the sections people read and share; consider Phase 2 (প্রবাসের কথা, শব্দের গল্প, মাঠের গল্প)

## Desk: delete everywhere and collapsible panels (D115), 2026-11-09
- [x] Every desk list: a red "মুছুন" on each row and "বেছে নেওয়াগুলো মুছুন" for bulk delete (pieces, writers, sections, issues, pages, newsletters, contests, collections, serials, comments, messages, redirects, users, writing rules); every edit page has the same button
- [x] Pieces go to the trash (filter "ট্র্যাশ"): bring back, or delete forever, one by one or in bulk; a trashed published piece answers 410
- [x] Marketing: delete a weekly plan or a post, clear the posted/skipped list; AI team: delete or cancel a job, clear finished jobs
- [x] Panels on the desk pages and edit forms can be folded (pages remember it per browser)

## Facebook and Instagram (D116), 2026-11-09
- [x] Kit: docs/SOCIAL.md + pictures in docs/social/ (profile, cover, two post pictures); site footer and structured data link both accounts
- [ ] Owner: Part A now (link the accounts, update fields, post and pin "নতুন রূপে ফিরছে")
- [ ] Owner, launch day: Part B (second pinned post, automatic reply, domain verification code in ENV_FILE, Sharing Debugger)

## E-mail: Hostinger + Amazon SES (D117), 2026-11-09
- [x] Separate sender for letters (SES over SMTP), daily limit, counted clicks per letter and per link in /desk, automatic bounce/complaint suppression via SNS, privacy policy updated
- [ ] Owner: follow docs/EMAIL.md (Hostinger mailbox, SES domain + DNS, production access, SNS topic, ENV_FILE), then send a test letter and check SPF/DKIM/DMARC pass

## Google Analytics + Search Console for the AI team (D119), 2026-11-09
- [x] GA4 tag (live site only, path only, consent mode for Europe, not on children's pages), daily snapshot, AI team reads it, /desk panel, privacy policy, letter utm labels
- [ ] Owner: docs/GOOGLE.md (GA4 property, Search Console, service account, access, ENV_FILE), then "এখনই আনুন" in /desk → এআই সেটিংস
- [x] D120: writers by pieces, every account on the letter list (except opted-out and spam), /desk → চিঠির তালিকা
- [x] D121: bot check (existing accounts + new sign-ups), manual review in /desk → চিঠির তালিকা → বট-যাচাই
- [ ] Owner: review the flagged accounts before the first big letter
- [x] D122: form receipts, confirmations that survive caches, old CSS builds kept, Cloudflare purge after deploys
- [x] D123: /desk → ই-মেলের নমুনা (every automatic email: preview, edit, back to the original); Bengali email frame
- [x] D126: agent-ready production: Content-Signal (search and answers yes, training no) + training crawlers shut out, /llms.txt, Markdown on `Accept: text/markdown`, Link header, sitemap index dates, contact point (form only)
- [ ] Later (owner said "not now"): a read-only MCP endpoint + Server Card + WebMCP search tool, once agents use them
- [x] D127: installable site (home screen, offline reading) + phone notifications for followed serials and writers (docs/APP.md)
- [ ] Owner: run `php artisan hatpakha:vapid` on the server, put the two lines in production ENV_FILE, redeploy, test on a phone (docs/APP.md)
- [x] D128: notifications without an account (🔔 on writer and serial pages, daily evening note), /app/ page + footer "যোগ করুন" buttons + menu link, one-time "ফোনে জানাব?" right after adding to the phone
- [x] Emails: the newsletter has the same logo header as every other e-mail; long links (unsubscribe, stop following) show as "এক চাপে বন্ধ করুন" and can never stretch the card wider than the header; web addresses typed in a letter become links
- [x] Newsletter sending: a batch where every letter fails pauses the letter; /desk shows "কেন যায়নি" (reasons in plain words) and "যেগুলো যায়নি, আবার পাঠান"
- [x] Newsletters: "কপি করুন" (duplicate) on the letter page and in the list: a new draft with the same words, pieces and audience
- [x] D129: newsletters through Amazon SES or Brevo, chosen in /desk → চিঠি → পাঠানোর পথ (each with its own daily limit)
- [x] Newsletter deliverability: the List-Unsubscribe one-click (POST, RFC 8058) now really works (was GET-only, which Gmail counts against the sender)
- [x] App icon badge: the number of waiting notifications shows on the home-screen icon; opening the app clears it
- [x] AI team requests: cut-off answers retried with 3× room; passing failures retried by themselves (3 tries); clear status (waiting/working/done/failed, try n/3, started X ago); errors in plain words with what to do; phone notification when done or failed
- [x] D130: AI team jobs show their steps (received → who → working → ready), auto-retry, plain errors, phone notice
- [x] D131: "write a story…" requests become a complete draft post (section, tags, SEO, cover request), opened from the request
- [x] AI team page: "তালিকা থেকে মুছুন…" lets the owner choose (opened finished / all finished / failed / both), each with a count; waiting or running jobs never
- [x] D132: install screenshots, offline reading list, /desk → ফোনে খবর (numbers + announcements), section choices, share to Hatpakha, morning fetch
