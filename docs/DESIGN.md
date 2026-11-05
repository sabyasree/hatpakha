# Design spec (binding)

Design canvas (all screens and the 12 covers): https://claude.ai/artifact/4iCfy5Pt1DJqTyC2DNUUcG
Chosen direction: **"পাখা খুলুক" (Design 1.0) in the logo palette, with the human touch** (D8). Design 2.0 was rejected; don't bring it back.

## Palette (from the logo; nothing else)
| Token | Hex | Use |
|---|---|---|
| `sky` | `#2DAAE2` | Primary accent, links on paper |
| `sky-light` | `#7CCBEE` | Tints, backgrounds |
| `sky-deep` | `#1A86C2` | Hover, focus rings |
| `sky-night` | `#12304A` | Dark text on light sky |
| `red` | `#CB1B32` | Brand, primary buttons (white text OK) |
| `red-deep` | `#B0162B` | Hover |
| `red-dark` | `#7A1022` | Dark accents |
| `pink` | `#F08D90` | Soft highlights (dark text only) |
| `peach` | `#EFC08D` | Warm panels (dark text only) |
| `peach-light` | `#FBEBD8` | Cards, letters |
| `peach-deep` | `#E39A6A` | Stamps, rules |
| `navy` | `#13212C` | Body text, dark surfaces (white text OK) |
| `paper` | `#FFFBF4` | Page background |
| `ink2`, `rule`, `sky-tint` | `#4D5A63`, `#E8DCCB`, `#DDF1FA` | Derived neutrals: secondary text, hairlines, pale sky panels |

Tokens are defined once in `resources/css/app.css` (`@theme`). **White text only on red or navy.** Gradients must start from red when they carry white text.

## Type
Anek Bangla (UI, headings) · Noto Serif Bengali (reading text) · Galada (logo-like display, sparingly). Body ≥ 18px on mobile, line-height ≈ 1.8 for prose, poems keep line breaks (`.poem-bn`).

## Human touch (keep it)
Slight tilts (`.tilt-a`, `.tilt-b`), paper grain/halftone, ruled-paper letters (`.lined`), hand-placed stamps, the fan motif (`components/fan`). Avoid perfectly symmetric grids, glossy gradients everywhere, and generic stock icons.

## Content-driven rules (from docs/DB-AUDIT.md)
- Featured image < 1200px wide → **title card** (`components/title-card`) instead of a stretched image.
- Long stories (story format) → story page with reading time and progress.
- Authors with < 3 posts, guests → compact profile; desk byline → desk page.

## Mobile
Design for 360px first. Tap targets ≥ 44px, no horizontal scroll, sticky bottom actions on post pages only.

## Copy
Bengali-first, plain words. Real content only, never placeholders or invented quotes.
