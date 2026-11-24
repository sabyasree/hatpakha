# What is missing, and why people would choose Hatpakha (research, 24 Nov 2026)

Owner's question: "find and list if anything I am missing … how to make this magazine more beautiful, feature-rich and
very very user friendly … how people will come here instead of other magazines, though I am not paying anything."

Five reviews ran in parallel: readers' experience and look (with phone and desktop screenshots), writers' experience,
other Bengali and world literary magazines (web research), growth and community, and a check of every public page.
Nothing below is built yet. Items marked **ask** need the owner's decision under CLAUDE.md §3 or an earlier decision.

## The one-line answer

Hatpakha cannot win on money. It can win on what nobody else in Bengali combines: **an editor who reads every piece and
answers fast and kindly**, **a stage and a credential writers can show**, **learning**, and **company for readers**.
Parabaas can be silent for six months and forbids reposting; Pratilipi and Facebook have no editor; prize magazines are
slow and few. Keep that promise visible, and add the stage, the lesson, the CV line and the company.

## A. Broken or missing now (small, do first)

1. Privacy, Terms, Contact and About work on live and staging (checked). A test now makes sure their texts ship with the code.
2. **No list of all writers** (`/lekhok/` or similar): ~1,700 writers since 2012 and no way to browse them; search is the only way.
3. **No text size or night reading** on a piece: older readers, long Bengali prose.
4. **Search loads every writer each time** (server load on the shared host); match names in the database or cache them.
5. **Piece page ends too long**: comments shown twice, two follow blocks (WhatsApp and e-mail), "পরের লেখা" far down.
6. **পাঠকের কলম**: the form sits ~2,500px down under last week's picks; a "লিখুন ↓" button under the title.
7. **Cards without a picture** show a big letter on peach; use the fan title card (house style) instead.
8. **Empty pages are dead ends** (no issue yet, serial starting): always offer something to read and a way to follow.
9. **Footer is thin**: add three columns (পড়ুন / লিখুন / হাতপাখা) with sections, how to send, contact, games, privacy.
10. **Small tap targets** (privacy link, section pills, byline link): pad to 44px; Bengali meta text at least 16px.
11. **Fonts**: ~834 KB on first visit; subset Anek Bangla to the weights used (saves ~300 KB on phones).
12. Owner's own pending setup (PROGRESS.md): Amazon SES DNS and tracking off, Google sign-in client, phone-notice keys,
    serial proposals waiting, UPI ID, the ফিরে আসা cover and editorial.

## B. For writers (they choose us without pay)

1. **Show the editor's letter on a declined piece** in the writer's account, with "what next" (another section, this
   week's theme, send a revised version after 30 days). A kind, visible no is our biggest edge.
2. **Keep the reply promise and prove it**: STRATEGY says "within a week", the site says "usually 14 days" (**ask**:
   which?). Then show a live figure on the rules page: "গত ৯০ দিনে গড়ে X দিনে উত্তর".
3. **A real "the editor opened it" step** in the writer's tracker, with dates.
4. **লেখার ডাক**: a public calls page: coming issue themes with deadlines, festival specials, contests, the editor's wish list.
5. **"কেন বেছেছি"**: two lines from the editor under each chosen piece (the AI drafts in the accept dialog, the owner edits).
6. **নতুন কলম**: one debut a week, labelled, with a first-piece certificate and a phone notice.
7. **Writer's portfolio**: pin three pieces on the author page; a one-page "লেখক-পরিচিতি" PDF with ISSN pieces,
   certificates and honours, and a QR check (reuses the certificate design).
8. **A monthly "আপনার লেখার মাস" e-mail** per writer: reads, followers, one reader reaction, the next call
   (**ask**: new automatic mail; fold into a notification budget, see D).
9. **Reader words back to the writer**: reactions per piece; the writer may show one comment on their page (respect opt-out).
10. **Clear policy**: one waiting piece at a time or not; may a piece go elsewhere while it waits (withdraw exists).
11. **Opt-in craft notes** on a decline, drawn from the AI report, sent only after the owner reads them (fits D42).
12. **Writer anniversaries**: "৫ বছর আগে আজ…" once a year, with the piece and "নতুন লেখা পাঠান" (**ask**: new automatic mail).

## C. For readers (beautiful, easy, a reason to come back)

1. **Home on a phone**: the poster fills the first screen; for returning readers a slim band, then today's pieces and
   "যেখানে থেমেছিলেন".
2. **Reading controls**: text size (3 steps) and a night theme (navy page, paper text: inside the palette rule).
3. **Desktop margins**: a quiet side rail with share, পরে পড়ব, text size and progress.
4. **Shelves by mood and length**: "৫ মিনিটের গল্প", "মন ভালো করা", "যে লেখা পাঠকের মন ছুঁয়েছে" from the feelings data.
5. **Search filters**: section, length, newest/oldest.
6. **Saved lines**: lines a reader shares or marks, kept in their account.
7. **Bottom bar** (**ask**, D79/D80): the raised red "লিখুন" holds the best slot; most visitors are readers. Option:
   "এই মাসের সংখ্যা" in the middle, লিখুন inside আরও.
8. **Desktop top menu** should always show সংখ্যা and ধারাবাহিক.
9. **Sign-up**: drop the second password box (দেখান exists), Google first, a gentler password rule (**ask**: security).

## D. Bigger ideas to bring people back (from the market and growth research)

Ranked by impact for effort. S = small build, M = medium, L = large.

1. **হাতপাখা আড্ডা** (S build, ~2 owner hours a month): a monthly live evening on Facebook/YouTube; 3–4 writers read,
   readers comment; the recording becomes audio. A stage and company: what Facebook groups give and we lack.
2. **"এই সপ্তাহের গল্প, পরিচয় করালেন …"** (S): a known Bengali writer introduces one Hatpakha piece in two paragraphs
   (Electric Literature's model). Credibility for the new writer, a visible role for the known one. Costs only asking.
3. **বর্ষসেরা "নতুন কলম ১০"** (S): the year's ten best new writers, with a citation each, announced with the বর্ষসেরা issue.
   Lists and prizes become news (Granta's lists, Kali o Kalam's young-writer award, covered yearly by the Daily Star).
4. **বইমেলার অনলাইন স্টল** (M): January–February, writers list their own printed books (cover, blurb, stall number in
   Kolkata or Dhaka, buy link) free; one featured a day. The strongest pull of the Boimela season.
5. **শ্রুতি-হাতপাখা** (S–M): a weekly podcast feed from the writers' own readings plus volunteer narrators; new writers
   are absent from Bengali audio (Sunday Suspense reads classics).
6. **পাঠকের কলম → লেখক** (S): a reader picked three times is invited to send a full piece with a 7-day answer (**ask**).
7. **সম্পাদকের ক্লাস** (S–M): one monthly lesson from the desk's real, anonymous edits ("this month's five commonest
   mistakes in poems"); learning is the best pay for young writers.
8. **জবাবি কবিতা** (M): once a month a poem opens for answer-poems (the old কবির লড়াই); the best are linked under it.
9. **ক্যাম্পাসের কলম** (M): Bengali departments and college magazines get a page and a yearly contest building to
   ২১ ফেব্রুয়ারি; teachers shortlist.
10. **দুই বাংলার সেতু** (M): a Dhaka and a Kolkata writer on one theme each month; a small English translation strand for
    the diaspora.
11. **পাঠচক্র** (M): a public-domain classic a month, one question a week in the letter, answers as comments.
12. **Recommendation swaps** (S): a monthly "পড়ুন ওদেরও" with Ichchhamoti, Kalpabiswa, Parabaas and Bangladeshi webzines.
13. **শারদীয়ার আমন্ত্রিত** (M): Sharodiya slots reserved for writers who earned them during the year (published pieces,
    readers' choice, medals); a goal for the whole year.
14. **Print later** (L, **ask**: the online-only rule): a print-on-demand বর্ষসেরা volume; readings abroad at Pujas.

## E. What to avoid

- Paid fast-track, paid feedback or entry-fee contests: they break the free promise (PLAN.md/MONETIZATION list some; **ask**).
- Writer leaderboards by reads or likes: reward networks over craft, invite fake likes.
- More automatic e-mails without a budget: there are already 15+ kinds; set at most ~2 a week a person, fold the rest into a digest.
- Hatpakha-run chat groups or open peer critique: moderation load, fights, and AI moderating live chat breaks D42.
- Daily publishing, AI co-writing for writers, AI pieces under human-looking names.
- Pay-per-episode serials: Amazon closed Kindle Vella in Feb 2025 ("hadn't caught on").

## F. Suggested order (next 90 days)

1. Now to late December (ফিরে আসা, বর্ষসেরা): A1–A11, B1–B3, B5, B6, C1–C3; announce D3 in the বর্ষসেরা issue (27 Dec).
2. January–early February (Boimela: Dhaka 7 Jan–6 Feb, Kolkata 22 Jan–3 Feb): D4 stall, first D1 আড্ডা ("বইমেলার
   সন্ধ্যা"), D5 podcast, D9 college letters, B4 calls page.
3. To ২১ ফেব্রুয়ারি: D9 contest, D10, D7; then measure (returning writers, কলম → submissions, আড্ডা attendance,
   stall listings) before D8, D11, D13, D14.

Sources for the market research (pages opened): parabaas.com, ichchhamoti.in, kaliokalam.com,
en.kavyakishor.com (Bangladeshi online literary magazines, Nov 2025); plus reports on Sunday Suspense (edtimes.in),
Pratilipi (theprint.in), Kindle Vella's closure (engadget.com, geekwire.com), the Kali o Kalam award (thedailystar.net),
Electric Literature (electricliterature.com/about), Wattpad, Webtoon and Substack's own pages. Some sites could not be
opened (banglalive.com refused; joydhakweb.com now redirects to an unrelated site). No audience numbers were found for
any Bengali webzine.
