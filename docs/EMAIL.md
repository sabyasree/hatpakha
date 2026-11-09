# E-mail setup: Hostinger for accounts, Amazon SES for letters (D117)

Two senders, one address: every e-mail comes **from `chithi@hatpakha.com`**.

| What | Sent through | Why |
|---|---|---|
| Sign-up confirmation, password reset, login codes, the editor's decisions, notes to a writer | **Hostinger email** (`MAIL_*`) | Few e-mails; part of the Hostinger plan already |
| Newsletters, the relaunch letters, "new episode", "your writer published" | **Amazon SES** (`MAIL_BULK_*`) | About 1,720 people per letter; about $1–2 a month; same-day delivery |

Until `MAIL_BULK_HOST` is set, everything goes through Hostinger, as before.
Allow about **one hour of work, plus a wait of a day or two** for Amazon (step 5).

---

## 1. The mailbox at Hostinger (10 minutes)
1. hPanel → **Emails** → hatpakha.com → create the mailbox **chithi@hatpakha.com** with a strong password. Replies from readers land here.
2. Note the SMTP details hPanel shows. They are usually: host `smtp.hostinger.com`, port `465`, SSL.
3. Hostinger's DNS page for email lists **MX, SPF (TXT) and DKIM (CNAME)** records. hatpakha.com's DNS is at Cloudflare, so add exactly those records there:
   - Cloudflare → hatpakha.com → **DNS**;
   - set each one to "DNS only" (the grey cloud), never proxied.

## 2. Amazon account (15 minutes)
1. Go to **aws.amazon.com** → Create account (needs a card; it charges only what you use).
2. Sign in to the console. In the top right, choose the region **Asia Pacific (Mumbai) ap-south-1**. Every step below happens in that region.
3. Turn on two-step login for the account: top right → your name → Security credentials → MFA.

## 3. Prove hatpakha.com is ours (15 minutes + up to a few hours of waiting)
1. Console → search **"Amazon SES"** → Configuration → **Identities** → Create identity → **Domain** → `hatpakha.com`.
2. Tick **"Use a custom MAIL FROM domain"** and enter `mail.hatpakha.com`.
3. Leave **Easy DKIM** and **RSA 2048** as they are → Create.
4. Amazon now shows records to add:
   - 3 DKIM records (CNAME);
   - 1 MX and 1 TXT record for `mail.hatpakha.com`.

   Add all five in Cloudflare → DNS, set to **DNS only** (the grey cloud).
5. Also add a **DMARC** record in Cloudflare, if there isn't one yet:
   - Type `TXT`, name `_dmarc`, value `v=DMARC1; p=none; rua=mailto:chithi@hatpakha.com`.
6. Wait until SES shows the identity as **Verified**. Usually minutes, at most 72 hours.

## 4. The SMTP password for the site (5 minutes)
1. SES → **SMTP settings** → **Create SMTP credentials** → Create user.
2. Download the file with the **SMTP user name and password**. This is NOT your Amazon login. It is shown only once.
3. The server is `email-smtp.ap-south-1.amazonaws.com`, port `587`.

## 5. Ask Amazon to lift the starting limit (5 minutes + 1–2 days)
New SES accounts are in a "sandbox": 200 e-mails a day, and only to addresses you verified. SES → **Account dashboard** → **Request production access**:
- Mail type: **Marketing**
- Website URL: `https://hatpakha.com/`
- Use case description (paste, then adjust if needed):

> Hatpakha (hatpakha.com, ISSN 2321-3035) is a Bengali literary web magazine published since 2012. We send a weekly newsletter of newly published poems and stories to readers who signed up on our site with double opt-in (confirmation link), or who hold a verified account and can switch it off in one click; a monthly letter to our writers; and short notices when a serial they follow has a new episode. About 1,700 recipients, roughly 9,000 e-mails a month. Every message has a one-click unsubscribe link and List-Unsubscribe headers. Bounces and complaints come back to us through SNS and are suppressed automatically and permanently. We never buy or rent lists.

Amazon answers by e-mail, usually within a day. After that, the dashboard shows your daily quota (often 50,000 a day).

## 6. Bounce and complaint reports to the site (10 minutes)
1. Console → search **"SNS"** (same region) → Topics → **Create topic** → Standard → name `hatpakha-ses` → Create.
2. Copy the topic's **ARN** (starts with `arn:aws:sns:ap-south-1:`).
3. Back in SES → Identities → `hatpakha.com` → **Notifications** → Edit feedback notifications:
   - Bounce feedback: `hatpakha-ses`;
   - Complaint feedback: `hatpakha-ses`;
   - Save.
4. Do step 7 first; then come back for step 5 here.
5. SNS → the topic → **Create subscription** → Protocol **HTTPS** → Endpoint `https://hatpakha.com/webhooks/ses` → Create. The site confirms it by itself within a minute; the subscription shows **Confirmed**.

## 7. Put the settings in GitHub (5 minutes)
GitHub → the repository → Settings → Environments → **production** → `ENV_FILE` → edit, and set these lines. Replace the values in angle brackets, keep everything else.

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=chithi@hatpakha.com
MAIL_PASSWORD=<the Hostinger mailbox password>
MAIL_FROM_ADDRESS=chithi@hatpakha.com
MAIL_FROM_NAME="হাতপাখা"

MAIL_BULK_HOST=email-smtp.ap-south-1.amazonaws.com
MAIL_BULK_PORT=587
MAIL_BULK_USERNAME=<SMTP user name from step 4>
MAIL_BULK_PASSWORD=<SMTP password from step 4>
MAIL_DAILY_LIMIT=190
SES_SNS_TOPIC_ARN=<the topic ARN from step 6>
```

- `MAIL_DAILY_LIMIT=190` keeps the site under the sandbox limit. After Amazon lifts it (step 5), change it to `0` (no limit) or to the quota SES shows.
- Then re-run the deploy (Actions → Deploy → Run workflow on `main`). Staging keeps `MAIL_MAILER=log` and no `MAIL_BULK_*`, so staging never mails anyone.

## 8. Check it (10 minutes)
1. /desk → চিঠি → open a draft → **"আমাকে একটা পাঠান"**: the test letter arrives from chithi@hatpakha.com.
2. In Gmail, open it → ⋮ → **Show original**: SPF, DKIM and DMARC should all say **PASS**.
3. Log out and use "পাসওয়ার্ড ভুলে গেছেন?" with your own address: that e-mail comes through Hostinger.
4. Send the first real letter. In /desk → চিঠি you'll see "N / 1,720 জনকে পাঠানো". After a day, press **ফলাফল** to see how many people clicked and which piece.

## What /desk shows
- **List (চিঠি):** sent / recipients, and **ক্লিক**: how many people clicked at least one link.
- **A letter's page:**
  - sent, failed, clicked (%), and addresses that bounced or complained (never mailed again);
  - "today's limit reached" while a letter is spread over days;
  - **ফলাফল**: people per link (each piece, the button, the name at the top).
- **Opens aren't counted, on purpose.** Apple Mail and Gmail load images by themselves, so open numbers are wrong. The letters have no hidden tracking image. Clicks are counted on our own site; no outside tracker.
- **SES's own dashboard** (Account dashboard → Reputation metrics): bounce and complaint rates for the whole account. Keep bounces under 5% and complaints under 0.1%. The automatic suppression does most of that.

## Cost
About 1,720 people × (4 readers' letters + 1 writers' letter) ≈ 8,600 e-mails a month ≈ **$1–1.50 a month** at $0.10–0.16 per 1,000 ([AWS SES pricing](https://aws.amazon.com/ses/pricing/); check the current price there). SNS reports for this volume cost practically nothing.

## If the privacy page was edited in /desk
The update to the privacy policy (counted links; Amazon SES delivers the letters) is applied automatically only if the page was never edited in /desk. If it was, add these two points by hand in /desk → পাতা → গোপনীয়তা নীতি.
