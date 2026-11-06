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
- ✅ Tests: 45 passing (SEO URL policy, meta, JSON-LD, sitemaps, headers, XSS, legacy login, roles)
- ✅ CI (tests + composer/npm audit) and SSH deploy workflows
- ✅ Old WordPress pages imported at their URLs; 7 rewritten as an editor (docs/PAGES-REVIEW.md, owner to confirm); plugin pages 301; /desk → পাতা
- ✅ Fixed: sanitizer was deleting text inside <span>/<div> (346 posts, 250 published); now unwrapped and kept, with regression tests
- ✅ WordPress shortcodes converted ([gallery], [caption], [embed] YouTube, page-builder); same-site links made relative
- ✅ Post polish (structure only, D23): 1,762 posts tidied; 441 poems turned from one-paragraph-per-line into stanzas; 109 repeated titles removed (prose only); 32 YouTube players restored; new reading typography for prose, poems, images, video, quotes, tables. Text verified identical against WordPress for every post
- ⬜ Self-host and subset fonts (currently Google Fonts CSS)
- ✅ Staging: WordPress imported (same counts as local), uploads copied (6.7 GB), owner promoted to admin
- ⬜ Uploads clean-up: remove plugin leftovers (e.g. `uploads/mailpoet` PHP cache) from the copy; review WordPress admin/editor accounts imported with panel access
- ⬜ Image pipeline: WebP/AVIF sizes for old uploads
- ⬜ Mobile layouts polished against design boards ৩–৭
- 🟡 Category merge: table ready for the owner in docs/CATEGORY-MAP.md (32 old categories → 10 sections; all old URLs 301)

## Next up (in order)
1. ✅ Owner: created the GitHub Environments (`staging`, `production`) and secrets (docs/DEPLOYMENT.md), create `new.hatpakha.com` and its database on Hostinger.
2. ✅ Staging live at new.hatpakha.com (app outside the web root via symlink; Cloudflare bypass rule for staging; DB imported; uploads copied).
3. 🟡 Owner: first admin login + 2FA on staging; uploads clean-up; list of imported admin/editor accounts.
4. Offer email 2FA only where real mail is configured (staging uses `log`, so authenticator only there).
5. Finish the Phase 1 leftovers above.
6. Before launch: Cloudflare cache rules for production (cache guest HTML; bypass `/desk*`, `/livewire*`, `/submit*`, `/account*` and requests with the session cookie).
7. ⚠️ Phase 2 must make the final re-import skip posts edited in /desk (today `hatpakha:import-wp` overwrites post bodies).
8. Phase 2: full redirect inventory (crawl the old site + Search Console export) and redirect tests; content audit labels.

## Later phases (from PLAN.md §8)
- Phase 2 Migration · Phase 3 Submissions + AI review (+ blind provider test) · Phase 4 Launch · Phase 5 Issues, e-book/flipbook, contests, certificates, newsletter · Phase 6 Growth
