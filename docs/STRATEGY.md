# How Hatpakha wins: the editor's plan (Nov 2026)

*Builds on `PLAN.md` §1 (positioning, KPIs) and `MONETIZATION.md`. Written with the AI team; the owner decides.*

## 1. The field we play in

| Who | What they are good at | Their gap (our opening) |
|---|---|---|
| **Pratilipi** (Bengali among its biggest languages; millions of readers) | Anyone can publish instantly (self-publishing); huge free library, serial fiction, app; **writers can earn**: Golden Badge authors (200+ followers) lock premium chapters and are paid from the Premium subscription and Superfan subscriptions (owner correction, 2026-11-07) | No editor reads a piece before it goes up (no selection, no copy-editing), so quality is a lottery; earnings favour long serials with big followings, not poems or single stories; no ISSN or literary credential |
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

**Online only (owner, 2026-11-06):** we can't be anywhere in person for now, so every campaign runs and releases online: the site, the newsletter, the Facebook page, the WhatsApp channel, Instagram, online contests and e-books. No stalls, launches or print. The AI team's shared brief says the same.

| When | Goal | Owner (human) | AI team |
|---|---|---|---|
| **Weeks 1–2** | **Launch-ready**: SMTP, production `ENV_FILE`, cron, Cloudflare cache rules, final URL audit, backups | Owner | Editor-in-chief watches; analyst checks traffic after cutover |
| **Weeks 1–4** | **Clear the 424-piece backlog** in batches of 20 a day (accept / decline with a kind note) so every old writer gets an answer | Owner (+ 1–2 trusted volunteer editors with the editor role) | Editor + proofreader prepare each piece; copywriter's "we're back" letter to old writers |
| **Week 2** | **Relaunch letter** to all accounts and the Facebook page: "Hatpakha is back, here is what's new for writers" | Owner sends | Copywriter writes, marketing makes the posts and cards |
| **Weeks 3–8** | **Boimela 2027 campaign, online** (Kolkata 22 Jan – 3 Feb, its 50th year; Dhaka 7 Jan – 6 Feb): a "বইমেলা সংখ্যা", a contest ("বইমেলার গল্প"), daily posts during the fairs; nothing at the fair grounds | Owner approves issue and contest | Editor plans issue; contest manager drafts rules; designer makes poster and cover; marketing runs the calendar; trend scout tracks fair news |
| **Ongoing** | **Three pieces a week, one letter a week, one post a day** | Owner approves | Whole team (this is now automatic; see এআই দল → পরের কাজ) |
| **Month 3** | **Measure and adjust**: what readers read, where they come from, which sections grow | Owner reviews | Analyst's monthly insights; editor-in-chief's team review |

**Targets for 90 days:** every backlog writer answered; 40+ new pieces published; 1,000 confirmed newsletter readers; 20% of visitors read a second piece; a successful Boimela issue and contest.

## 3b. The relaunch: winning back trust (owner, 2026-11-07, D104)

**What the data says.** 1,393 published pieces; peaks in 2015–17 and 2020–21, then 11 pieces in 2022, none in 2023, 16 in 2024. 1,728 accounts and 912 writers (267 published in 2020–24; 145 have three or more pieces). 424 submissions, mostly sent in 2016–18, were never answered. People remember Hatpakha but think it has stopped. The relaunch must rebuild **trust**, not add features.

**What we do, in order**

1. **Own the silence.** The "we're back" letter says plainly that Hatpakha went quiet, apologises, and says what is different now. Revived magazines that did this kept their communities.
2. **Old submissions first, and only the good ones (owner).** None of the 424 is published as it is.
   - The AI editor reads each once and suggests "worth asking" or "too weak", with a score and a one-line reason for the owner only. The owner can change any of them (/desk → পুরনো লেখকদের চিঠি).
   - After the move to hatpakha.com, writers of "worth asking" pieces get **one** letter, sent automatically: an apology, the reason (Hatpakha was almost closed for some years, so nobody could read and answer), and the question "do you still want it published?". Writers whose pieces are all weak get no letter.
   - The letter's text needs the owner's approval once. Sending is 20 an hour, newest submissions first, so old dead addresses don't harm the site's mail reputation.
   - The writer answers per piece from a signed link: publish / don't publish / already published elsewhere. A "yes" puts the piece in "পুরনো: লেখক রাজি" for the owner to read and decide. A "no" or no answer means it is never published (the site refuses to publish an old submission without the writer's yes).
3. **Prove we're alive before inviting anyone.** 4–6 weeks of pieces scheduled before any announcement (three a week). The schedule must not slip in the first months.
4. **The core writers, personally.** The owner messages the 145 regular writers (WhatsApp or personal mail) and invites them to a **ফিরে আসা সংখ্যা**, the first issue after the return.
5. **The old list, gently.** The "we're back" letter to all accounts goes in small batches, recent writers (2020–24) first; bounced addresses are removed. Easy unsubscribe in every letter.
6. **Google: keep and watch.** Same domain and every old URL, so no change-of-address is needed. Verify Search Console, submit the sitemap on launch day, check "not found" weekly for a few months.
7. **WhatsApp Channel and the Facebook page.** WhatsApp Channels reach followers without an algorithm in between; revive the Facebook page with the same message.
8. **Protect the owner's time.** One or two trusted volunteer editors (editor role). A pace that can be kept: three pieces a week, not daily.

**Timeline**

| When | What |
|---|---|
| Now to mid-December | Launch blockers. AI reads the 424 old pieces; owner checks the list and approves the letter. Message the core writers. Build a 6-week schedule. |
| Late December | Move to hatpakha.com (quiet launch). The apology letters start by themselves. |
| Early January | "We're back" letter in batches, Facebook, WhatsApp Channel, the ফিরে আসা সংখ্যা. |
| Late January | Boimela issue and contest (section 3). |

Research behind this (2026-11-07): e-mail re-engagement guidance (Suped, Customer.io), relaunched magazines (The Writer 2025, Blydyn Square Review, The Northwest Review), Google's Change of Address help, Nieman Lab and Deccan Herald on WhatsApp Channels.

## 3c. New sections, written by the AI desk until writers come (proposal, 2026-11-08)

**Rule for every new section:** it must be something Hatpakha can do *better* than the big portals (voice, story, community), it must be safe for the AI desk to draft *now* (no invented people, no reviews of things the AI has not read, no medical or legal advice), and it must be able to pass to human writers later.

**What the research shows** (thin on hard numbers; treat as direction):
- Mystery, paranormal and true-crime are among the biggest Bengali YouTube genres (channels of 400-700k subscribers), and Sunday Suspense proves the appetite for ghost and detective stories. AI-written "true crime" has already been exposed as fabricated elsewhere, so real crimes are off limits.
- For Bengalis abroad, Durga Puja and food are identity anchors, and the second generation acts as "cultural translators" between two cultures. Readers abroad also pay the best ad rates.
- Book-fair recommendation lists and proverb/idiom books get regular press attention (Daily Star's Boi Mela picks, "Curious reads").
- Google: AI writing is allowed when it adds value; mass-producing pages to rank is "scaled content abuse" and is being enforced. So: few, edited, original pieces, never a flood.

**Proposed sections**

| Phase | Section | What it is | Why it is worth it | Why the AI desk can draft it now |
|---|---|---|---|---|
| 1 | **ফিরে পড়া, ফিরে দেখা** (literature group) | Essays on classic Bengali books and films: Bibhutibhushan, Tagore, Ray, Ritwik, Uttam-Suchitra | Searchable (people look up these titles), shareable, fits a literary magazine exactly | The AI knows the classics well; facts go to the check list. New releases are human-only (the AI has not read them) |
| 1 | **জনপদের গল্প** (life group) | One town, village, street or river of Bengal per piece: its past, its people, its smell and sound | Each piece is shared in that place's own WhatsApp and Facebook groups; place names are searched; nobody does this with a writer's voice | Literary essay rules (D106) plus the fact-check list; never a drawing of a real monument |
| 1 | **রহস্যময় বাংলা** (literature group) | Legends, ghost lore and folk tales of Bengal's places, retold as stories | The biggest Bengali video genre, in reading form; pairs with the অলৌকিক serial | Folklore and public-domain tales; clearly told as legend, never as real crime |
| 2 | **প্রবাসের কথা** (life group) | Bengali life abroad: Puja in a rented hall, a child answering in English, the first winter | Unique, matches ছোটদের হাতপাখা, readers abroad earn the most | AI drafts only general essays (no invented "I"); real stories invited from readers abroad first |
| 2 | **শব্দের গল্প** (literature group) | Where a Bengali word, proverb or idiom comes from, and how it is used | Language lovers, students, parents abroad; short and shareable | Only with dictionary sources in the check list; doubtful origins stay vague |
| 2 | **মাঠের গল্প** (life group) | Football and cricket memory: the derby, the radio commentary, the para field | A Bengali passion; strong sharing | History essays with fact check; no current sports news |
| Later, humans only | নতুন বইয়ের আলোচনা, মা হওয়ার গল্প | New book reviews; mothers' own writing | Real readers' voices | **Not AI**: a review needs someone who read the book, a memoir needs the person who lived it |

**How the AI desk works for these** (same as ঐতিহ্য and রান্নাঘর, D95-D107):
- Drafts only, under "হাতপাখা ডেস্ক", with a plan, facts to check, a cover proposal; nothing is published without the owner.
- **Slow and steady:** at most one new piece per section per week (Phase 1: about 3 a week in total, on top of the serials). Google rewards consistency, not volume.
- Each section page always says "এই বিভাগে লিখুন", and every new writer in a section is answered first. Goal: by month 3, at least one human piece per section per month.
- After 8 weeks, keep the sections readers read and share, merge or close the rest (owner decides).

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

## 6. The old English filler posts: options for the owner (2026-11-06)

What is there: **365 published English pieces** (362 in the English section, 3 in শের-ও-শায়েরি), **130 of them under the desk byline**. Many are generic web filler from the last WordPress years ("Top 10 Surprising Parenting Tips", "10 Essential Python Concepts", Netflix, K-pop, Paralympics, ChatGPT citation, birthday wishes, Meta). No reads recorded since the counter started. They weaken the promise ("a careful home for Bengali writing") and Google judges a site by its weakest pages.

Nothing is deleted or unpublished without the owner's say (CLAUDE.md §3). Options, best first:

1. **Sort into three piles** (the archivist can propose the sorting, the owner ticks):
   - **Keep**: real English literature and culture by named writers (poems, essays on Tagore, translations, travel). They stay in the English section, which becomes "Hatpakha in English": Bengali culture for readers who read English.
   - **Hide from Google**: thin but harmless pieces stay reachable at their URLs but get `noindex` and leave the home page, lists and sitemap.
   - **Retire**: off-topic filler (tech how-tos, celebrity news, wishes lists) is unpublished and its URL 301s to `/category/english/` (or gives 410 Gone if the owner prefers). Old links never break (rule 1).
2. **Rewrite a few into Bengali** where the subject fits us (e.g. Tagore, festivals, travel): a new Bengali piece, with the old URL redirected to it.
3. **Leave everything as is** (not recommended): costs nothing now, but keeps diluting search quality and the brand.

Recommendation: option 1. If the owner agrees, the next step is a reviewable list in /desk (the content-audit tool already supports "keep / noindex / retire" labels with editor confirmation).
