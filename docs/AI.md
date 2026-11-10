# AI review: setup and rules (Phase 3d/3e)

**Rule: AI advises editors; it never publishes, rejects or emails anyone** (CLAUDE.md §2). Writers never see AI output directly; an editor may put suggestions in their own message.

## What happens
1. A writer submits → after their page is sent, `ReviewSubmission` asks the AI for a report (no queue worker or cron needed).
2. Editors see **AI ৭/১০** in /desk → লেখা and open **AI রিপোর্ট**: score 1–10 against the rubric, summary, strengths, 3 suggestions for the writer, spelling, suggested section, ≤5 tags, SEO title/description, policy flags (hate, sexual, violence, defamation, spam links, personal data, possible plagiarism), fit for the magazine.
3. Editors can (re)run it with **AI দিয়ে পড়ান**. The decision buttons stay with the editor.
4. Admins edit the **rubric** and see this month's spend in /desk → **AI সহকারী**.

## Switching it on (owner)
Add to the `ENV_FILE` secret (staging first), never to git or chat:
```
AI_ENABLED=true
AI_PROVIDER=anthropic          # winner of the blind test
ANTHROPIC_API_KEY=…            # console.anthropic.com → API keys
# For the blind test only:
OPENAI_API_KEY=…
OPENAI_REVIEW_MODEL=…          # the OpenAI model to compare (check OpenAI's model list)
OPENAI_PRICE_IN=… OPENAI_PRICE_OUT=… OPENAI_PRICE_CACHED=…   # USD per 1M tokens from OpenAI's pricing page
```
Optional: `ANTHROPIC_REVIEW_MODEL` (default `claude-sonnet-5-5`, per the plan), `ANTHROPIC_REVIEW_EFFORT` (default `medium`), `AI_MIN_WORDS` (default 20).

## Cost guards
- Off unless `AI_ENABLED=true` **and** a key is set; nothing is sent otherwise.
- Pieces under `AI_MIN_WORDS` are never sent.
- No spending cap (owner, D37). An admin pauses or resumes AI with one button in /desk → এআই সহকারী (or the অ্যাডমিন box on আজকের কাজ); while paused, new reports are skipped and the review queue works as normal. Every pause/resume is in the audit log. Spend is still logged per call and shown on that page.
- Every call's tokens and cost are logged in `ai_usages` (prices in `config/ai.php`; update when providers change prices).
- The rubric is cached (cheaper repeat calls); writers can submit 3 pieces/day.
- Planning estimate (PLAN.md §4): ₹1,300–1,800/month at 400 submissions/month.

## Blind test (3e): choosing the provider
On the staging server, after the keys above are set:
```
php artisan hatpakha:ai-blind-test            # 30 real backlog pieces; shows an estimate, stops above $6 (≈ ₹500)
php artisan hatpakha:ai-blind-test --count=10 # smaller run
```
Then in /desk → **AI পরীক্ষা**, score each anonymous report A/B from 1 to 5 (most useful to an editor = 5). After every report is scored, the page reveals which provider wrote which and the averages. Put the winner in `AI_PROVIDER`.

## Safety
- The submission is sent to the AI wrapped in `<submission>` and labelled as data; instructions inside a piece are ignored and reported.
- Report values are clamped (score 1–10, ≤3 suggestions, ≤5 tags, known flags only) and escaped when shown.
- Claude calls use the server-side refusal fallback; a refusal or error just leaves the piece for normal human review.

## AI editor (D42/D43)
The AI works as the desk editor under the owner. It prepares; a person approves.
- **Per piece** (new submissions automatically; old pending via `hatpakha:ai-editor --backlog`): report, copy-edits, recommended decision, note to the writer, teaser, tags, Google title/description. Copy-edits are short exact find → replace pairs applied by `App\Ai\CopyEdit`; the server re-labels anything bigger than a few characters as a "word" change (never ticked by default; the writer confirms it).
- **Pictures**: `App\Ai\Illustrator` (Claude small model writes a scene → OpenAI image model). Settings: `OPENAI_IMAGE_MODEL` (required, exact name from OpenAI's models page), `OPENAI_IMAGE_SIZE` (1536x1024), `OPENAI_IMAGE_QUALITY` (medium), `OPENAI_IMAGE_PRICE` (USD per image, for the spend log).
- **Monthly issue**: `App\Ai\IssuePlanner` (calendar → theme options; picks 6–15 recent pieces) and `App\Ai\EditorialWriter` (draft under the owner's name).
- **Comments**: `App\Ai\CommentScreen` (small model): ok / spam / check.
- Every call is logged in `ai_usages` with its cost; the pause button stops all of it.
