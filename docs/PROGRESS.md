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
- ✅ Admin `/desk`: branded, editor+ only, mandatory TOTP 2FA, admin-only user management
- ✅ Tests: 21 passing (SEO URL policy, meta, JSON-LD, sitemaps, headers, XSS, legacy login, roles)
- ✅ CI (tests + composer/npm audit) and SSH deploy workflows
- ⬜ Self-host and subset fonts (currently Google Fonts CSS)
- ⬜ Image pipeline: WebP/AVIF sizes for old uploads; upload scanner/quarantine for the WordPress uploads folder
- ⬜ Mobile layouts polished against design boards ৩–৭
- ⬜ Category merge redirects reviewed by the owner (23 old categories → 10 sections; see `ImportWordPress::SECTION_MAP`)

## Next up (in order)
1. Owner: create the GitHub Environments (`staging`, `production`) and secrets (docs/DEPLOYMENT.md), create `new.hatpakha.com` and its database on Hostinger.
2. Deploy to staging; import the DB there; copy the uploads (with the scan).
3. Finish the Phase 1 leftovers above.
4. Phase 2: full redirect inventory (crawl the old site + Search Console export) and redirect tests; content audit labels.

## Later phases (from PLAN.md §8)
- Phase 2 Migration · Phase 3 Submissions + AI review (+ blind provider test) · Phase 4 Launch · Phase 5 Issues, e-book/flipbook, contests, certificates, newsletter · Phase 6 Growth
