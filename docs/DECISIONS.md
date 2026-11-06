# Decision log

Append-only. Newest at the bottom. Don't edit past entries; if a decision changes, add a new entry that supersedes it.
Format: **date · decision · reason · decided by**.

| # | Date | Decision | Reason | By |
|---|---|---|---|---|
| D1 | 2026-11-05 | Rebuild off WordPress; positioning: Bengali literary magazine for new writers | WordPress + ~20 plugins unmanageable, SEO broken, site dormant since 2024 | Owner |
| D2 | 2026-11-05 | Hosting: **Hostinger Cloud**, PHP 8.4, **MySQL** | Owner's existing hosting | Owner |
| D3 | 2026-11-05 | Stack: **Laravel + Filament** (Blade, Tailwind, Alpine, Livewire where needed) | Node apps idle-stop on Hostinger; PHP + LiteSpeed cache is fastest there | Owner |
| D4 | 2026-11-05 | **Bengali-first**; existing English posts kept in a separate "English" section | Core audience and archive are Bengali | Owner |
| D5 | 2026-11-05 | Staging at **new.hatpakha.com** | Safe testing before cutover | Owner |
| D6 | 2026-11-05 | Deploy with **GitHub Actions over SSH**; secrets in GitHub Environments; `.env` never committed | Security (docs/SECURITY.md §6) | Owner + Claude |
| D7 | 2026-11-05 | Publishing is **free**; first monetisation = **AdSense + free contests**; paid features after the payment gateway is approved | Readers and writers won't pay easily; build audience first | Owner |
| D8 | 2026-11-05 | Design: **"পাখা খুলুক" (Design 1.0) in the exact logo palette**, plus the human-touch elements; Design 2.0 rejected | Owner preference | Owner |
| D9 | 2026-11-05 | **12 issues/year**: 6 festival specials + 6 monthly; covers per the canvas | Festivals are uneven; monthly rhythm needed | Owner |
| D10 | 2026-11-05 | **Automatic e-book (PDF + EPUB) and flipbook** per issue; PDF via headless Chromium in GitHub Actions (fallback mPDF) | Correct Bengali conjuncts; Hostinger can't run Chromium | Owner + Claude |
| D11 | 2026-11-05 | **AI layer is provider-neutral**; Claude vs OpenAI chosen by a **blind test in Phase 3** | Cost similar; quality on Bengali decides | Owner |
| D12 | 2026-11-05 | **Author certificates** with QR verification page; free digital, paid premium and printed later | Writer value + monetisation | Owner |
| D13 | 2026-11-05 | SEO and security specs (`docs/SEO.md`, `docs/SECURITY.md`) are **binding** and enforced by tests | Owner asked for proper SEO and security | Owner |
| D14 | 2026-11-05 | Keep **exact WordPress slugs** (decoded, NFC) at `/{slug}/` with **trailing slash**; new posts get Latin transliterated slugs | Zero SEO loss | Claude (per D13) |
| D15 | 2026-11-05 | 2FA (TOTP) **mandatory** for editors and admins; admin panel at `/desk` | docs/SECURITY.md §2 | Claude (per D13) |
| D16 | 2026-11-05 | Pending backlog (424, mostly 2016–19) handled in **tiers** (recent / cleanup / ask-author) | Real data audit (docs/DB-AUDIT.md) | Owner |
| D17 | 2026-11-05 | Package: `symfony/html-sanitizer` for rich text | Maintained, allowlist-based sanitiser | Claude |
| D18 | 2026-11-05 | Home page caches **IDs, not models** | Laravel 13 refuses to unserialize models from the cache (security default kept) | Claude |
| D19 | 2026-11-05 | Two GitHub Environments, `staging` (new.hatpakha.com) and `production`; the site is **noindex unless `SEO_INDEXABLE=true`** (production only, from cutover) | Test safely; staging must never compete with the live site in Google | Owner (environments) + Claude (noindex flag) |
| D20 | 2026-11-05 | 2FA can use **email codes** as well as an authenticator app; 2FA stays mandatory for the panel | Owner asked for an easier option; app remains recommended for admins | Owner |
| D21 | 2026-11-06 | Old WordPress pages keep their URLs; key pages rewritten by the editor (not copied); unreviewed old pages are noindex; plugin pages 301 to home | Owner: "don't blindly copy, work as a magazine editor" | Owner + Claude |
| D22 | 2026-11-06 | Sanitizer **unwraps** unknown tags (keeps text) and **drops** script/style/form/iframe with contents | Dropping unknown tags deleted text from 346 imported posts | Claude |
| D23 | 2026-11-06 | Every post body is tidied on save by `App\Support\BodyFormatter`: structure and look only (paragraphs, stanzas, empty lines, images, all-bold, repeated title in prose); refuses any change that would alter a visible character | Owner: "polish all posts, don't change the content" | Owner + Claude |
| D24 | 2026-11-06 | Fonts self-hosted from `public/fonts/` (Google's own subsets, unmodified, OFL); no third-party font requests; admin uses the same file via Filament's LocalFontProvider | Speed (no extra DNS/TLS), privacy, stricter CSP | Claude |
| D25 | 2026-11-06 | Content audit and backlog tiers are **suggestions**; only an admin can apply them, one by one or in bulk, each logged and reversible (noindex flag, or archive + 410/301 redirect) | CLAUDE.md §3: deleting/unpublishing needs the owner | Claude |
| D26 | 2026-11-06 | Posts edited in /desk are never overwritten by a WordPress re-import | Final delta import must not destroy editorial work | Claude |
| D27 | 2026-11-06 | Imported WordPress admins/editors become **writers**; panel roles only by explicit grant (logged). A re-import revokes panel roles that came only from WordPress | An old admin password + mandatory-2FA-at-first-login would let an attacker enrol their own 2FA | Claude (security) |
| D28 | 2026-11-06 | Newsletter: only explicit, confirmed opt-ins are mailable; auto-added WordPress users need a one-time re-consent email (owner approves); unsubscribed/bounced suppressed forever | Consent and deliverability; CLAUDE.md §3 | Claude |
| D29 | 2026-11-06 | **Submissions only through the website form** (`/submit/`, from the writer's account). No email, Facebook or WhatsApp submissions | Owner decision | Owner |
| D30 | 2026-11-06 | Approved: docs/AUDIT-SUMMARY.md (remove 7, merge 9, noindex 66), docs/PAGES-REVIEW.md policies (with D29 replacing point 1), docs/CATEGORY-MAP.md including `puja-2020` → archive issue "শারদীয়া ১৪২৭" (`/sonkha/2020-10/`) | Owner review | Owner |
