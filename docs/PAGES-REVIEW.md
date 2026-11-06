# Pages: editorial review (for the owner)

The old site had 12 published pages. I read each one as an editor. Rule used: **keep every old URL**, keep only facts that are true and confirmed (first post Nov 2012, ISSN 2321-3035, editor@hatpakha.com), and rewrite the rest in simple Bengali for the new magazine. Rewritten text lives in `database/content/pages/*.html`; after import, pages are edited in **/desk → পাতা**. A native Bengali copy editor should read all of them before launch.

## Rewritten (in the footer, indexable)

| URL | Old page | What was wrong | New page |
|---|---|---|---|
| `/about-hatpakha/` | "About Hatpakha" (English) | Self-help blog tone ("help people improve their lives"), said "no proofing, no cancellation" (contradicts editorial review), grammar errors | **হাতপাখার কথা**: what we are, since 2012, ISSN, why (new writers' first home; kept from the old text), what we publish, how we work |
| `/submissions/` | "Submissions" | Told people to register and use a form that no longer exists; contained hidden base64 script code | **লেখা পাঠানোর নিয়ম**: how to send (by email until the new form), what we accept, what happens next |
| `/faqs/` | FAQ (English) | Login/register steps for the old plugin, dead links | **প্রশ্নোত্তর**: 11 questions in Bengali; kept the old answers that are still true (free, no word limit, parts, we fix spelling but never ideas, no payment now) |
| `/contact-us/` | Contact us | Odd travel-agency line ("we'll send you there") | **যোগাযোগ**: one email, what to write about (corrections, copyright complaints, ads, site problems) |
| `/advertise/` | Advertise | Unverifiable claims ("thousands of youngsters"), a "sales arm" that doesn't exist, **old USD prices** | **বিজ্ঞাপন ও সহযোগিতা**: sponsorship options, ad policy, contact. **No prices** (pricing is your decision) |
| `/privacy-policy/` | Privacy Policy | Generic template about shopping carts and orders; not true for Hatpakha | **গোপনীয়তা নীতি**: what we actually store (accounts, submissions, comments, server logs), cookies (login + Google AdSense), who sees data (Hostinger, Cloudflare, Google), your rights |
| `/terms-conditions/` | Terms & Conditions | Template that claimed **all content belongs to "Hatpakha Magazine"**, bad for writers | **শর্তাবলি**: author keeps copyright, Hatpakha gets a non-exclusive licence to publish, editing rules, conduct, takedown |

## Kept as they were, hidden from Google (noindex), not in the footer

| URL | Why | Your call |
|---|---|---|
| `/critics/` ("Feedbacks") | Real reader comments from 2014. Genuine, but old and in English | Keep, or I can turn them into a Bengali "পাঠকের কথা" section |
| `/terms-conditions-hatpakha-app/` | Licence for a 2020 "Hatpakha" mobile app (by Optimasedge) | Does the app still exist? If not, I'll redirect this to `/terms-conditions/` |
| `/madhyamik-mathematics-suggestion/`, `/mathematics-suggestion-higher-secondary/` | 2016 exam suggestions: off-topic for a literary magazine and outdated | Keep hidden, or remove (410)? |

## Old plugin pages → 301 to home
`/login/`, `/register/`, `/account/`, `/password-reset/`, `/user/`, `/members/`, `/logout/`, `/authors/`, `/edit-post/`, `/connections/`, `/home-4/`, `/tds-checkout/`, `/tds-my-account/`, `/tds-login-register/`. They will point to the new reader login and writer area when those exist (Phase 3).

## Policy points to confirm (I wrote sensible defaults; change anything)
1. **Submissions by email** to editor@hatpakha.com until the new form launches (Phase 3). Subject format: `লেখা — বিভাগ — শিরোনাম`. Max 3 poems per email.
2. **Previously published work** is accepted if the writer tells us where it appeared. (Alternative: only unpublished work.)
3. **Copyright stays with the author**; Hatpakha gets a non-exclusive licence (site, issues, e-books, social posts).
4. **No payment to writers** for now; no fee to submit (already decided, D7).
5. **Under 18**: send with a guardian's consent.
6. **Advertising rules**: ads always labelled; advertisers can't influence editorial choices; no misleading, gambling or intoxicant ads.
7. **Governing law**: "laws of India" (no specific court named). Tell me if you want a city, e.g. Kolkata.
8. I did **not** mention AI-written submissions. Do you want a rule, e.g. "লেখা মানুষের লেখা হতে হবে"?

Privacy and terms are plain-language drafts, not legal advice. Have them checked before launch if you can.
