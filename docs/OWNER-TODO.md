# What you have to do (owner's list)

Written 19 November 2026. Everything here is yours to do; the site side is already built.
Never paste keys, secrets or passwords into the chat with Claude. They go only into the GitHub **ENV_FILE**.

**Rule for ENV_FILE:** any value that contains a space must be inside double quotes, like `NAME="Two Words"`. Without the quotes the deploy fails with "The environment file is invalid".

## 1. Merge and deploy (5 minutes)

- [ ] Merge the open request: https://github.com/sabyasree/hatpakha/pull/88
- [ ] Wait for the Deploy job to finish (green tick).
- [ ] Open hatpakha.com on your phone and check one piece: the five "পড়ে কেমন লাগল?" buttons and the WhatsApp box under it.

Nothing below is needed for the merge. Each item only switches one more feature on.

## 2. Google sign-in (15 minutes, once, free)

- [ ] Go to https://console.cloud.google.com and sign in. Create a project named "Hatpakha". Skip any free-trial or billing offer.
- [ ] **APIs & Services → OAuth consent screen**: choose **External**. App name: Hatpakha. Support e-mail: yours. Authorised domain: `hatpakha.com`. Privacy policy: `https://hatpakha.com/privacy-policy/`. Save, then **Publish app**.
- [ ] **APIs & Services → Credentials → Create credentials → OAuth client ID**. Type: **Web application**.
- [ ] Under **Authorised redirect URIs** add both, exactly, with the slash at the end:
  - `https://hatpakha.com/login/google/callback/`
  - `https://new.hatpakha.com/login/google/callback/`
- [ ] Copy the **Client ID** and **Client secret** into the ENV_FILE of **both** GitHub environments (production and staging):
  - `GOOGLE_CLIENT_ID=...`
  - `GOOGLE_CLIENT_SECRET=...`
- [ ] Re-run Deploy for both.
- [ ] Test on staging first (new.hatpakha.com/login/) in a private window, with a Google account that is **not** your editor account.
- [ ] Tell Claude it works, so the how-to video and PDF can be re-recorded (the first steps change).

## 3. WhatsApp channel (1 minute a day)

- [ ] Put two or three posts in the channel before inviting anyone.
- [ ] Post the channel link once on the Facebook page and pin it.
- [ ] Each day: /desk → মার্কেটিং → "আজকের হোয়াটসঅ্যাপ চ্যানেলের বার্তা" → **কপি** → **চ্যানেল খুলুন** → paste and send.
- [ ] Three posts a week is enough if daily is too much.

## 4. Support page "হাতপাখার পাশে থাকুন" (optional)

The page and its footer link stay hidden until you do this.

- [ ] Decide which UPI address should receive support.
- [ ] Add to the production ENV_FILE:
  - `SUPPORT_UPI_ID=yourname@bank`
  - `SUPPORT_UPI_NAME="Your Name"` the account holder's name **exactly** as UPI apps show it (readers are told to check it before paying). **Keep the double quotes**: a value with a space in it and no quotes stops the deploy.
- [ ] Re-run Deploy, then open hatpakha.com/pashe-thakun/ and send yourself ₹1 to check the name matches.

## 5. AdSense

- [ ] Open the production ENV_FILE and see which of these lines are empty: `ADSENSE_SLOT_HOME`, `ADSENSE_SLOT_HOME_2`, `ADSENSE_SLOT_ARTICLE`, `ADSENSE_SLOT_ARTICLE_2`, `ADSENSE_SLOT_ARTICLE_MID`, `ADSENSE_SLOT_SIDEBAR`, `ADSENSE_SLOT_LIST`. An empty line means that ad spot shows nothing.
- [ ] For each empty one, create a display ad unit in AdSense and put its id on that line, then re-run Deploy. (`ADSENSE_SLOT_LIST` is the one used under the games.)
- [ ] After a week, look at earnings from the game pages: one ad now sits under each game.

## 6. Amazon SES (from earlier)

- [ ] Switch off open and click tracking: SES console → **Configuration sets** and **Virtual Deliverability Manager** → engagement tracking off.
- [ ] Send yourself a test letter and check the links read `hatpakha.com`, not `awstrack.me`.
- [ ] Make a fresh draft of the readers' letter to see the new magazine layout (old drafts keep the old look).

## 7. Decisions only you can make

- [ ] **Free e-book for letter subscribers:** which pieces go in, and ask those writers' consent. Then tell Claude.
- [ ] **Guests and "পড়ে কেমন লাগল?":** keep login required (fair for পাঠকের পছন্দ), or let anyone tap (more answers, but one person can vote many times).
- [ ] **The 14-day promise** is now shown to writers. Change the number if it is too tight.
- [ ] **Game prizes:** twice a year or once a year, and the amount. Not built yet.
- [ ] **Editorial byline:** it shows "Hatpakha Magazine" in English; change the name on that author profile in the desk if you want Bengali.

## 8. Things to watch this week

- [ ] পাকড়াশীর প্রুফ, episode 2: the warning in the desk should clear by itself. If it is still there, send Claude a screenshot.
- [ ] /desk → মার্কেটিং: new Facebook comments on your boosted post should appear in the list. If none appear and there is no warning, tell Claude.
- [ ] /desk → আজকের কাজ: a new warning appears when a writer has waited 10 days for an answer.
- [ ] Reply to the writer who found the form hard, with the link hatpakha.com/submissions/ (the how-to video and PDF are at the top of that page).

## Games (D243 to D245)

- [ ] Play each game once on your own phone on new.hatpakha.com before merging: শব্দ-খেলা, both ধাঁধা, শব্দজব্দ, প্রবাদ পূরণ.
- [ ] Ask a Bengali reader to read through the new lists. They were written by AI and only checked by machine. Files: `database/content/word-game-2.php`, `word-game-hard-2.php`, `riddles-2.php`, `riddles-hard.php`, `proverbs.php`. A wrong one can also be reported from the game itself.
- [ ] Read the winner's letter once and change the wording if you like: /desk → চিঠির বয়ান → "খেলার খেতাব".
- [ ] 1 January 2027: check that December's three names are on the সম্মান-ফলক and that the winners got their letter.
- [ ] Proverbs repeat after about four months (507 of them, four a day). Say if you want more written.
- [ ] If a score looks wrong: /desk → ব্যবহারকারী → the player → "খেলার তালিকা থেকে বাদ" (can be undone).

## Where things are

| What | Where |
|---|---|
| Keys and settings | GitHub → Settings → Environments → ENV_FILE |
| Daily WhatsApp message | /desk → মার্কেটিং |
| Automatic jobs and their switches | /desk → নিজে থেকে চলা কাজ |
| Errors on the site | /desk → Site Errors |
| How-to video and PDF for writers | hatpakha.com/submissions/ |
| Readers' choice page | hatpakha.com/pathoker-pochhondo/ |
| Today's games | hatpakha.com/khela/aaj/ |
| Leaderboard and roll of honour | hatpakha.com/khela/board/ |
| Google and password sign-in counts | /desk → ব্যবহারকারী (top of the page) |
