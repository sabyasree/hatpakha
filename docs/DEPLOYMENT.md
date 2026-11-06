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
in hPanel → Advanced → Cron Jobs → "Custom", every minute (`* * * * *`):

```
cd /home/<user>/apps/hatpakha-staging && php artisan schedule:run >> /dev/null 2>&1
```

(Production: the same with the production folder.) /desk → আজকের কাজ shows "শেষ কাজ: … আগে"; if it says the
clock is not running, this line is missing. The schedule only prepares work and respects the pause button.

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

## Cutover (Phase 4, needs owner approval)
Freeze WordPress → final dump + import → switch the domain → verify redirects and Search Console → keep the old site's DB backup offline.
