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

### Design language
A *hatpakha* is a hand fan, so use that as the motif: warm paper-cream background, deep ink text, one accent colour (terracotta/alpona red), generous white space, illustrated issue covers. Calm, literary and trustworthy.

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
4. Outputs: a **web issue page**, a **print-ready PDF** (Paged.js/Typst with proper Bengali shaping), and a **newsletter edition**.
5. The editor reviews and clicks "Publish issue". The issue gets `PublicationIssue` structured data with the ISSN.

---

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
- `lang="bn"` / `lang="en"` per article, `hreflang` where translations exist. New slugs transliterated to Latin (`/kobita/kon-jon-tumi`); old Bengali slugs kept as they are.
- Open Graph / WhatsApp previews with auto-generated share cards (title + author over the issue artwork).
- Noindex thin archives; canonical tags everywhere; RSS feeds per section and author.

**Content & authority:**
- Publish consistently (at least 3 pieces/week). Freshness signals will bring crawling back.
- Author pages with real bios (E-E-A-T). An "About / Editorial board / Submission policy / ISSN" page.
- Topic hubs: "Bengali love poems", "Puja stories", "Tagore", "travel in Bengal". These are curated, evergreen collections.
- Writers share their own pages, so we get natural backlinks and social traffic. Add a "Published in Hatpakha" badge writers can embed.
- Approach Bengali literature groups, college magazines and little magazines for collaboration and backlinks.

---

## 7. Editorial operations (magazine editor view)

- **Calendar**: monthly issue (1st of the month), weekly newsletter (Sunday), annual Sharodiya (Mahalaya), a Boimela special (January–February), Rabindra Jayanti / Nazrul Jayanti specials.
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
| **5. Issues & money** | 10–14 | Issue generator (web + PDF), newsletter, Razorpay memberships, contest module |
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

### Still open
1. Which monetization streams to switch on first (recommended: AdSense + newsletter at launch, contests + Razorpay in month 2, Sharodiya 2027 as the first big event).
2. Data hand-over: full DB export + uploads copy placed on the server outside the live `public_html`.
3. GitHub Actions secrets for deploy (SSH host/user/key) are added by you in GitHub → Settings → Secrets. **Never put them in the repository.**

No code until you approve. Once you confirm, the next step is **Phase 1**: scaffold the Laravel + Filament project in this repository.
