# Security specification (binding)

Every feature must meet this checklist. Items marked ✅test are enforced by tests in `tests/Feature/Security*`.

## 1. Transport and headers
- HTTPS only. Cloudflare SSL mode **Full (strict)**; HSTS `max-age=31536000; includeSubDomains`.
- A security-headers middleware on every response ✅test:
  - `Content-Security-Policy`: nonce-based scripts. Allow only self, the Google Fonts CSS, AdSense domains, and Turnstile.
  - `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera, mic and geolocation off), `frame-ancestors 'self'`.
- Session cookies: `Secure`, `HttpOnly`, `SameSite=Lax`. The session ID is regenerated on login and privilege change.

## 2. Accounts and authentication
- Passwords: Laravel `Hash` (bcrypt or argon2id).
- **Legacy WordPress hashes** (`$P$` phpass, `$wp$` bcrypt): verified once at login, then immediately re-hashed and the legacy hash deleted. The legacy hash is never logged or exposed. ✅test
- Login options: email + password, Google (Socialite), and **magic link** (signed URL, single use, 15-minute expiry).
- **Email verification before anything** (D59) ✅test: a new account cannot open its account page, submit or comment until the address is verified. Sign-up never signs in by itself; the e-mailed link (signed, 60 minutes) verifies and signs in **only on first use**, and never signs in accounts with 2FA or desk roles. Enforced wherever real mail is configured (test servers with the log mailer cannot verify, so they skip it).
- **No account probing** ✅test: sign-up answers the same for new and existing addresses; the owner of an existing address gets a short "you already have an account" e-mail instead. Login and password reset already answer generically.
- **Bots** ✅test: hidden honeypot + "too fast" check on sign-up; Cloudflare Turnstile on sign-up, login, password reset, guest comments and newsletter sign-up (once `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET` are set); rate limits below.
  - Turnstile server check (D60) ✅test: token must be a non-empty string ≤ 2048 characters; siteverify (form POST with secret, response, remote IP; 10-second timeout) must say `success`, the **action of that form** (`login`, `register`, `forgot`, `comment`, `newsletter`) and one of **our hostnames** (`TURNSTILE_HOSTNAMES`, default the `APP_URL` host; localhost never allowed in production). Network errors, non-2xx, unreadable replies or an empty allowlist **fail closed**. Tokens are single-use (Cloudflare rejects replays). Siteverify is never called from the browser; the secret lives only in the server environment (GitHub Environments → `ENV_FILE`).
- **Passwords**: at least 10 characters with letters and numbers, and refused if they appear in known breaches (Have I Been Pwned range check: only the first 5 characters of the SHA-1 hash leave the server). Changing the password logs out other devices.
- **Optional 2FA for readers and writers** (D59) ✅test: authenticator app (TOTP) with 8 one-time recovery codes stored hashed, set up from /account/ (QR + key, confirmed by a first code). Login then asks for the code (10-minute window, throttled, codes cannot be reused). The account page nudges everyone without 2FA. Desk roles cannot switch it off.
- **Two-factor authentication is mandatory** for editors and admins (Filament MFA). Two methods: authenticator app (TOTP, with recovery codes; **recommended for admins**) or a 6-digit **email code** (valid 5 minutes, max 2 sends per window). ✅test
  - Email 2FA is only as safe as the mailbox: anyone who controls the email can also reset the password, so admins should use the app, or both.
- Rate limits ✅test: login 5 per minute per IP+email; password reset 3 per hour; registration 5 per hour per IP; submissions 3 per day per writer.
- Generic error messages (no "user exists" leaks).

## 3. Authorisation
- Roles: `reader`, `writer`, `trusted_writer`, `editor`, `chief_editor`, `admin`. Laravel **Policies** for every model; no role checks inside Blade.
- The admin panel (`/desk`) is reachable only by editor+ (`canAccessPanel`). Optional Cloudflare Access or IP allowlist. ✅test
- Writers can only see and edit their own drafts and submissions. ✅test
- **AI never takes actions.** AI output is advisory data shown to an editor; publishing, rejecting and emailing require a human click (until the trusted-writer auto-approve is explicitly switched on).

## 4. Input, output and content
- CSRF on all forms (Laravel default). All input validated with FormRequests.
- **Rich text is sanitised on save** with an allowlist (p, br, em, strong, blockquote, h2–h4, ul/ol/li, a[href], img[src,alt] from our own domain, figure/figcaption). Raw `{!! !!}` output is allowed only for sanitised fields (`*_html`). ✅test (XSS payloads stripped)
- Eloquent / query bindings only. No string-built SQL.
- Spam: honeypot + **Cloudflare Turnstile** on register, submit, comment and newsletter forms; cheap content pre-filters before any AI call.
- Submissions are **untrusted input to the AI** (prompt injection). They go only into the user part of the prompt, delimited; AI output must match a JSON schema and is never executed or rendered as HTML.

## 5. Uploads and the old WordPress files
- Upload checks: real MIME sniffing, size limit (images 8 MB), images re-encoded (strips EXIF/GPS and any embedded payload), random file names.
- **No PHP executes in upload folders.** `.htaccess` in `public/wp-content/uploads` and `public/media`: `php_flag engine off` + deny `*.php*`, `*.phtml`, `*.phar`. ✅test (rules file present)
- **Migration scan:** the old `wp-content/uploads` folder is scanned before it is copied. Any `.php`, `.phtml`, `.js` inside uploads, files with double extensions, and files whose content does not match their extension are **quarantined, not copied**. A report is kept.
- Legacy attack paths (`/wp-login.php`, `/xmlrpc.php`, `/wp-admin/*`, `/wp-json/*`) return **410** and are rate-limited by a Cloudflare WAF rule.

## 6. Secrets and infrastructure
- `.env` is **never committed**; only `.env.example`. Real values live in **GitHub Environments** (`staging`, `production`) and are written to the server at deploy time.
- A separate least-privilege MySQL user per environment (no `GRANT`, no `FILE`). Staging never uses production credentials.
- SSH deploy key limited to the deploy user. No passwords over SSH.
- Debug off in production (`APP_DEBUG=false`); error pages show no stack traces. ✅test
- `composer audit` + `npm audit` in CI; Dependabot weekly; Laravel and Filament security releases applied within 7 days.

## 7. Monitoring, backups and recovery
- **Audit log** of editorial actions (who approved, rejected, edited, published; from which IP). Admin login history.
- Failed-login alerts to the admin email after repeated failures.
- **Backups:** daily DB backup (Hostinger) + an encrypted weekly copy off-site; uploads weekly. A **restore test every quarter**.
- An uptime monitor on home and one article page.

## 8. Privacy (India DPDP Act 2023)
- A privacy policy: what is collected, why, for how long, and how to delete it. Consent tick-box on registration and newsletter signup.
- "Delete my account" and "download my data" in account settings. Published work stays (author credit can be anonymised on request).
- Minimal PII. Phone number optional. Readers' comments are featured on the home page only with an opt-out.
- A cookie / consent banner for ads (Google consent mode), required for EEA/UK visitors.

## 9. Payments (when the gateway is enabled)
- Gateway-hosted checkout only (no card data on our server). Webhook **signature verification**, idempotency keys, and a server-side amount check. ✅test

## Imported accounts (Phase 2)
- **Least privilege at import:** WordPress administrators/editors arrive as writers; `/desk` access only by an explicit, logged grant (`php artisan hatpakha:role`, or the Users screen). ✅test
- Bot-like and Facebook-test accounts are flagged in `users.spam_reason` (never deleted automatically). ✅test
- Newsletter: only explicit, confirmed consent is mailable; unsubscribed/bounced addresses are suppressed permanently. ✅test
