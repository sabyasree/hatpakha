# Hatpakha 2.0: Rebuild & Growth Plan

*Prepared 5 Nov 2026. Roles: UX designer, magazine editor, publisher, business analyst, SEO lead.*

---

## 0. What I found on the current site (audit of hatpakha.com)

Everything below comes from the site's public pages and its public WordPress REST API.

| Item | Finding | What it means |
|---|---|---|
| Content | **1,393 posts** (2012 to Sept 2024), 25 pages, **3,199 media files**, 397 comments | 12 years of archive. That is the main asset. |
| Mix | **Poetry 648**, **Stories 400**, Social 100, Editorial 46, Travel 38, Inspiration 35, Literature 29 … | About 75% is Bengali literature. That is the identity. |
| Activity | 73 posts in 2021, 11 in 2022, **0 in 2023**, 16 in 2024, nothing since Sept 2024 | The site has been inactive for about 2 years. Google has already reduced its crawl priority. |
| Recent posts | Generic English listicles ("Top 10 parenting tips", "Netflix…", "Python concepts") | These weaken the brand and are the kind of thin content Google's helpful-content system demotes. |
| Taxonomy | 32 categories (several empty or duplicated: *Life / LifeStyle*, *Article / Miscellaneous / main*), **2,278 tags** | Most tags hold 1–2 posts. That makes thousands of thin pages that hurt SEO. |
| Plugins | Newspaper theme (tagDiv + td-composer), Yoast, AMP, LiteSpeed, Wordfence, Co-Authors Plus, MailPoet + MC4WP, WooCommerce, Contact Form 7, JWT auth, an MCP plugin… | Too many plugins that overlap (2 newsletter plugins, AMP + theme, WooCommerce for almost nothing). |
| Performance | Homepage is **454 KB of HTML with 96 `<script>` tags** | Slow on mobile in India, so Core Web Vitals fail. |
| SEO bugs | `sitemap_index.xml` returns **404** while `sitemap.xml` exists (two sitemap systems in conflict); robots.txt blocks `/wp-content/plugins/` (Google can't load CSS/JS); AMP versions still 301-redirect | Plugins fighting each other. This explains "SEO not working". |
| URLs | `/%postname%/` with many **Bengali-script slugs** (percent-encoded) | We must keep every one of these URLs exactly as it is. |
| Strengths | **ISSN 2321-3035** registered, 12-year history, ~1,000 original Bengali poems and stories | This credibility is rare and worth building on. |

User accounts are not public (HTTP 401). To copy them we need admin access (see §5).

---

## 1. Strategy (publisher / business analyst view)

### Positioning
**"Hatpakha: the Bengali literary web magazine where new writers get published, edited and read."**

Stop publishing generic English SEO articles. The site can't win against big media on "parenting tips". It **can** be the #1 destination for:
- Bengali poetry (*kobita*), short stories (*golpo*), *rommo rachana*, travel writing, translated literature
- **New writers** who want an editor, a real ISSN-registered publication and readers
- Bilingual readers (Bengali + English) in West Bengal, Bangladesh and the diaspora

### Business model
Publishing stays **free**. Revenue comes from AdSense (base layer), affiliate links, local brand sponsors, optional paid extras for writers (contests, detailed feedback, fast-track, book publishing, workshops) and the yearly **Sharodiya** issue. **Full research and numbers: [`MONETIZATION.md`](MONETIZATION.md).**

### KPIs to track from day 1
| Metric | 6-month target | 12-month target |
|---|---|---|
| Organic clicks/month (Search Console) | back to the pre-decline level | 3× that level |
| Submissions/month | 100 | 400 |
| Published pieces/month | 40 | 120 |
| Newsletter subscribers | 2,000 | 10,000 |
| Paying members | 50 | 300 |
| LCP on mobile | < 2.0 s | < 1.8 s |

---

## 2. Technology choice (updated: Hostinger Cloud hosting + MySQL)

### Constraints
- **Hosting:** Hostinger Cloud hosting (LiteSpeed web server, PHP, MySQL, cron, hPanel, Cloudflare in front).
- **Database:** MySQL.
- **Must be very fast**, with an easy submission process and good user profiles.

On Hostinger, Node.js apps (Next.js) are **stopped after idle time and cold-start** on the next visit, and the managed database is MySQL. PHP under LiteSpeed is always warm and has a built-in full-page cache. So the best stack for *this* host is PHP-based, not the earlier Next.js + Postgres idea.

### Recommendation: **Laravel + Filament + MySQL** (server-rendered, cached)

| Layer | Choice | Why |
|---|---|---|
| Framework | **Laravel** (latest stable) on **PHP 8.4** | Runs natively on Hostinger. Mature, well documented, and easy to hire for in India. Auth, queues, mail, scheduler and storage are built in. |
| Admin / editor panel | **Filament** | A polished admin for editors: submission queue, AI report view, approve/reject, users, issues and settings. Built in days, not months. |
| Public site | **Blade templates + Tailwind CSS + Alpine.js** (small JS), **Livewire** only where interactive (submit form, profile edit, follow, comments) | Pure HTML pages with almost no JS, so reading pages load instantly even on slow 4G. |
| Database | **MySQL 8 / MariaDB** (Hostinger) | Same database family as WordPress, so migration is simple. Full-text search for Bengali + English. |
| Speed layer | **LiteSpeed Cache (LSCache for Laravel)** full-page cache + **Cloudflare** CDN/edge cache, WebP/AVIF images, self-hosted subset Bengali fonts | Cached pages are served in milliseconds. Target: **LCP < 1.5 s on mobile, < 50 KB JS per page.** |
| Background jobs | Laravel queue (database driver) run by **Hostinger cron every minute** | AI review, image resizing and emails run in the background. The writer never waits. |
| Rich text editor | **Tiptap** (used in Filament and the submission form) | Clean paste from Word / Google Docs, Bengali Unicode, autosave. |
| Media | Keep on Hostinger at the **same path `/wp-content/uploads/...`**, behind Cloudflare | Old image URLs, Google Image rankings and backlinks keep working. Cloudflare R2 is an optional later move. |
| Login | Email + password, **Google login**, and **magic link** (passwordless email) | No friction for new writers. |
| Email | Hostinger SMTP at first, then **Resend / Brevo** for reliability; newsletter via Brevo or self-hosted Listmonk | Replaces MailPoet + MC4WP. |
| Payments | **Razorpay** (India) + Stripe (diaspora), later | Memberships, contest fees, anthology orders. |
| AI | **Claude API** called from Laravel jobs: Sonnet for editorial review, Haiku for bulk tagging and SEO metadata | Strong Bengali, structured JSON output, editable rubric. |
| Analytics | Google Search Console + Umami/Plausible | |
| Code & deploy | GitHub repository → **GitHub Actions** builds assets and deploys to Hostinger over SSH (`staging` branch → new.hatpakha.com, `main` → hatpakha.com) | Every change is tracked and can be rolled back. |

### Other stacks considered for Hostinger Cloud
| Option | Verdict |
|---|---|
| **Laravel + Filament** (recommended) | Best balance of speed, built-in features and fit with the host. |
| **Lean WordPress rebuild** (custom light theme, about 5 plugins, custom plugin for AI and submissions) | Cheapest and keeps users and URLs with zero migration. But the plugin and update burden returns over time, and a custom submission + profile UX fights WordPress. Good fallback if budget is very tight. |
| **Next.js on Hostinger Node.js hosting** | Excellent framework, but idle-stop cold starts on this host hurt "very fast". It's better suited to Vercel or a VPS. |
| **Static site (Astro) + separate backend** | Fastest reading pages, but two systems to run, and submissions and profiles still need a backend. Too complex for a small team. |
| **Ghost / hosted headless CMS** | Doesn't run on Hostinger Cloud shared plans, and contributor workflows are weak. |

**Rough running cost:** your Hostinger Cloud plan + Claude API (expect well under ₹5 per reviewed piece) + optional email service (free tiers cover the start).

### How "very fast" is guaranteed
1. Every public page is **server-rendered and fully cached** (LSCache + Cloudflare). The cache is cleared only for the pages that change when a post is published or edited.
2. **No heavy theme or page builder.** Hand-built templates have one CSS file (about 15 KB) and almost no JS.
3. **Responsive images** (WebP/AVIF, `srcset`, lazy-load), generated in the background at upload.
4. **Bengali fonts self-hosted and subset**, with `font-display: swap`.
5. A **performance budget checked on every deploy** (Lighthouse CI): a change that makes pages slower doesn't ship.

---

## 3. Product & UX design

### Roles
`Reader` → `Writer` (contributor) → `Trusted Writer` → `Editor` → `Editor-in-chief` → `Admin`

### Key screens
1. **Home = the current issue**: a cover image, an editor's note, then sections (Kobita · Golpo · Probondho · Bhraman · Onubad). It reads like a magazine, not a blog roll.
2. **Reading page**: built for reading only. Bengali serif type (Noto Serif Bengali / Tiro Bangla), 18–20px, line-height 1.8, **poem mode** that keeps line breaks and stanza spacing, reading time, light/sepia/dark themes, WhatsApp/Facebook share (most sharing in Bengal happens on WhatsApp), author card, "more from this issue".
3. **Author pages**: bio, photo, all works, follow button. Writers share these, so they bring new readers.
4. **Issue archive**: every monthly issue and Sharodiya issue as a cover grid. Each issue has a web view and a PDF/flipbook view.
5. **Submit**: a simple editor (paste from Word/Google Docs, Bengali Unicode), category, language, declaration of originality. A **status tracker** shows the stages: Received → AI check → Editor review → Scheduled → Published, with feedback.
6. **Editor dashboard**: a queue sorted by AI score, side-by-side AI report, one-click Approve / Request changes / Reject with a templated, kind message.
7. **Mobile-first**. Most readers are on mid-range Android phones with patchy 4G, so the page budget is under 100 KB of JS.

### Submission process: designed for "submit in under 2 minutes"
1. **Big "লেখা পাঠান / Submit your writing" button** on every page. You can start writing *before* logging in; login is asked only at the end (Google, magic link or email).
2. **One screen:** title, the text (paste from Word / Google Docs / WhatsApp, which gets cleaned automatically), section (Kobita / Golpo / …) and an optional cover image. Tags and SEO are filled by AI, not by the writer.
3. **Autosave** every few seconds, so nothing is ever lost. Works well on mobile, and Bengali keyboards (Avro, Gboard) work naturally.
4. **Preview exactly as it will appear** (poem mode keeps line breaks).
5. One tick-box originality declaration, then Submit.
6. **Status tracker + email/WhatsApp-style notifications**: Received → Being reviewed → Accepted / Changes requested → Scheduled → Published. Feedback is shown kindly, with "Edit & resubmit" in one click.
7. Writers can see **all their drafts, submissions and published works** in "My writings".

### User profiles
- **Public profile URL:** `hatpakha.com/writer/<username>` with photo, cover, bio (Bengali/English), location, social links, "writer since", badges (Published, Featured, Contest winner, Trusted writer).
- **Works** grouped by section, plus featured works pinned by the writer, followers count, total reads.
- **Follow** writers, then get new works in your feed / weekly email.
- **Writer dashboard:** reads per piece, comments, submission history, AI feedback history (writers learn from it), membership status.
- **Reader profile** (light): saved/bookmarked pieces, followed writers, comment history.
- **Privacy controls:** writers choose what is public (e.g. hide location). Pen names are supported.
- **Legacy authors** from WordPress get profiles automatically and can **claim** them by email.

### Design language: "পাখা খুলুক" (let the fan open)
Design canvas (all screens): https://claude.ai/artifact/4iCfy5Pt1DJqTyC2DNUUcG
- **Chosen direction: Design 1.0 in the logo's colours** (vivid "পাখা খুলুক" layout, recoloured on owner request to the **exact logo palette**: sky `#2DAAE2`, fan red `#CB1B32`, fan pink `#F08D90`, fan peach `#EFC08D`, plus their tints and shades (sky `#7CCBEE`/`#1A86C2`/`#12304A`, red `#B0162B`/`#7A1022`, peach `#FBEBD8`/`#E39A6A`), navy text `#13212C`, white `#FFFBF4`. **No colours outside the logo family** in the interface. Exceptions: WhatsApp/success green, neutral greys, and natural object colours inside illustrations (leaves, clay). White text always sits on red or navy, never on pink or peach; gradients and gold foil on heroes, covers and contest banners; Anek Bangla headlines, Noto Serif Bengali reading text, Galada accents; sunrise-fan hero, fan mood dial, taped story pages, stamp travel cards, postcard newsletter, ticket-stub contest). **Approved by the owner on 5 Nov 2026.**
  - Always: real authors and real opening lines (never placeholder brackets in anything public), and an SVG logo.
  - **Human touch** (approved): "পাঠকের চিঠি" (real reader comments, shown with permission or an opt-out), "এই দিনে" (on-this-day archive pick), the editor's hand-drawn "সম্পাদকের পছন্দ" note, real counts (14 years · 488 writers · 1,393 pieces), and real reasons in the UI copy (e.g. the 2019 lost-story comment explaining autosave).
- **12 issue covers:** 6 festival specials (বইমেলা, বসন্ত, নববর্ষ, রবীন্দ্র-নজরুল, শারদীয়া, দীপাবলি) with gold-foil titles and folk-art illustration, and 6 monthly issues (ভাষা, গ্রীষ্ম, বর্ষা, শ্রাবণ, হেমন্ত, বর্ষসেরা) with solid titles. One template system with the logo masthead, cover lines and an ISSN barcode, reused for the hero, social images, the newsletter and the e-book. Final art is by a human illustrator; the shape-built covers are layout templates.
- **Mobile-first:** bottom tab bar (হোম · রিল · সংখ্যা · আমি), floating "লিখুন" button, Kobita Reels, and swipe flipbook.

---

## 4. AI editorial workflow (the "AI checks posts" part)

**Rule: AI assists, humans decide.** AI never publishes on its own in the first 6 months. After that, *Trusted Writers* with a high score can be fast-tracked to "scheduled", and an editor can still veto.

```
Submission
   │
   ├─▶ 1. Safety & policy check     (hate, sexual content, defamation, spam links)   → auto-reject + reason
   ├─▶ 2. Originality check         (similarity vs. our 1,393-post archive +
   │                                 web plagiarism API e.g. Copyleaks)               → flag
   ├─▶ 3. Editorial review (Claude) → JSON report:
   │        • genre, language, quality score 1–10 against our rubric
   │        • strengths, 3 concrete improvement suggestions (sent to writer)
   │        • spelling / grammar / বানান suggestions (Bengali + English)
   │        • suggested category + max 5 tags from a CONTROLLED tag list
   │        • SEO: title variants, meta description, transliterated slug, image alt text
   │        • "fits current issue theme?" yes/no + why
   ├─▶ 4. Editor queue (sorted by score; AI report shown alongside)
   └─▶ 5. Approve → schedule → auto-publish → newsletter + social snippets drafted by AI
```

Notes:
- **No reliable detector exists for AI-generated writing.** We use the originality declaration, plagiarism and archive similarity checks, and the editor's judgement. We don't make claims about detecting AI.
- The rubric lives in the CMS as editable text, so the editor-in-chief can tune the AI's taste without code.
- Every AI decision is logged with the piece for transparency and appeals.

### Auto-generated magazine issues ("magazine views")
Every month (and for the Sharodiya special):
1. The editor picks a theme or lets AI propose 3 themes from approved pieces.
2. AI drafts the **table of contents**: selection, ordering and section balance (poetry/prose/travel), plus a draft **editor's note**.
3. The system builds the cover from a template (title, issue no., ISSN, featured names) plus an AI-assisted or commissioned illustration.
4. Outputs: a **web issue page**, a **print-ready PDF**, an **EPUB e-book**, a **flipbook**, and a **newsletter edition** (details below).
5. The editor reviews and clicks "Publish issue". The issue gets `PublicationIssue` structured data with the ISSN.

### E-book & flipbook: built automatically for every issue
When the editor clicks **"সংখ্যা প্রকাশ" (Publish issue)**, a background job builds everything with no manual work:

| Output | How it is made | Notes |
|---|---|---|
| **Print PDF** (A5, with bleed) + **screen PDF** (smaller) | Issue HTML laid out with **Paged.js in headless Chromium**, run by a **GitHub Actions job** that the site triggers. The PDF is uploaded back to the server. | Chromium's text shaping renders Bengali conjuncts (যুক্তাক্ষর) correctly. Hostinger can't run Chromium, so GitHub Actions does it, within free minutes. **Fallback:** mPDF on the server (PHP, Bengali via OpenType layout); its rendering must be checked before relying on it. |
| **EPUB 3** | Generated in PHP on the server (XHTML chapters + cover + table of contents, Noto Serif Bengali embedded) and validated with `epubcheck` in the same Actions job | Opens in Google Play Books, Apple Books and phone e-book apps, and on Kindle via "Send to Kindle". |
| **Flipbook** (web) | **PDF.js** (Mozilla) renders the screen PDF pages and **StPageFlip** (open-source page-curl library) turns them | Desktop: two-page spread with page-curl, thumbnails, keyboard ← →, full screen. Mobile: one page at a time, swipe, page dots. Pages load lazily so it stays fast. Deep links like `/sonkha/2026-11/read?page=12`. |
| **Cover & share images** | Festival cover template → 1200×630 (link previews), 1080×1350 (Instagram), 1080×1920 (Status/Reels) | Used automatically for WhatsApp/Facebook previews. |

- **Each piece = one chapter:** title, author, section and the author's profile link on the last line. New pieces start on a new page, poems keep their line breaks, and long stories flow over as many pages as needed.
- **Sponsor pages** from the Sharodiya sponsor packages go into the PDF/flipbook automatically, and the back cover carries the next-issue teaser.
- **SEO:** the flipbook is for the reading experience. Google indexes the normal HTML article pages, and the flipbook page links to each article.
- **Money:**
  - Reading the flipbook is free.
  - **PDF/EPUB download in exchange for an email address** (newsletter growth).
  - The same print PDF feeds **print-on-demand** copies of the Sharodiya issue.
  - AdSense sits beside the flipbook on desktop, never inside the pages.
- **Archive:** the migration also builds e-books for past "issues" grouped by year or theme, e.g. "হাতপাখা কবিতা সংকলন ২০১২–২০২৪".

---

### AI provider & cost
- **Provider-neutral AI layer** (one Laravel interface, e.g. via the Prism package). Claude and OpenAI are both supported; switching is a config change.
- **Provider decision: a blind test in Phase 3.** Run 30 real backlog pieces through both providers, have the editor score the reports without knowing which is which, then pick the winner and keep the other as fallback. Test budget: under ₹500.
- **Model tiers:** a small model (Claude Haiku 5.5 class) for tags, moods, SEO and captions; a mid model (Claude Sonnet 5.5 class) for submission review; the flagship (Claude Opus 5.5 class) optionally for contest shortlists.
- **Prices used for planning** (Claude, per 1M tokens, Nov 2026): Haiku 5.5 $0.10 / $0.50; Sonnet 5.5 $2 / $10; Opus 5.5 $4 / $20. Batch is 50% off, and cached prompt input is about 10× cheaper. OpenAI's tiers are similar; check the official page at decision time.
- **Estimated running cost:** about **₹1,300–1,800 per month** at 400 submissions per month (≈ ₹2,500–3,500 if the flagship model does every review). **One-time migration:** about ₹600 (tags and moods for 1,393 posts, triage of 424 pending, both in batch).
- **What uses NO AI:** PDF, EPUB and flipbook generation, covers, title cards, scheduling, emails, on-this-day and the mood picker (tags are precomputed). **Reader traffic never triggers AI.**
- **Cost guards:** a monthly spend cap in the provider console; cheap pre-filters (empty, short, spam) before any AI call; a per-writer daily limit (3 submissions); batch for non-urgent work; caching of the rubric; an AI-spend tile on the admin dashboard.

## 4b. Automation map: what runs by itself and what needs a person

**Target:** about **5–8 hours per week** of human work for one editor at about 40 published pieces per month (plus sales time for sponsors and Sharodiya).

| Level | What |
|---|---|
| 🟢 **Fully automatic** (no human after setup) | Scheduled publishing · page cache clearing · sitemap, SEO meta, structured data, share cards · image resizing (WebP/AVIF) · all emails to writers (received, status, feedback, published, share kit) · weekly newsletter assembled from the week's posts and sent · Facebook page / Instagram auto-post (Meta Graph API) · AdSense ad serving · contest entry payments, receipts, e-certificates · tips/payout records · daily DB + files backup · uptime monitoring · deploy on push (GitHub Actions) · **weekly AI business report** emailed to you (traffic, RPM, submissions, revenue, what to fix) |
| 🟡 **AI does the work, a human clicks approve** | Submission review → editor approves / rejects (2–3 min each) · monthly issue (AI picks pieces, order, editor's note, cover text) → editor publishes · contest shortlist → judges pick winners · social captions and quote cards · tag/category clean-up and the old-content audit · replies to common writer questions |
| 🔵 **Can become automatic later** (switch in settings, after about 6 months of trusted data) | **Auto-approve** when: writer is *Trusted*, AI score ≥ threshold, no safety / plagiarism / similarity flags. The piece is published on a delay (e.g. 12 h) so an editor can still veto, and the editor gets a daily digest of everything auto-approved. |
| 🔴 **Always human** | Editorial policy and the AI rubric · plagiarism / copyright / defamation disputes and takedowns · final contest winners · **selling sponsorships** and brand relationships · Sharodiya printing and vendors · book-publishing service (editing, ISBN, print) · workshops · refunds / payment disputes · AdSense policy issues · a 10-minute weekly spot-check of AI decisions |
| ⚪ **One-time setup by you** | DB export + uploads copy · DNS switch at launch · Search Console · Razorpay KYC · AdSense ads.txt · GitHub deploy secrets · Meta (Facebook/Instagram) app connection |

**Why not 100% automatic from day 1:**
1. User-submitted content can break **AdSense policies**. One bad piece (adult, hate, copied) can get the AdSense account limited.
2. AI can misjudge Bengali literary nuance, satire or plagiarism, and **no tool reliably detects AI-written text**.
3. The ISSN credibility and writers' trust depend on real editorial judgement.
4. Sales, printing and money disputes involve people and contracts.

**Not available automatically:** posting to a **WhatsApp Channel** (no official API). The system prepares the message and image; someone taps "share" (about 1 minute).

---

## 5. Migration: copy all users, content and everything

### What you will provide (your plan: export users, published + pending posts, and the uploads folder)
1. **Full MySQL database export** from phpMyAdmin (Export → *Custom* → all tables → SQL). This one file contains **everything**: users, **published, pending, draft, scheduled and private posts**, comments, categories/tags, Yoast SEO data, co-authors and newsletter subscribers. Export every table, not only `wp_posts`.
2. **The uploads folder.** ⚠️ Please **do not overwrite the live `public_html`** yet, because WordPress is still running there. Put the copy in a separate folder (e.g. `/home/<user>/migration/uploads/`) or a staging subdomain (`new.hatpakha.com`). At launch it moves to `public_html/wp-content/uploads/` so all old image URLs keep working.
3. **Google Search Console owner access** and the **Hostinger plan name** (to confirm PHP version, SSH, cron and Git deploy).
4. **Security:** the database contains password hashes and personal data. **Never commit it to GitHub or paste it in chat.** Keep it on the Hostinger server or a private drive. The migration script reads it directly from MySQL.

### Staging approach
`new.hatpakha.com` (subdomain on the same Hostinger Cloud plan, with its own MySQL database) → import a copy of the WordPress DB → run the migration → review → final re-import of new posts → switch the main domain.

### What we move, and how
| Data | Method | Notes |
|---|---|---|
| Posts, pages (all statuses: published, **pending**, draft, scheduled) | WP MySQL → Laravel migration command → new MySQL tables | Keep date, author, status, category, featured image, **exact slug**. Convert HTML/shortcodes (tagDiv, Gutenberg) to clean rich text. |
| Users (authors, subscribers) | `wp_users` + `wp_usermeta` → new `users` + `profiles` | **Passwords keep working**: we import the WordPress password hash (phpass `$P$…` / WP 6.8+ bcrypt `$wp$…`), check it at first login, then re-hash. If that fails, a "set new password" email. |
| Co-authors / guest authors | Co-Authors Plus taxonomy → author profiles | |
| Media (3,199) | uploads folder kept at the same `/wp-content/uploads/` path; DB records imported | Generate WebP/AVIF sizes in the background; original URLs keep working. |
| Comments (397) | `wp_comments` → `comments` table | |
| Categories | 32 → about **10 clean sections** with 301s for merged ones | |
| Tags | 2,278 → controlled list of about **150**; others 301 to the parent section | AI helps map them. |
| Newsletter subscribers | CSV import | Re-consent email once. |
| SEO metadata | Yoast meta titles/descriptions per post | Carried over. AI fills the missing ones. |

### Content audit (do not migrate blindly)
AI-assisted pass over all 1,393 posts, each labelled **Keep / Improve / Merge / Noindex / Remove (410)**. For example, near-duplicate or spammy 2012-era posts and thin listicles get noindexed or removed. An editor confirms each batch.

### Zero-SEO-loss cutover
1. Crawl the old site and list **every URL** (sitemap + crawl + Search Console export).
2. The new site serves **identical URLs** for posts. Every other URL (tags, AMP `/amp/`, `?p=123`, date archives, `/feed/`) gets a **301 map**, tested automatically: 100% of old URLs must return 200 or 301 to the right page.
3. Freeze content on WordPress, do a final delta migration, switch DNS on Cloudflare, submit the new sitemap, and monitor Search Console daily for 4 weeks.
4. Keep the old WordPress offline but backed up for 6 months.

---

## 6. SEO plan

**Technical (built into the code, no plugins):**
- Server-rendered HTML, fast LCP, minimal JS. One correct `sitemap.xml` index (posts, authors, issues, sections), clean robots.txt.
- Structured data: `Article`/`CreativeWork` (with `genre: Poetry/Short story`), `Person` for authors, `BreadcrumbList`, `Periodical` + `PublicationIssue` with **ISSN 2321-3035**, `WebSite` + SearchAction.
- `lang="bn"` / `lang="en"` per article, `hreflang` where translations exist. All posts live at `/{slug}/` as on WordPress: old Bengali slugs are kept exactly, and new slugs are transliterated to Latin (`/kon-jon-tumi/`).
- **Full binding checklist: [`docs/SEO.md`](docs/SEO.md)** (URL policy, meta, JSON-LD, indexing rules, sitemaps, IndexNow, Core Web Vitals budget, launch monitoring).
- Open Graph / WhatsApp previews with auto-generated share cards (title + author over the issue artwork).
- Noindex thin archives; canonical tags everywhere; RSS feeds per section and author.

**Content & authority:**
- Publish consistently (at least 3 pieces/week). Freshness signals will bring crawling back.
- Author pages with real bios (E-E-A-T). An "About / Editorial board / Submission policy / ISSN" page.
- Topic hubs: "Bengali love poems", "Puja stories", "Tagore", "travel in Bengal". These are curated, evergreen collections.
- Writers share their own pages, so we get natural backlinks and social traffic. Add a "Published in Hatpakha" badge writers can embed.
- Approach Bengali literature groups, college magazines and little magazines for collaboration and backlinks.

---

## 6b. Security (summary; full binding checklist: [`docs/SECURITY.md`](docs/SECURITY.md))
- HTTPS + HSTS, strict security headers (nonce CSP), secure cookies.
- Legacy WordPress passwords verified once, then re-hashed; Google login and magic links; **mandatory 2FA for editors and admins**; rate limits on login, reset, register and submit.
- Policies for every model; the admin panel only for editors and above; **AI never takes actions**, only advises.
- Rich text sanitised with an allowlist; Turnstile + honeypot against spam; submissions treated as untrusted input to the AI (prompt injection).
- Uploads re-encoded; **no PHP can execute in upload folders**; the old uploads folder is scanned and suspicious files quarantined during migration; old WordPress attack paths return 410.
- Secrets only in GitHub Environments; least-privilege DB users; `composer audit` / `npm audit` in CI.
- Audit log of editorial actions, daily backups with a quarterly restore test, DPDP-compliant privacy (consent, delete and export my data).

---

## 7. Editorial operations (magazine editor view)

- **Rhythm**: articles go live on the website **every week** (at least 3 per week). The weekly newsletter goes out on Sunday. A **monthly issue** collects the best of the month as a flipbook, PDF and EPUB.
- **Issue calendar: 12 issues a year = 6 festival specials + 6 regular monthly issues.** The 6 festivals are not evenly spaced (March–May has three, June–September has none), so a fixed "every 2 months" schedule doesn't fit them. Festival issues are released **on the festival day**; regular issues on the **1st of the month**.

| Month | Issue | Type | Release | Notes |
|---|---|---|---|---|
| January | **বইমেলা সংখ্যা** | Festival | Boi Mela opening day | Launch the book-publishing service and Boi Mela meet-up |
| February | **ভাষা সংখ্যা** (২১ ফেব্রুয়ারি) | Regular | 1 Feb | International Mother Language Day; also Saraswati Puja |
| March | **বসন্ত সংখ্যা** | Festival | Dol Purnima | Love poems, colours |
| April | **নববর্ষ সংখ্যা** | Festival | ১ বৈশাখ | Haalkhata; new-year stories |
| May | **রবীন্দ্র-নজরুল সংখ্যা** | Festival | ২৫ বৈশাখ | Covers Tagore and Nazrul Jayanti; **Sharodiya contest opens** |
| June | **গ্রীষ্মের ছুটি / আম-কাঁঠাল সংখ্যা** | Regular | 1 June | Light, humour, children's writing |
| July | **বর্ষা সংখ্যা** | Regular | 1 July | Monsoon poems, a very Bengali theme |
| August | **শ্রাবণ সংখ্যা** | Regular | 1 Aug | ২২ শ্রাবণ remembrance; travel writing; **Sharodiya contest closes** |
| Sept / Oct | **শারদীয়া (পূজাবার্ষিকী)** | Festival · **flagship** | Mahalaya | Biggest issue: contest winners, sponsors, **print-on-demand copies** |
| Oct / Nov | **দীপাবলি সংখ্যা** | Festival | Kali Puja | Ghost and thriller stories |
| November | **হেমন্ত সংখ্যা** | Regular | 1 Nov (or merged with Dipaboli when they fall in the same month) | Nabanna, harvest |
| December | **বর্ষসেরা সংখ্যা** | Regular | 15 Dec | Best of the year chosen by readers' votes + editors; awards |

- **Festival specials:** gold-foil folk-art cover, the full e-book, sponsors and a big social push.
- **Regular monthly issues:** use a simpler **standard monthly cover template** (theme photo or illustration + title), so they take little design work.
- **Workload with AI:** about 2–3 hours per regular issue and 1–2 days per festival special (selection, editor's note, cover text). Everything else (layout, PDF, EPUB, flipbook, share images, newsletter) is automatic.
- **If the team is small in year 1:** publish only the 6 festival issues **plus বর্ষা** (7 issues), to fill the June–September gap, and add the other regular issues in year 2. Weekly web publishing and the Sunday newsletter continue either way.

### Which issue does a piece go into? (backlog + new submissions)
**Key idea: a piece does not wait for its issue.** It goes live on the website as soon as it is approved, in the weekly schedule. The monthly issue is a **collection** that includes it later in the flipbook, PDF and EPUB. So nobody waits months just because of the calendar.

**Assignment rules** (AI suggests, editor confirms in the *Issue planner* board):
1. **Season or festival match first.** AI reads each piece and tags its themes, which point to an issue:

   | Themes in the piece | Issue |
   |---|---|
   | books, reading, Boi Mela | বইমেলা |
   | Ekushe, mother tongue, language | ভাষা |
   | love, spring, colours, Dol | বসন্ত |
   | new beginnings, Boishakh | নববর্ষ |
   | Tagore, Nazrul | রবীন্দ্র-নজরুল |
   | summer, holidays, children, humour | গ্রীষ্ম |
   | rain, monsoon | বর্ষা |
   | travel, mountains, Srabon | শ্রাবণ |
   | Durga Puja, autumn, kash flowers, homecoming | শারদীয়া |
   | ghost, mystery, lamps | দীপাবলি |
   | harvest, village, fog | হেমন্ত |
   | top-rated of the year | বর্ষসেরা |

2. **Evergreen pieces** (no season) go to the **next issue that still has room in their section**, **oldest first** (first come, first served).
3. **Section balance per issue.** Typical target: about 12 poems, 5 stories, 2 travel pieces, 2 essays and 1 humour piece. Festival specials get more.
4. **Fairness:** at most 1 poem + 1 prose piece per author in an issue, and new writers are prioritised in every issue.
5. **Waiting limit:** a writer gets a **decision within 7 days**. If the matching festival issue is more than 3 months away, the piece is **published on the web now** and still placed in that issue later.

### Real backlog (from the DB audit, see `docs/DB-AUDIT.md`)
**424 pending posts from 221 authors.** But **392 are from 2016–2019**, only **28 from 2022–2026**, and 125 belong to one account (repeated titles from 2016). So they need **different handling by age**:

| Tier | Posts | Action |
|---|---:|---|
| **A: recent (2022–2026)** | 28 | Normal review now; the best go into the relaunch issue "ফিরে এলাম". Apology email to each author. |
| **B: cleanup** | ~45 + the 125-post account reviewed as one batch | Very short or empty (30), duplicates (13), test-like posts. Closed without publishing (archived, not deleted). |
| **C: old but possibly good (2016–2019)** | ~230 after cleanup | AI triage scores them all. The **top ~60–80** authors get an email: *"In 2016 you sent us 'X'. We'd love to publish it now. Is it still unpublished, and may we?"* Only pieces with a **yes** are published, as a series "**অপ্রকাশিত পাণ্ডুলিপি**" (unpublished manuscripts) and in the "আর্কাইভ থেকে" slot of each issue. The rest are closed politely. |
| **Drafts** | 53 | Never submitted. They appear in each author's "My writings" dashboard after migration, so the author can finish and submit them. |

### Clearing the existing backlog at relaunch
| Step | What happens |
|---|---|
| 1. Export | Pending/draft posts come over from the DB with their **original submission date** (this keeps the queue order fair). |
| 2. AI triage (one run) | For every pending piece: quality score, section, themes, suggested issue, and possible duplicate (already published on Hatpakha or elsewhere on the web). |
| 3. Apology + check email | Every waiting author gets a personal email: *"Sorry for the long wait. Hatpakha is coming back. Your piece is accepted / needs a small change / not selected this time."* Accepted authors are asked to confirm the piece is still unpublished elsewhere and to accept the new terms (one click). |
| 4. Relaunch issue **"ফিরে এলাম"** | The **first new issue** is built mostly from the **best of the backlog**. The theme "coming back" also fits the Sharodiya contest theme "ফিরে আসা". |
| 5. Spread the rest | The remaining accepted pieces go to the next 2–3 issues by the rules above, and on the web at 3–5 per week. That restarts fresh content for Google. |
| 6. Old published posts | They stay in the archive at the same URLs. They are resurfaced through mood picks, topic hubs, the "আর্কাইভ থেকে" (from the archive) slot (1–2 classic pieces per issue) and yearly anthologies. They are **not** re-issued as new. |
- **Sections** (about 10): Kobita · Golpo · Anugolpo · Probondho · Rommo Rachana · Bhraman · Onubad · Shishu-Kishor · Interview · Editorial.
- **Submission guidelines** page with word limits, originality rules, response time (7 days, which the AI pipeline makes realistic).
- **Editorial board**: 1 editor-in-chief + 2–3 volunteer section editors at first. AI reports cut each review to about 3 minutes.
- **Feedback culture**: every rejected writer gets kind, specific feedback. This is what makes writers come back, and it's what the AI report enables.

---

## 8. Roadmap

| Phase | Weeks | Deliverables |
|---|---|---|
| **0. Access & audit** | 1 | DB dump, uploads, Search Console; full URL inventory; content-audit labels |
| **1. Foundation** | 2–4 | Laravel + Filament scaffold, MySQL data model (posts, authors, sections, issues, submissions), design system, reading page, home, author pages |
| **2. Migration** | 4–6 | Migration scripts for users/posts/media/comments, redirect map + automated redirect tests, staging site with full archive |
| **3. Submissions + AI review** | 6–8 | Submit flow, status tracker, Claude review pipeline, editor dashboard, email notifications |
| **4. Launch** | 9 | Cutover, Search Console monitoring, first new monthly issue |
| **5. Issues & money** | 10–14 | Issue generator (web issue + print PDF + EPUB + flipbook + share images), festival cover templates, newsletter, contest module, payment gateway when approved |
| **6. Growth** | ongoing | Sharodiya issue, anthologies, workshops, audio recitations (writers record their own), translations |

---

## 9. Risks & mitigations
| Risk | Mitigation |
|---|---|
| Traffic dips after migration | Identical URLs + tested 301 map + same metadata; launch in a quiet month (not Puja season) |
| Password import issues | Lazy re-hash, with password-reset email as fallback |
| AI makes a wrong call | Humans decide; logs; editable rubric; writer appeals |
| Plagiarised submissions | Archive similarity + web plagiarism API + originality declaration + takedown policy |
| Small team burnout | AI handles the first pass and paperwork; volunteers do section editing; a fixed monthly rhythm |
| Copyright of old works | Confirm contributor rights in new Terms; let authors claim and edit their legacy pieces |

---

## 10. Decisions

### Decided (Nov 2026)
| Topic | Decision |
|---|---|
| Hosting | Hostinger Cloud hosting, **PHP 8.4**, MySQL |
| Stack | **Laravel + Filament** (Blade + Tailwind + Alpine, Livewire where interactive) |
| Language | **Bengali-first.** Existing English posts are kept in a separate **English** section (`/english/`, `lang="en"`), which is also the higher-RPM commercial section |
| Staging | `new.hatpakha.com` |
| Deployment | **GitHub Actions → Hostinger** (SSH deploy on push to `main`; staging from a `staging` branch) |
| Publishing | Free for everyone; monetization per `MONETIZATION.md` |
| Ads | Google AdSense (existing account), manual placements only |

| Monetization phase 1 | **AdSense + contests.** Contests start **free to enter** (prizes: publication, featured badge, e-certificate, sponsor gifts). **Paid entries, paid feedback and tips** switch on once the payment gateway (Cashfree / Razorpay) account is approved. The code is gateway-agnostic. |
| Data hand-over | You copy the full DB export + uploads folder to the server (outside the live `public_html`) |
| Deploy | GitHub Actions → Hostinger over **SSH**, same approach as your other project |

### Secrets / `.env` policy
- `.env.example` (no real values) **is** committed.
- Real values (DB password, `APP_KEY`, Claude API key, payment-gateway keys, SMTP password, SSH key) are stored in **GitHub → Settings → Environments (`staging`, `production`) → Secrets**. The deploy workflow writes `.env` on the server at deploy time.
- **A real `.env` is never committed**, even in a private repo: git history keeps it forever, every collaborator and CI tool can read it, and leaked API or payment keys can be abused within minutes.

### Still open
1. The payment gateway: approval date, and which one (Cashfree or Razorpay).

No code until you approve. Once you confirm, the next step is **Phase 1**: scaffold the Laravel + Filament project in this repository.
