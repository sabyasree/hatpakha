# Old categories → new sections (for the owner to confirm)

The old site had 32 categories; the new magazine has 10 sections. **Every old `/category/{slug}/` URL 301-redirects** to its new section, so no link breaks. Code: `ImportWordPress::SECTION_MAP` (anything not listed goes to সমাজ / Social).

Rules applied to each post: (1) English-language posts go to **English**, except shayari; (2) if a post had several categories, the first match in this order wins: কবিতা, শের-ও-শায়েরি, গল্প, ভ্রমণ, অনুবাদ, প্রবন্ধ, সম্পাদকীয়, অনুপ্রেরণা, সমাজ.

| Old category | Posts | → New section | Comment |
|---|---:|---|---|
| poetry | 648 | কবিতা | same |
| short-stories | 400 | গল্প | same |
| fiction | 3 | গল্প | |
| social | 100 | সমাজ | same |
| health | 15 | সমাজ | |
| technology | 8 | সমাজ | |
| editorial | 46 | সম্পাদকীয় | same |
| travel | 38 | ভ্রমণ | same |
| inspiration | 35 | অনুপ্রেরণা | same |
| life | 19 | অনুপ্রেরণা | |
| lifestyle | 13 | অনুপ্রেরণা | |
| literature | 29 | প্রবন্ধ | |
| reviews | 13 | প্রবন্ধ | book/film reviews |
| books | 8 | প্রবন্ধ | |
| mythology | 5 | প্রবন্ধ | |
| interviews | 3 | প্রবন্ধ | |
| sher-o-shayari | 4 | শের-ও-শায়েরি | same |
| translated | 1 | অনুবাদ | same |
| miscellaneous | 25 | সমাজ (default) | **check**: mixed bag |
| videos | 22 | সমাজ (default) | **check**: mostly YouTube posts; candidates for noindex in the content audit |
| featured | 14 | (none: a homepage flag, not a topic) | posts keep their real category |
| entertainment | 10 | সমাজ (default) | **check** |
| amazing-facts | 9 | সমাজ (default) | **check**: likely noindex in the content audit (off-topic) |
| puja-2020 | 8 | সমাজ (default) | **suggest**: make them the archive issue "শারদীয়া ১৪২৭ (২০২০)" |
| article | 6 | সমাজ (default) | |
| game, quiz, coupon, main, uncategorized | 11 | সমাজ (default) | **check**: coupon/game look like old promo posts; likely noindex/410 in the audit |
| news-updates, jokes | 0 | — | redirect only |

**Please confirm or change:** (a) the mapping above, (b) whether `puja-2020` should become an archive issue, (c) whether off-topic groups (amazing-facts, videos, coupon, game, quiz) should be hidden from Google in the Phase 2 content audit. Removing anything needs your approval.
