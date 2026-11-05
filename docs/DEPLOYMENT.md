# Deployment (Hostinger Cloud + GitHub Actions)

## Branches → environments
| Branch | GitHub Environment | Site |
|---|---|---|
| `staging` | `staging` | https://new.hatpakha.com |
| `main` | `production` | https://hatpakha.com (after cutover) |

`.github/workflows/ci.yml` runs on every push/PR (tests, `composer audit`, `npm audit`).
`.github/workflows/deploy.yml` runs on push to `staging`/`main`: tests → production build → rsync over SSH → writes `.env` → `migrate --force` → `optimize` → `filament:optimize`.

## One-time setup (owner)
1. **Hostinger**: create the subdomain `new.hatpakha.com` and a MySQL database + user. Enable SSH access.
2. **Folder layout** on the server:
   ```
   ~/domains/new.hatpakha.com/app/          ← DEPLOY_PATH (Laravel root)
   ~/domains/new.hatpakha.com/public_html → symlink to app/public
   ```
   Point the document root at `app/public`, never at the Laravel root.
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
| `MAIL_*` | can be `MAIL_MAILER=log` | real SMTP |
| `ADSENSE_*` | empty (no ads on staging) | your AdSense IDs |

   Never reuse the staging `APP_KEY` or DB password in production.

## WordPress data
1. Import the old dump into a **separate** database on the server; put its credentials in `LEGACY_DB_*`.
2. `php artisan migrate --seed --force` then `php artisan hatpakha:import-wp` (safe to re-run).
3. Copy the old `wp-content/uploads` to `app/public/wp-content/uploads/` (old image URLs keep working). The `.htaccess` there blocks PHP and scripts; still scan the folder before going live (Phase 1 leftover: scanner script).
4. Create the first admin: `php artisan tinker` → set your user's `role` to `admin`. The panel forces 2FA setup on first login.

## Never
- Commit `.env`, the DB dump, keys or the uploads folder.
- Point the document root at the Laravel root.
- Run `migrate:fresh` on the server.

## Cutover (Phase 4, needs owner approval)
Freeze WordPress → final dump + import → switch the domain → verify redirects and Search Console → keep the old site's DB backup offline.
