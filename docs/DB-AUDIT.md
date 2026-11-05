# Existing WordPress database: audit (5 Nov 2026)

Source: phpMyAdmin export of the live hatpakha.com database (MariaDB 11.8, 226 MB, 268 tables).
It was analysed in a temporary local copy. **The dump itself is never committed to this repository.** This file contains totals only, no personal data.

## Content
| Item | Count | Notes |
|---|---:|---|
| Published posts | **1,393** | Matches the live site. Peak years: 2016 (210), 2020 (411), 2021 (215) |
| **Pending posts** (submitted, never reviewed) | **424** | From **221 authors**. **392 of them are from 2016–2019** (7–10 years old) |
| Draft posts | 53 | 2016–2021, never submitted |
| Private posts | 1 | |
| Pages | 25 published, 48 private, 1 draft | Many private pages are leftovers from old plugins |
| Media (attachments) | 3,200 | Files live in `wp-content/uploads` |
| Approved comments | 397 | |
| Other post types | `wpa-stats` 3,439, `feedback` 69, `postman_sent_mail` 39 … | Plugin junk; **not migrated** |

### Pending backlog breakdown (424)
| By year | 2014–15: 6 · **2016: 151 · 2017: 110 · 2018: 101 · 2019: 30** · 2022: 14 · 2023: 5 · 2024: 3 · 2025: 2 · 2026: 2 |
|---|---|
| By category | Poetry 268 · Stories 58 · Sher-O-Shayari 30 · Social 14 · Life 13 · Inspiration 11 · others 30 |
| By length | very short or empty (<300 chars): **30** · poem-size: 195 · medium: 145 · long (6,000+ chars): 54 |
| Language | contains Bengali script: 305 · no Bengali (English or other): 119 |
| Duplicates | 3 are copies of an already-published post; 10 are repeated submissions inside the queue |
| Concentration | **one account holds 125** pending posts (all from 2016, several repeated titles, likely tests or duplicates) |
| Authors | 221 authors are waiting; 77 of them also have published pieces |

## Users
| Item | Count | Notes |
|---|---:|---|
| Accounts | **1,728** | 2013 → Oct 2026; only 33 registered since 2023 |
| Authors with at least 1 post | ~488 published authors | Most other accounts are readers or inactive. Some are probably spam sign-ups (to be filtered at migration) |
| Password hashes | 1,719 phpass (`$P$`), 5 WordPress 6.8 bcrypt (`$wp$`), 4 empty (social login or broken) | **All can keep their password**: verify the old hash at first login, then re-hash |
| Roles | mostly `subscriber` (+ leftovers from Ultimate Member and CoursePress plugins), 4–6 administrators | Clean role mapping: reader / writer / editor / admin |

## Newsletter (MailPoet)
| subscribed | unconfirmed | inactive | unsubscribed | bounced |
|---:|---:|---:|---:|---:|
| **491** | 216 | 941 | 95 | 75 |

Only the **491 subscribed** are migrated as active. The 216 unconfirmed get one re-consent email. Unsubscribed and bounced addresses are never mailed.

## Plugin leftovers (not migrated)
Wordfence and other security logs, LiteSpeed, Revolution Slider, BuddyPress, Give donations, Gravity Forms, forum tables, polls, tagDiv billing, Blog2Social and more: about 250 of the 268 tables. Only `wp_posts`, `wp_postmeta` (selected keys), `wp_users`, `wp_usermeta` (selected keys), terms, comments, Co-Authors data, Yoast SEO meta and MailPoet subscribers are migrated.

## Decisions this audit drives
1. **Backlog plan:** see PLAN.md → "Clearing the existing backlog" (tiered approach for the 424 pending posts).
2. **Spam-account filter** at user migration: accounts with no posts, no comments, no newsletter consent and a known spam pattern are not imported (kept in a list for review).
3. **Old URLs:** all 1,393 published posts keep their slugs; private and plugin pages get 301 redirects or 410.
