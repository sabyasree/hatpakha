# Google Analytics and Search Console for the AI team (D119)

**What it does:**
- The site carries the Google Analytics 4 tag (on hatpakha.com only).
- Every morning at 7:30 the site fetches the last 28 days from Google Analytics and Search Console. That covers readers, where they come from, the most read pages, cities, devices, the busiest hours and the Google searches that bring people. The site keeps a short summary.
- The AI team reads that summary in its shared knowledge:

| Agent | Uses it for |
|---|---|
| Marketing and copywriter | What to post, when (busiest hours), and which channel works |
| Newsletter and editor's picks | What readers like |
| Issue planner and strategist | Which sections to grow |
| SEO expert | The Google searches, and the pages seen often on Google but rarely clicked |
| Analyst | The monthly report |
| Editor-in-chief | The overall picture |

- **Reviews of writers' pieces never see traffic numbers.** A piece is judged on its writing, not its popularity.
- **You can read exactly what the AI reads:** /desk → এআই সেটিংস → "গুগল অ্যানালিটিক্স ও সার্চ কনসোল".

**Cost:** free. Everything is read-only; the site never changes anything in Google.

**Allow about 45 minutes.** Use the Google account that owns hatpakha.com's Google services.

---

## 1. Google Analytics 4 property (10 minutes)
**If hatpakha.com already has a GA4 property** (from the WordPress site), use it: the history stays. Get its two numbers (steps 4 and 5) and skip the rest.

1. Go to **analytics.google.com** → Admin (gear, bottom left) → **Create** → **Property**.
2. Property name `Hatpakha`, time zone **India**, currency **INR** → Next → business details (Media/Publishing, small) → Create.
3. **Data stream:** Web → URL `https://hatpakha.com` → name `hatpakha.com` → Create stream.
4. Copy the **Measurement ID**, which looks like `G-XXXXXXXXXX`. This is `GA_MEASUREMENT_ID`.
5. Admin → **Property details** → copy the **Property ID**, which is a number like `412345678`. This is `GA_PROPERTY_ID`.
6. Admin → **Data collection and modification → Data retention** → set **14 months** → Save.
7. Admin → **Data collection** → leave **Google signals off**. The site also switches it off from its side.

## 2. Search Console (10 minutes)
1. Go to **search.google.com/search-console** → Add property → **Domain** → `hatpakha.com`.
2. Google shows a **TXT record**. Add it in Cloudflare → DNS: Type TXT, Name `@`, the value Google gives → Save. Then click **Verify** in Search Console. It usually verifies within minutes.
3. The value for the site is `sc-domain:hatpakha.com`. This is `SEARCH_CONSOLE_SITE`.
4. After launch: Search Console → Sitemaps → add `https://hatpakha.com/sitemap.xml`.

## 3. A "reader" robot account for the site (15 minutes)
1. Go to **console.cloud.google.com** → top bar → project picker → **New project** → name `hatpakha-reports` → Create, then select it.
2. Search **"Google Analytics Data API"** → **Enable**.
3. Search **"Google Search Console API"** → **Enable**.
4. Menu → **IAM & Admin → Service accounts** → **Create service account**:
   - name `hatpakha-reader`;
   - skip roles ("Continue" twice);
   - Done.
5. Open `hatpakha-reader` → **Keys** → Add key → Create new key → **JSON** → Create. A file downloads.
   - Keep it safe, like a password; don't send it to anyone or paste it in chat.
   - If Google says key creation is disabled by an organisation policy, tell me. That only happens on company Google accounts.
6. Copy the robot's **email**, which looks like `hatpakha-reader@hatpakha-reports.iam.gserviceaccount.com`.

## 4. Let the robot read (5 minutes)
1. **Analytics:** Admin → **Property access management** → **+** → Add users → paste the robot's email → role **Viewer** → untick "Notify by email" → Add.
2. **Search Console:** Settings → **Users and permissions** → Add user → the robot's email → permission **Restricted** → Add.

## 5. Put it in GitHub (5 minutes)
1. Turn the JSON key file into one line (base64):
   - **Mac:** open Terminal, type `base64 -i ` (with a space), drag the key file into the window, add ` | tr -d '\n' | pbcopy`, press Enter. The line is now copied.
   - **Windows:** open PowerShell and run `[Convert]::ToBase64String([IO.File]::ReadAllBytes("C:\path\to\key.json")) | Set-Clipboard` (use your file's real path).
2. GitHub → repository → Settings → Environments → **production** → `ENV_FILE` → add:
```
GA_MEASUREMENT_ID=G-XXXXXXXXXX
GA_PROPERTY_ID=412345678
SEARCH_CONSOLE_SITE=sc-domain:hatpakha.com
GOOGLE_SERVICE_ACCOUNT_JSON=<paste the one long line>
```
3. Redeploy production.
4. **Optional, staging:** you may add the same four lines to the **staging** ENV_FILE so you can see the numbers in staging's /desk. The tag never appears on staging pages, so staging visits are never counted.

## 6. Check it
1. Open hatpakha.com on your phone. In Google Analytics → **Reports → Realtime** you should appear within a minute.
2. /desk → **এআই সেটিংস** → "গুগল অ্যানালিটিক্স ও সার্চ কনসোল" → **এখনই আনুন**. The text the AI team will read appears below.
   - A new property shows little in the first days.
   - Search Console needs 2–3 days before its first numbers.
3. Error messages are shown on that panel in plain words, for example "Analytics: User does not have sufficient permissions". That one means step 4 is missing.

---

## How it's kept safe
- **The tag is only on hatpakha.com,** never on staging, /desk or children's pages.
- **Only the page's path is sent to Google** (plus `utm_` campaign labels, such as the ones on letter links). The rest of the address, like signed unsubscribe or reply links, never goes to Google.
- **Google signals and ad personalisation from analytics are off.** Visitors in the EU, UK and Switzerland are asked for consent first, by the same consent message as AdSense.
- **The robot can only read,** and its key lives only in the GitHub ENV_FILE secret.
- **No person is identified.** The site stores only totals; Google's reports don't contain people.
- **Letters are labelled.** Links in letters carry `utm_source=hatpakha-chithi&utm_medium=email`, so Google Analytics shows how many visits each letter brought.
- **Our own read counter (D54) keeps running.** It works without cookies, so it is the more complete count.
- **The privacy policy now mentions Google Analytics.** If you edited that page in /desk, add the new paragraph by hand.
