# Deployment (Hostinger Cloud + GitHub Actions)

## Branches → environments
| Branch | GitHub Environment | Site |
|---|---|---|
| `staging` | `staging` | https://new.hatpakha.com |
| `main` | `production` | https://hatpakha.com (after cutover) |

`.github/workflows/ci.yml` runs on every push/PR (tests, `composer audit`, `npm audit`).
`.github/workflows/deploy.yml` runs on push to `staging`/`main`: tests → production build → rsync over SSH → writes `.env` → PHP version check → `migrate --force` → `db:seed --force` (idempotent) → `optimize` → `filament:optimize`.

## One-time setup (owner)
1. **Hostinger**: create the subdomain `new.hatpakha.com` and a MySQL database + user. Enable SSH access.
2. **Folder layout.** Hostinger forces a subdomain's custom folder to sit inside the main site's `public_html`, so only a **symlink** goes there; the Laravel app lives in the home folder, outside every web root:
   ```
   ~/apps/hatpakha-staging/            ← DEPLOY_PATH (Laravel root, .env lives here)
   ~/apps/hatpakha-staging/public/     ← the only web-visible folder
   ~/domains/hatpakha.com/public_html/new → symlink to ~/apps/hatpakha-staging/public
   ```
   In hPanel → Subdomains: subdomain `new`, tick **Custom folder for subdomain**, folder name `new`, leave **Use public_html directory** unticked. Then over SSH:
   ```bash
   ls -la ~/domains/hatpakha.com/public_html/new     # only Hostinger's default files expected
   mkdir -p ~/apps/hatpakha-staging/public
   rm -r ~/domains/hatpakha.com/public_html/new
   ln -s ~/apps/hatpakha-staging/public ~/domains/hatpakha.com/public_html/new
   cd ~/apps/hatpakha-staging && pwd                  # this is DEPLOY_PATH (the deploy refuses any path inside public_html)
   ```
   Side effect: `hatpakha.com/new/` also reaches the staging app's public folder (never `.env`). Staging is noindex, so this is harmless; to block it, add at the top of WordPress's `public_html/.htaccess`:
   ```apache
   RewriteEngine On
   RewriteCond %{HTTP_HOST} ^(www\.)?hatpakha\.com$ [NC]
   RewriteRule ^new(/|$) - [F,L]
   ```
3. **SSH key for deploys**: generate a new key pair just for GitHub Actions (`ssh-keygen -t ed25519 -C hatpakha-deploy`), add the public key in Hostinger → SSH Access, keep the private key only in GitHub.
4. **GitHub → Settings → Environments** → create `staging` and `production`, each with these **secrets**:

| Secret | Value |
|---|---|
| `SSH_PRIVATE_KEY` | The deploy private key |
| `SSH_KNOWN_HOSTS` | Output of `ssh-keyscan -p <port> <host>` |
| `SSH_HOST` | Hostinger SSH host/IP |
| `SSH_PORT` | Hostinger SSH port (often 65002) |
| `SSH_USER` | Hostinger SSH user |
| `DEPLOY_PATH` | Absolute path of the Laravel root (step 2) |
| `ENV_FILE` | The full production `.env` content (start from `.env.example`) |

   For production, add a required reviewer on the `production` environment.
5. `ENV_FILE` values that differ per environment:

| Key | `staging` | `production` |
|---|---|---|
| `APP_ENV` | `production` | `production` |
| `APP_DEBUG` | `false` | `false` |
| `APP_URL` | `https://new.hatpakha.com` | `https://hatpakha.com` |
| `APP_KEY` | its own (`php artisan key:generate --show`) | a **different** one |
| `DB_*` | staging database | production database |
| `SEO_INDEXABLE` | `false` (robots `Disallow: /` + `X-Robots-Tag: noindex`) | `false` until cutover, then **`true`** |
| `MAIL_*` | real SMTP if anyone uses **email 2FA** or password reset on staging (with `log`, codes only go to `storage/logs`) | real SMTP |
| `ADSENSE_*` | empty (no ads on staging) | your AdSense IDs |

   Never reuse the staging `APP_KEY` or DB password in production.

## The AI editor's clock (cron), one line per environment (D44)
The AI editor works on a schedule (every 10 minutes, hourly, daily; see `routes/console.php`). Add ONE cron job
in hPanel → Advanced → Cron Jobs: type **Custom**, every minute (`* * * * *`), with full paths (hPanel crons
don't start in your folder and don't expand `~`). Use the same folder as the `DEPLOY_PATH` secret:

```
/usr/bin/php /home/<user>/apps/hatpakha-staging/artisan schedule:run >> /home/<user>/apps/hatpakha-staging/storage/logs/cron.log 2>&1
```

The log file shows any error. A `[diagnose-cron]` staging deploy prints the heartbeat and the end of that log.

(Production: the same with the production folder.) /desk → আজকের কাজ shows "শেষ কাজ: … আগে"; if it says the
clock is not running, this line is missing. The schedule only prepares work and respects the pause button.

## Speed: Cloudflare cache for guests (D65), one rule per domain

The app already marks guests' public pages as shareable (`Cache-Control: public, s-maxage=300/600`, no cookies) and never shares anything for signed-in readers, writers or editors. Cloudflare only needs to be allowed to keep them:

1. Cloudflare dashboard → the domain → **Caching → Cache Rules → Create rule**, name it `Hatpakha guest pages`.
2. **When incoming requests match** (Edit expression):
   `(http.host eq "new.hatpakha.com") and not http.cookie contains "hatpakha-session" and not starts_with(http.request.uri.path, "/desk") and not starts_with(http.request.uri.path, "/livewire")`
   (for production use `hatpakha.com`; the cookie name is `SESSION_COOKIE` in the `.env`, by default `hatpakha-session`: check it in the browser's cookies once.)
3. **Then**: Cache eligibility → **Eligible for cache**; Edge TTL → **Use cache-control header if present**; Browser TTL → **Respect origin**.
4. Save. Check: open a post in a private window twice; the response header `cf-cache-status` should say `HIT` the second time. Signed in, it should say `DYNAMIC` or `BYPASS`.

Also worth switching on (Speed → Optimization): Brotli, HTTP/3, Early Hints. Leave Rocket Loader **off** (it breaks the page scripts).

New or edited pieces appear for guests within 5–10 minutes (the cache lifetime); for an urgent fix, use Caching → Purge → Custom purge with the page's address.

## WordPress data
1. Import the old dump into a **separate** database on the server; put its credentials in `LEGACY_DB_*`.
2. `php artisan migrate --seed --force` then `php artisan hatpakha:import-wp` (safe to re-run).
3. Copy the old `wp-content/uploads` to `app/public/wp-content/uploads/` (old image URLs keep working). The `.htaccess` there blocks PHP and scripts; still scan the folder before going live (Phase 1 leftover: scanner script).
4. Make phone-sized images: `php artisan hatpakha:images` (re-run after each new import; it skips what exists).
5. Create the first admin: after the import your WordPress account exists, so run `php artisan hatpakha:role you@example.com admin`, then sign in at `/desk/` with your old WordPress password (it is re-hashed on first login). The panel forces 2FA setup on first login.

## Never
- Commit `.env`, the DB dump, keys or the uploads folder.
- Point the document root at the Laravel root.
- Run `migrate:fresh` on the server.

## Cutover (Phase 4): production is a copy of staging (D90, owner)
The old WordPress production is already stopped. Everything done on staging (AI covers, SEO texts, desk edits,
audit decisions, honours, certificates, accounts, settings) is kept by **copying staging to production**.
Staging then stays for development with AI switched off.

1. **Back up staging** (database + `public/media` + `public/wp-content/uploads`). Keep the copy off the server.
2. **Database:** create the production database on Hostinger and import a dump of the staging database into it.
3. **Files:** copy `public/media/` (covers, share pictures) and `public/wp-content/uploads/` from staging to the
   production folder (the deploy never touches these, D47).
4. **Production `ENV_FILE`** (GitHub Environment `production`), differences from staging:
   - `APP_URL=https://hatpakha.com`
   - **`APP_KEY` = exactly the staging key.** Two-step-verification secrets and recovery codes are stored
     encrypted with it; a new key would lock every 2FA user (owner included) out of /desk.
   - `DB_*` = the new production database; `SEO_INDEXABLE=true`; `TURNSTILE_HOSTNAMES=hatpakha.com`
   - real SMTP (`MAIL_*`); AI keys and `AI_ENABLED=true`; Turnstile keys for the hatpakha.com widget
5. **Deploy** `main` to production (owner merges the branch into `main`), then on the server:
   - `php artisan migrate --force`
   - `php artisan hatpakha:move-host --dry` (see what still says `new.hatpakha.com`), then `php artisan hatpakha:move-host`
   - `php artisan optimize:clear`
6. **Cron** on production: `* * * * * php artisan schedule:run`.
7. **Cloudflare:** point hatpakha.com at production; cache rules (guest HTML; bypass `/desk*`, `/livewire*`,
   `/submit*`, `/account*` and requests with the session cookie). Re-check the Turnstile widget's hostnames.
8. **Check:** `php artisan hatpakha:url-audit --base=https://hatpakha.com …` (old URLs 200/301), log in to /desk
   with 2FA, send a test contact message, share a piece on WhatsApp. Submit the sitemap in Search Console.
9. **Clean test leftovers** in production /desk (test accounts, test messages).
10. **Staging afterwards:** keep `AI_ENABLED=false` and `MAIL_MAILER=log` in the staging `ENV_FILE`. Staging holds
   real writers' accounts and e-mails, so it must never send mail or run paid AI jobs. Its data is a snapshot from
   launch day; new content lives on production only.
