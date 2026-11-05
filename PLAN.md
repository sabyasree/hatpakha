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

### Business model (in order of how easy each one is)
1. **Sharodiya / Puja annual digital issue + print-on-demand anthology.** Bengal already has this tradition (the old "Puja 2020" category shows it worked before). Sponsors and pre-orders.
2. **Writing contests** with a small entry fee (₹100–200), winners published in the issue, certificates.
3. **Writer membership** (₹99–199/month): priority review, detailed AI + editor feedback, author page badge, early anthology access.
4. **Contributor book publishing**: compile a writer's Hatpakha pieces into an ISBN e-book or print book (service fee).
5. **Reader membership / tips** (optional, later), newsletter sponsorship, a few tasteful ads (no ad clutter on reading pages).
6. **Workshops** (online, paid): poetry, story craft, translation.

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

## 2. Technology choice

### Recommendation: **Next.js + Payload CMS + PostgreSQL**, with Claude for AI

| Layer | Choice | Why |
|---|---|---|
| CMS + admin | **Payload CMS 3** (open source, runs *inside* Next.js) | Real admin panel, built-in auth and roles, drafts and version history, scheduled publishing, field-level access control, hooks where AI review plugs in. No plugins to maintain. You own the code and data. |
| Frontend | **Next.js** (App Router, server components, ISR) | Static-fast pages, perfect SEO control, image optimization, one codebase with the CMS. |
| Database | **PostgreSQL** (Neon or Supabase managed) | Reliable, cheap, good full-text search; `pgvector` for "related stories" and plagiarism similarity. |
| Media | **Cloudflare R2** + Cloudflare Images/CDN | You already use Cloudflare. No egress fees. The 3,199 images move here. |
| Hosting | **Vercel** (Pro) *or* a single VPS with Docker/Coolify (budget option) | Vercel means no server work. A VPS costs about ₹600–1,000/month but you have to maintain it. |
| Search | Postgres full-text first; Meilisearch later if needed | Bengali-aware search. |
| Email / newsletter | **Resend** (transactional) + **Listmonk** or Buttondown (newsletter) | Replaces MailPoet + MC4WP. |
| Payments | **Razorpay** (India) + Stripe (diaspora) | Memberships, contest fees, anthology orders. |
| AI | **Claude API** (Anthropic): Sonnet for review, Haiku for bulk tagging and SEO metadata | Strong at Bengali and English, follows editorial rubrics, structured JSON output. |
| Analytics | Google Search Console + Plausible/Umami (privacy-friendly) | |

### Alternatives I considered and rejected
- **Stay on WordPress, just clean it up.** Cheapest short-term, but the AI workflow and magazine-issue generator would need yet more plugins. That's the same problem you have now.
- **Ghost.** Excellent for SEO and newsletters, but weak for public contributor submissions, custom review workflows and issue layouts.
- **Sanity / Contentful (hosted headless).** Good, but monthly costs grow per user/seat, and your data sits on someone else's platform.
- **Pure static (Astro + Markdown).** Fast, but can't handle user accounts, submissions or memberships.

**Rough running cost:** ₹2,500–5,000/month (hosting + database + email + AI for about 400 submissions). Expect AI cost per reviewed piece to be well under ₹5.

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

### Design language
A *hatpakha* is a hand fan, so use that as the motif: warm paper-cream background, deep ink text, one accent colour (terracotta/alpona red), generous white space, illustrated issue covers. Calm, literary and trustworthy.

---

## 4. AI editorial workflow (the "AI checks posts" part)

**Rule: AI assists, humans decide.** AI never publishes on its own in the first 6 months. After that, *Trusted Writers* with a high score can be fast-tracked to "scheduled", and an editor can still veto.

```
Submission
   │
   ├─▶ 1. Safety & policy check     (hate, sexual content, defamation, spam links)   → auto-reject + reason
   ├─▶ 2. Originality check         (embeddings vs. our 1,393-post archive +
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

## 5. Migration: copy all users, content and everything

### What I need from you
1. **Hostinger hPanel access**, or: a **full database dump** (phpMyAdmin → Export → SQL) and a **zip of `wp-content/uploads`**.
2. A WordPress admin account (for WXR export and checks).
3. Google Search Console access (owner).
4. MailPoet / Mailchimp subscriber exports, and WooCommerce orders if any matter.

### What we move, and how
| Data | Method | Notes |
|---|---|---|
| Posts, pages | DB dump → migration script → Payload | Keep date, author, status, category, featured image, **exact slug**. Convert HTML/shortcodes (tagDiv, Gutenberg) to clean rich text. |
| Users (authors, subscribers) | `wp_users` + `wp_usermeta` → Payload users | **Passwords keep working**: we import the WordPress password hash (phpass `$P$…` / WP 6.8+ bcrypt `$wp$…`), check it at first login, then re-hash. If that fails, a "set new password" email. |
| Co-authors / guest authors | Co-Authors Plus taxonomy → author profiles | |
| Media (3,199) | uploads zip → R2, rewrite URLs in content | Also generate modern WebP/AVIF sizes. |
| Comments (397) | `wp_comments` → comments collection | |
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
| **1. Foundation** | 2–4 | Next.js + Payload scaffold, data model (posts, authors, sections, issues, submissions), design system, reading page, home, author pages |
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

## 10. Decisions needed from you
1. **Hosting:** Vercel (easiest) or a VPS (cheapest)?
2. **Language focus:** Bengali-first with English secondary (recommended), or fully bilingual?
3. **The 2024 English listicles:** keep, rewrite or noindex? (Recommended: noindex or remove most of them.)
4. **Monetisation to launch first:** Sharodiya anthology + contests (recommended), or memberships?
5. **Access:** please share the DB dump + uploads (or Hostinger access) so migration can start.

Once you confirm, the next step is **Phase 1**: scaffold the Next.js + Payload project in this repository.
