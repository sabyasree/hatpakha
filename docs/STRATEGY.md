# How Hatpakha wins: the editor's plan (Nov 2026)

*Builds on `PLAN.md` §1 (positioning, KPIs) and `MONETIZATION.md`. Written with the AI team; the owner decides.*

## 1. The field we play in

| Who | What they are good at | Their gap (our opening) |
|---|---|---|
| **Pratilipi** (Bengali among its biggest languages; millions of readers) | Huge free library, serial fiction, writer community, app | No editing, no curation, quality is a lottery; a reader complained of finding mostly Sarat Chandra and Tagore, little new literature; no ISSN prestige |
| **Old webzines**: পরবাস (since 1997), কৌরব (poetry, since 1998), কল্পবিশ্ব (sci-fi) | Loyal readers, a clear identity, consistent quality | Slow, irregular, desktop-era design, little help for new writers, small social reach |
| **Facebook groups and pages** (where many Bengali writers now publish) | Instant readers and comments; direct reader gratitude (the "পাঠক সম্মানী / Path Pronami" model in Bangladesh) | Noise, no editing, posts vanish in a week, no archive, no credential |
| **Print annuals and little magazines** (শারদীয়া, বইমেলা লিটল ম্যাগ) | Prestige, festival rituals | Expensive and slow; reach ends with the print run |
| **Audio apps** (audiobooks and Bengali audio series are growing) | Listening on the go | Mostly old classics or paid series; new writers rarely get in |

Sources: ThePrint and Quartz on Pratilipi; Parabaas and Wikipedia pages for Kaurab and Kalpabiswa; Rupkatha Journal 2026 on born-digital Bengali writing; The Business Standard on Path Pronami; market reports on Bengali audio. Numbers vary between sources, so treat them as directions, not facts.

## 2. Why people will come to Hatpakha (our promise)

**For writers: "Every piece is read by an editor, and you hear back within a week."**
1. **Edited, not just posted.** An editor reads every piece; small copy-edits are offered and the writer agrees to any word change. No other free platform does this.
2. **A fast, kind answer** with a reason, even when the answer is "not this time". The status tracker shows where the piece is.
3. **A real credential.** An ISSN-registered magazine since 2012, a verifiable certificate for each published piece, a proper author page with photo, cover and bio.
4. **Readers who come back.** Monthly issues, festival issues, newsletter, WhatsApp/Facebook promotion of every piece, "read next".
5. **Contests and anthologies** that end in something you can show (certificate, the yearly anthology, Sharodiya).

**For readers: "A few minutes, one good piece, every day."**
1. **Curated, not a flood.** Three new pieces a week (Mon/Wed/Fri), an editor's pick, monthly themed issues.
2. **Beautiful on the phone**, fast, no clutter, no ads inside the text.
3. **Fourteen years of archive** brought back: "এই দিনে", themed collections, the yearly anthology.
4. **The calendar of Bengali life**: Boimela, ২১ ফেব্রুয়ারি, নববর্ষ, ২৫ বৈশাখ, বর্ষা, শারদীয়া, হেমন্ত… each with a special issue.
5. **Both Bengals**: West Bengal and Bangladesh writers and spellings, side by side.

**Our edge in one line:** *the care of a little magazine, the speed of the internet, the reach of social media.*
No competitor combines editing, speed, prestige and reach.

## 3. The next 90 days (who does what)

| When | Goal | Owner (human) | AI team |
|---|---|---|---|
| **Weeks 1–2** | **Launch-ready**: SMTP, production `ENV_FILE`, cron, Cloudflare cache rules, final URL audit, backups | Owner | Editor-in-chief watches; analyst checks traffic after cutover |
| **Weeks 1–4** | **Clear the 424-piece backlog** in batches of 20 a day (accept / decline with a kind note) so every old writer gets an answer | Owner (+ 1–2 trusted volunteer editors with the editor role) | Editor + proofreader prepare each piece; copywriter's "we're back" letter to old writers |
| **Week 2** | **Relaunch letter** to all accounts and the Facebook page: "Hatpakha is back, here is what's new for writers" | Owner sends | Copywriter writes, marketing makes the posts and cards |
| **Weeks 3–8** | **Boimela 2027 campaign** (Kolkata 22 Jan – 3 Feb, its 50th year; Dhaka 7 Jan – 6 Feb): a "বইমেলা সংখ্যা", a contest ("বইমেলার গল্প"), daily posts during the fairs | Owner approves issue and contest | Editor plans issue; contest manager drafts rules; designer makes poster and cover; marketing runs the calendar; trend scout tracks fair news |
| **Ongoing** | **Three pieces a week, one letter a week, one post a day** | Owner approves | Whole team (this is now automatic; see এআই দল → পরের কাজ) |
| **Month 3** | **Measure and adjust**: what readers read, where they come from, which sections grow | Owner reviews | Analyst's monthly insights; editor-in-chief's team review |

**Targets for 90 days:** every backlog writer answered; 40+ new pieces published; 1,000 confirmed newsletter readers; 20% of visitors read a second piece; a successful Boimela issue and contest.

## 4. Is everything ready to run the magazine? (checklist)

**Ready now**: submissions with editing, review, decisions and notes; issues and editorials; publishing slots; comments with AI pre-screening; covers (writer upload or AI); SEO; newsletter (sign-up, letters to readers and writers); marketing posts and cards; contests; certificates; archive and collections; reader analytics; AI team with plan and monitoring; accounts with verification and optional 2FA; Bengali/English desk.

**Must do before launch (blockers)**

| # | Item | Who |
|---|---|---|
| 1 | Real e-mail (SMTP) in production `ENV_FILE`; set `MAIL_BATCH` to your plan's hourly limit | Owner |
| 2 | Production cron line (same as staging, production folder) | Owner |
| 3 | Cloudflare cache rules for production (guest pages cached; `/desk`, `/account`, `/submit`, `/login`, `/v/` bypassed) | Owner + Claude |
| 4 | Final URL audit with a Search Console export | Owner exports, Claude runs `hatpakha:url-audit` |
| 5 | Backups: daily database + `public/media` copy off the server (Hostinger backups + one off-site copy) | Owner |
| 6 | Decide the English filler posts from the old site (noindex or remove) | Owner |
| 7 | A native Bengali copy editor reads all site and desk text once | Owner |

**Missing features worth building next (in this order)**

| # | Feature | Why it matters |
|---|---|---|
| 1 | **Site search** (title, writer, tag) | Readers and writers look for their own and favourite pieces; it's the most-used feature on any archive |
| 2 | **Serial fiction (ধারাবাহিক)**: parts linked as one series, "next part" | The format readers binge on Pratilipi; brings readers back weekly |
| 3 | **Reader reactions and a reading list** ("ভালো লেগেছে", save for later) | A signal for writers and the analyst; a reason for readers to have an account |
| 4 | **Follow a writer**: a short mail when they publish | Builds each writer's own audience on our site, not on Facebook |
| 5 | **Audio**: writers record their own reading (upload), played on the page | Audio is the fastest-growing format; a human voice beats AI voices for literature |
| 6 | **Reader gratitude to writers** (optional tip to the writer, Path Pronami style) once a payment gateway is approved | Lets readers thank writers; we take nothing or a small fee (`MONETIZATION.md`) |
| 7 | **Issue e-book / PDF** and the yearly anthology file | Something writers can share and keep; print-on-demand later |
| 8 | **Search Console data** for the SEO expert and analyst | Better refresh choices than our own counter alone |
| 9 | **Automatic Facebook/WhatsApp posting** (owner turns on) | Saves the daily copy-and-paste |

**People, not features (the real risk)**

- **The owner is the only decision-maker.** 424 old pieces plus new ones will bottleneck at one person. Add 1–2 trusted volunteer section editors (the editor role already exists, with 2FA) and keep final say on issues and editorials.
- **A human voice in public.** The AI drafts; the owner's editorial, replies to writers and festival letters should sound like a person. Edit before sending.
- **Consistency beats bursts.** Three pieces a week, every week, is better than thirty in a day.

## 5. How the AI team is set up for this (D62)

Every writing job now starts from one shared brief (`app/Ai/Knowledge.php`): who Hatpakha is, the Bengali literary tradition, forms, the cultural calendar, what readers and writers want, and our voice. The **trend scout** searches each Monday for Bengali literary and cultural news with sources, and marketing, letters, issue planning and collections take it into account. The brief is context, not facts to quote, and the owner can edit it in one file. When this plan changes, the brief should change with it.
