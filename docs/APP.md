# Hatpakha as an app, and phone notifications (D127)

**What readers get:**
- **On the home screen.** Hatpakha can be added to a phone's home screen with the logo as its icon, and opens full screen like an app.
  - **Android (Chrome):** from the third page a reader opens, a small box offers "যোগ করুন". If they press "পরে", it stays away for 30 days.
  - **iPhone:** Safari shows the two steps instead (Share □↑ → Add to Home Screen).
- **Offline reading.** The last 40 pages a guest has opened can be read again without internet. Any other page shows "এখন ইন্টারনেট নেই" with a list of the pieces that can be read offline.
- **Always a way to add it (D128).** The footer of every page has three store-style buttons: "যোগ করুন Android ফোনে / iPhone-এ / কম্পিউটারে". The menu has "📱 ফোনে হাতপাখা". Both lead to **/app/**, which has the steps for each device and the daily-notification button.
  - On Android Chrome, the Android button installs straight away when the browser allows it.
  - Inside the installed app these buttons are hidden.
- **Asked once, right after adding (D128).** Right after Hatpakha is added on Android, or the first time it's opened from the home screen, one box asks "🔔 নতুন লেখা এলে ফোনে জানাব?".
  - "হ্যাঁ, জানান" turns on the daily note, plus the reader's follows if signed in. "এখন না" never asks again.
- **Phone notifications, with or without an account (D128).**
  - **🔔 buttons** on every writer page and serial page (and episode) send a notification only when that writer or serial has something new.
  - **The daily note:** once a day at 19:40 India time, a short note of the day's new pieces. Nothing new means nothing is sent. Set `PUSH_DAILY=false` in ENV_FILE to stop it.
  - **Signed-in readers** can also link their follows in Account → Settings → **ফোনে খবর**, where there is a test button.
  - Each phone hears about a piece only once, however it asked.
  - **iPhone:** works from iOS 16.4, and only after Hatpakha is added to the home screen and opened from that icon. The buttons lead there.

**Cost:** free. No app store, no new service. Notifications travel through the browsers' own services (Google, Mozilla, Apple, Microsoft).

---

## Turn on notifications (once, about 5 minutes)

Until the two keys are in place, the "ফোনে খবর" card doesn't appear. Everything else (home screen, offline reading) already works.

1. Open the Hostinger SSH terminal (the same one used at launch) and run:
   ```
   cd ~/apps/hatpakha-production && php artisan hatpakha:vapid
   ```
   It prints two lines, `VAPID_PUBLIC_KEY=…` and `VAPID_PRIVATE_KEY=…`. It stores nothing.
2. GitHub → Settings → Environments → **production** → `ENV_FILE` → add those two lines → Save.
   - The private key is like a password: don't paste it into chat or e-mail.
3. Redeploy production (Actions → Deploy → Run workflow on `main`).
4. On your phone, open hatpakha.com → sign in → Account → Settings → **ফোনে খবর** → **এই ফোনে চালু করুন** → allow → **একটা পরীক্ষা পাঠান**. A notification should arrive within seconds.

**Never change the keys after this.** New keys silently switch off notifications for everyone who had them on, and each person would have to turn them on again.

**Staging (optional):** run the same command once more and put *that* pair in the **staging** ENV_FILE. Staging only ever sends notifications to desk staff (like e-mail, D118).

---

## How it's kept safe
- **The offline helper never touches the desk, account pages, sign-in, forms or search.** It keeps only pages that Cloudflare may share anyway (guest pages), so nothing personal is stored on the phone.
- **The helper itself (`/sw.js`) is never cached.** Each deploy changes its version line, so phones pick up a new one by the next day at the latest.
- **Notifications go only to the browsers' real push services.** Any other address is refused, so the site can't be tricked into calling other servers.
  - Messages are encrypted for the reader's browser (RFC 8291) and signed with our key (RFC 8292).
  - Written with PHP's own OpenSSL, no extra package. A test checks the result byte for byte against the standard's example.
- **Subscriptions clean themselves up.** If a reader switches notifications off in the browser, the push service answers "gone" and the site deletes that subscription. After 5 failures in a row it is deleted too. One person keeps at most 10 devices.
- **The privacy policy explains both features.** If you edited that page in /desk, add the new section by hand (in `database/content/pages/privacy-policy.html`).

## Later (not built)
- Google Play listing of the same site (Trusted Web Activity, $25 once) when wanted.
- A "new issue" or special announcement to everyone. A broadcast reaches many people at once, so ask the owner first (like bulk e-mail).
