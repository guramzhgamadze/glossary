# WP Glossary Tooltip

A WordPress glossary plugin that automatically adds hover tooltips to defined terms throughout your content. Built with full Georgian language support including declension-aware matching across all 7 grammatical cases.

**Author:** [Guram Zhgamadze](https://github.com/guramzhgamadze)  
**Repository:** [https://github.com/guramzhgamadze/glossary](https://github.com/guramzhgamadze/glossary)  
**License:** GPLv2 or later  
**Requires WordPress:** 6.0+  
**Requires PHP:** 7.4+  
**Tested up to:** 6.7  
**Current Version:** 2.0.19

---

## Table of Contents

1. [Features](#features)
2. [Installation](#installation)
3. [Creating Glossary Terms](#creating-glossary-terms)
4. [Glossary Groups & Category Rules](#glossary-groups--category-rules)
5. [Settings Reference](#settings-reference)
6. [Shortcodes](#shortcodes)
7. [Styles Tab](#styles-tab)
8. [Import & Export](#import--export)
9. [REST API](#rest-api)
10. [Georgian Language Support](#georgian-language-support)
11. [File Structure](#file-structure)
12. [Changelog](#changelog)

---

## Features

- **Automatic Tooltip Injection** — scans post content and wraps matching terms with tooltip triggers; uses DOM parsing so existing HTML is never corrupted
- **Elementor Scope Guard** — on Elementor-built pages tooltips are injected only inside the Post Content widget, never into headers, footers, loop grids, related posts, or navigation widgets
- **Georgian Declension Matching** — matches all declined forms of a Georgian word automatically (nominative, dative, genitive, ergative, instrumental, adverbial, vocative + postpositions + plural)
- **Synonyms / Aliases** — assign multiple trigger words per term; all forms of each synonym are also matched
- **Glossary Groups** — tag-style taxonomy for grouping terms into logical sub-glossaries; combined with Category Rules to control which terms appear in which posts
- **Category Rules** — map WordPress post categories to Glossary Groups so only relevant terms are highlighted in each section of your site
- **Three Tooltip Themes** — Dark, Light, and Branded; solid background, auto-width
- **Hover or Click** — choose whether tooltips open on mouse hover or on click
- **"Read More" Link** — optional link inside the tooltip pointing to the full term page
- **Glossary Index** — `[wpgt_glossary]` shortcode renders a full A–Z index with alphabet navigation, compatible with Elementor letter archive templates
- **Letter Grid** — `[wpgt_letter_grid]` shortcode renders a clickable Georgian alphabet grid linking to letter archive pages
- **Live Search Widget** — `[wpgt_search]` shortcode renders an AJAX-powered search box with priority-ranked results (starts-with ranked above contains)
- **Single Term Card** — `[wpgt_term]` shortcode renders an inline definition card for one term
- **Visual Styles Editor** — full-screen two-panel Styles tab with live preview; controls every visual property of every component with no CSS required
- **Excel Import / Export** — bulk-manage all terms via `.xlsx` files
- **REST API** — full read-only JSON API at `/wp-json/wpgt/v1/`
- **Elementor Compatible** — shortcodes work via the Elementor Shortcode widget; letter archive pages work with Elementor Loop Grid
- **Accessible** — `role="tooltip"`, `aria-describedby`, full keyboard support (Tab, Escape, Arrow keys)
- **Mobile Friendly** — tooltip width clamps to viewport width on small screens; search input uses `font-size: 16px` on mobile to prevent iOS Safari auto-zoom
- **Performant** — all terms loaded once and cached; tokenisation-based matching with O(1) hash-map lookup
- **Per-Post Opt-Out** — a sidebar meta box on any post/page lets you disable tooltip injection for that specific piece of content

---

## Installation

1. Download or clone the repository
2. Upload the `wp-glossary-tooltip` folder to `/wp-content/plugins/`
3. Activate the plugin in **WordPress Admin → Plugins**
4. Navigate to **Glossary → Add New Term** to create your first term
5. Visit **Glossary → Settings** to configure appearance and behaviour
6. Add `[wpgt_glossary]` to any page to show the full glossary index
7. After activation, go to **Settings → Permalinks → Save Changes** to flush rewrite rules

---

## Creating Glossary Terms

Go to **Glossary → Add New Term** in the WordPress admin.

### Fields

| Field | Description |
|---|---|
| **Title** | The primary term name. This is the word the plugin will look for in your content. |
| **Content** | Full definition shown on the term's individual page. Supports the block editor. |
| **Excerpt** | Used as the tooltip text if no custom Tooltip Text is set. |
| **Tooltip Text** | Short text shown inside the tooltip bubble (1–2 sentences recommended). If empty, the Excerpt is used. If the Excerpt is also empty, the first 25 words of the Content are used. |
| **Synonyms / Aliases** | Comma-separated list of alternative words that should also trigger this tooltip. Each synonym is also morphologically declined (for Georgian). |
| **Loanword** | Check this for Sanskrit or other foreign loanwords. Uses a special declension engine: no syncope, limited truncation. |
| **Glossary Group** | Assign this term to one or more Glossary Groups. Only grouped terms are injected into post content (strict mode). |
| **Related Terms (IDs)** | Comma-separated post IDs of related terms. Currently stored for API use. |
| **Declined Forms** | Auto-generated from the title and synonyms when the term is saved. Shown in a collapsible details panel for inspection. |

### Tips

- Keep Tooltip Text short — 1–2 sentences. The tooltip bubble is small.
- If a term is very common (like "of" or "the"), it will not be indexed because the minimum stem length filter (3 characters) prevents over-matching.
- For Georgian terms, enter the nominative form (e.g. **სტრესი**). The plugin automatically generates and matches all other cases.
- After saving a term, its declined forms are automatically pre-computed and cached. If you change the title or synonyms, just save again.
- A term must have at least one Glossary Group assigned to it, otherwise it will never be injected (strict mode). If you have no Category Rules configured, all grouped terms are shown everywhere.

---

## Glossary Groups & Category Rules

This system lets you control exactly which glossary terms appear in which posts, based on the WordPress categories those posts belong to.

### Glossary Groups

Glossary Groups are a flat tag-style taxonomy (like WordPress tags, not categories). You can create them at **Glossary → Glossary Groups**.

Each term can belong to one or more groups. Terms with **no group assigned are never injected** — this is strict mode. If you want a term to appear everywhere, assign it to at least one group.

### Category Rules

Category Rules live at **Glossary → Settings → Category Rules**.

They map a WordPress post category to one or more Glossary Groups:

> **Category "Yoga Basics"** → Groups: `yoga`, `anatomy`  
> **Category "Philosophy"** → Groups: `philosophy`, `sanskrit`

When a post in "Yoga Basics" is loaded, only terms belonging to the `yoga` or `anatomy` groups will be highlighted in it. Terms in `philosophy` or `sanskrit` groups will be ignored for that post.

**If no Category Rules are configured:** all grouped terms are shown in all posts (backward-compatible mode).

---

## Settings Reference

Navigate to **Glossary → Settings** to find all options, organised into tabs.

---

### General Tab

| Setting | Default | Description |
|---|---|---|
| **Enable Tooltips** | On | Master switch. Uncheck to disable all tooltip injection site-wide without deactivating the plugin. |
| **Parse Post Types** | Post, Page | Which post types the plugin should scan for glossary terms. Tick any custom post types you want included. |
| **First Occurrence Only** | On | When on, each term is highlighted only the first time it appears on a page. Turn off to highlight every occurrence. |
| **Case Sensitive** | Off | When off, "Yoga", "yoga", and "YOGA" all match the same term. |
| **Exclude Headings** | On | When on, terms inside `<h1>`–`<h6>` tags are not highlighted. |
| **Exclude Links** | On | When on, terms already inside an `<a>` tag are not highlighted again. |

---

### Tooltip Tab

#### Behaviour

| Setting | Default | Description |
|---|---|---|
| **Open On** | Hover | `Hover` — tooltip appears on mouseover. `Click` — tooltip appears on click (better for mobile-heavy sites). |
| **Position** | Top | Whether the tooltip bubble prefers to appear above or below the trigger word. Flips automatically if there is not enough space. |
| **Show "Read More" Link** | On | Shows a link at the bottom of the tooltip pointing to the full glossary term page. |
| **Open Link in New Tab** | On | The "Read more" link opens in a new browser tab. |

#### Appearance

| Setting | Default | Description |
|---|---|---|
| **Theme** | Dark | Base colour palette. `Dark` = dark navy background. `Light` = white background. `Branded` = uses Brand Color as background. |
| **Brand / Underline Color** | `#2563eb` | Controls the dashed underline on trigger words, hover colour, and the Branded theme background. |
| **"Read More" Link Color** | _(theme default)_ | Override the colour of the "Read more" link inside the tooltip. |

---

### Index Page Tab

| Setting | Default | Description |
|---|---|---|
| **Glossary Slug** | `glossary` | The URL path for the glossary archive. After changing, flush rewrite rules via **Settings → Permalinks → Save**. |
| **Index Columns** | `3` | How many columns the `[wpgt_glossary]` shortcode uses for its term grid. Options: 1–4. |
| **Show A–Z Bar** | On | Whether the alphabet navigation bar is shown above the `[wpgt_glossary]` output. |

---

### Category Rules Tab

Map WordPress post categories to Glossary Groups. See [Glossary Groups & Category Rules](#glossary-groups--category-rules) above.

---

### Advanced Tab

Shows the REST API endpoint URLs for your installation and a button to flush WordPress rewrite rules manually.

---

### Import / Export Tab

See the [Import & Export](#import--export) section below.

---

### Styles Tab

A full-screen visual editor with a live preview panel on the right. Every visual property of every plugin component can be controlled here with no CSS required. Changes are previewed instantly; click **Save Styles** to persist them.

The Styles tab is organised into accordion cards:

| Card | Controls |
|---|---|
| **Global Font** | Choose a Google Font (or enter a custom font name) applied to all plugin elements |
| **Trigger Word** | Underline colour, hover colour, underline style/width, font weight/size/transform |
| **Tooltip Bubble** | Background, text/title/link colours, border, padding, radius, font size, line height, max width, shadow, "Read More" button text |
| **[wpgt_glossary] Index** | A–Z bar (background, border, link colours, radius, padding, justify), letter headings (colour, size, weight, borders), term cards (background, border, hover border, radius, shadow, padding), term name (colour, size, weight), excerpt (colour, size) |
| **[wpgt_term] — Term Box** | Background, left border colour/width, title colour/size/weight, definition colour/size, radius, padding, shadow |
| **[wpgt_search] — Search Widget** | Widget max-width, input height, input background/text/border/focus/radius/size/padding, placeholder text, icon colours, results dropdown background/height/radius/shadow, result item colours (text, hover bg, hover colour, title, excerpt, match highlight, no-results), separator colour, result padding |

#### How styles are applied to the frontend

Saved styles are output as an inline `<style>` block appended directly after `public.css` via `wp_add_inline_style()`. This guarantees they always load in the correct cascade order, independent of page caching plugins. All search widget rules use `!important` to beat any theme CSS that globally targets form inputs. Google Fonts are loaded via `wp_enqueue_style()` — never via raw `echo` — so they are output correctly in `<head>`.

#### Overriding with custom CSS

The Styles tab covers the most common cases. For anything beyond it, target CSS classes directly in **Appearance → Customize → Additional CSS** or your child theme's `style.css`. See [Styling & Customisation](#styling--customisation) below for class references.

---

## Shortcodes

### `[wpgt_glossary]` — Full Glossary Index

Renders a full A–Z glossary index with an alphabet navigation bar and a grid of term cards. When placed inside an Elementor letter archive template, it auto-detects the current letter and filters accordingly.

```
[wpgt_glossary]
```

**Attributes:**

| Attribute | Default | Accepts | Description |
|---|---|---|---|
| `columns` | `3` (from Settings) | `1` `2` `3` `4` | Number of columns in the term grid. Collapses automatically on mobile. |
| `show_alphabet` | `true` (from Settings) | `true` `false` | Show or hide the A–Z navigation bar. |
| `letter` | _(auto-detected)_ | Letter taxonomy slug e.g. `letter-a` | Show only terms for this letter. Auto-detected when shortcode is inside a letter archive template. |
| `orderby` | `menu_order` | `menu_order` `title` `date` | Sort by drag-to-reorder order, alphabetically, or by date (newest first). |

**Examples:**

```
[wpgt_glossary]
[wpgt_glossary columns="2"]
[wpgt_glossary show_alphabet="false" columns="1"]
[wpgt_glossary letter="letter-a"]
[wpgt_glossary orderby="title" columns="3"]
[wpgt_glossary orderby="date" columns="2"]
```

**Output structure:**

```html
<div class="wpgt-glossary-index" data-columns="3">
  <nav class="wpgt-alphabet-bar">
    <a href="/glossary-letter/letter-a/" class="wpgt-az-link">Ა</a>
    <!-- ... -->
  </nav>
  <section class="wpgt-letter-group" id="wpgt-letter-Ა">
    <h3 class="wpgt-letter-heading">Ა</h3>
    <ul class="wpgt-term-list wpgt-columns-3">
      <li class="wpgt-term-item">
        <a href="/glossary/asana/" class="wpgt-term-link">ასანა</a>
        <p class="wpgt-term-excerpt">A physical posture in yoga…</p>
      </li>
    </ul>
  </section>
</div>
```

---

### `[wpgt_letter_grid]` — Georgian Alphabet Grid

Renders the full Georgian alphabet as a clickable grid. Letters that have terms are rendered as links to their letter archive page. Letters with no terms are rendered as inactive spans.

```
[wpgt_letter_grid]
```

No attributes. The current letter is automatically highlighted when the shortcode is inside a letter archive template.

**Output structure:**

```html
<div class="wpgt-letter-grid">
  <a href="/glossary-letter/letter-a/" class="wpgt-letter-grid__item wpgt-letter-grid__item--active">ა</a>
  <span class="wpgt-letter-grid__item wpgt-letter-grid__item--empty">ბ</span>
  <!-- ... -->
</div>
```

**CSS classes:**

| Class | Meaning |
|---|---|
| `wpgt-letter-grid__item--active` | Letter has terms, rendered as a link |
| `wpgt-letter-grid__item--empty` | Letter has no terms, rendered as a span |
| `wpgt-letter-grid__item--current` | Currently viewed letter (set on archive pages) |

---

### `[wpgt_term]` — Single Term Card

Renders an inline definition card for one specific term.

```
[wpgt_term id="123"]
[wpgt_term slug="asana"]
```

**Attributes:**

| Attribute | Description |
|---|---|
| `id` | The WordPress post ID of the glossary term. |
| `slug` | The term's URL slug. Slower than `id` (requires a DB query by name). |

Either `id` or `slug` must be provided. If both are given, `id` takes priority.

**Output structure:**

```html
<div class="wpgt-term-box">
  <h4 class="wpgt-term-box__title">
    <a href="/glossary/pranayama/">Pranayama</a>
  </h4>
  <div class="wpgt-term-box__definition">
    <p>Breath control — the fourth limb of Ashtanga yoga…</p>
  </div>
</div>
```

---

### `[wpgt_search]` — Live Search Widget

Renders a search input. After 2 characters (280ms debounce), results are fetched from the REST API and shown in a dropdown. Results are **priority-ranked**: titles that start with the query appear first, followed by titles that contain the query elsewhere.

```
[wpgt_search]
[wpgt_search placeholder="Search yoga terms…"]
```

**Attributes:**

| Attribute | Default | Description |
|---|---|---|
| `placeholder` | Value from Styles tab, or `Search glossary…` | Placeholder text shown inside the input. The default can be set globally in **Styles → Search Widget → Placeholder Text** without touching each shortcode. |

**Keyboard navigation:**

- `↓` Arrow — move focus down into the results list
- `↑` / `↓` — navigate between results
- `Escape` — close the dropdown and clear the input
- `Enter` — follow the focused result link
- `Tab` — close the dropdown

**iOS Safari note:** The input always renders at `font-size: 16px` on screens ≤ 768px to prevent iOS Safari from auto-zooming when the field is tapped.

**Output structure:**

```html
<div class="wpgt-search-widget">
  <div class="wpgt-search-combobox">
    <span class="wpgt-search-icon">…</span>
    <input type="search" class="wpgt-search-input" placeholder="Search glossary…" />
    <button class="wpgt-search-clear" hidden>…</button>
  </div>
  <div class="wpgt-search-results" role="listbox">
    <a href="/glossary/asana/" class="wpgt-search-result-item" role="option">
      <span class="wpgt-search-result-title">Asana</span>
      <span class="wpgt-search-result-excerpt">A physical posture in yoga…</span>
    </a>
  </div>
</div>
```

---

## Styling & Customisation

The recommended approach is the **Styles tab** (see above). For advanced overrides, target CSS classes directly.

### CSS Custom Properties

```css
:root {
    --wpgt-brand:      #e85d4a;   /* underline + hover colour on trigger words */
    --wpgt-radius:     10px;      /* border-radius of the tooltip bubble */
    --wpgt-shadow:     0 4px 16px rgba(0,0,0,.25);
    --wpgt-transition: 0.25s ease;
}
```

### Trigger Words

```css
.wpgt-tooltip-trigger { border-bottom-color: #e85d4a; font-weight: 600; }
.wpgt-tooltip-trigger:hover,
.wpgt-tooltip-trigger:focus { color: #e85d4a; }
```

### Tooltip Bubble

```css
.wpgt-tooltip-bubble { border-radius: 10px; font-size: 0.9rem; }
.wpgt-tooltip-bubble .wpgt-tooltip-title { font-size: 1rem; }
.wpgt-tooltip-bubble .wpgt-tooltip-see-more { font-size: 0.75rem; }

/* Per-theme overrides */
.wpgt-theme-dark  { background: #0f172a !important; }
.wpgt-theme-light { border: 2px solid #e85d4a; }
```

### Glossary Index

```css
.wpgt-alphabet-bar    { background: #fff; border: 2px solid #e2e8f0; }
.wpgt-az-link         { border-radius: 50%; }
.wpgt-az-link:hover   { background: #e85d4a; }
.wpgt-letter-heading  { font-size: 2rem; color: #e85d4a; }
.wpgt-term-item       { background: #fff; border-left: 3px solid #e85d4a; }
.wpgt-term-link       { color: #1a1a1a; }
.wpgt-term-excerpt    { color: #888; }
```

### Letter Grid

```css
.wpgt-letter-grid                        { display: flex; flex-wrap: wrap; gap: 8px; }
.wpgt-letter-grid__item                  { width: 44px; height: 44px; text-align: center; }
.wpgt-letter-grid__item--active          { color: #e85d4a; font-weight: 600; }
.wpgt-letter-grid__item--current         { background: #e85d4a; color: #fff; border-radius: 50%; }
.wpgt-letter-grid__item--empty           { color: #ccc; cursor: default; }
```

### Term Box

```css
.wpgt-term-box               { border-left-color: #e85d4a; border-left-width: 6px; background: #fffbf5; }
.wpgt-term-box__title a      { color: #1a1a1a; }
.wpgt-term-box__definition   { color: #555; }
```

### Search Widget

```css
.wpgt-search-input        { border-radius: 30px; border-color: #d1d5db; }
.wpgt-search-input:focus  { border-color: #e85d4a; box-shadow: 0 0 0 3px rgba(232,93,74,.15); }
.wpgt-search-results      { border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,.12); }
.wpgt-search-result-item:hover { background: #fff5f3; color: #e85d4a; }
.wpgt-search-match        { color: #e85d4a; } /* highlighted portion of matched text */
```

### Responsive Breakpoints

| Breakpoint | Behaviour |
|---|---|
| `≤ 768px` | 3-col and 4-col grids collapse to 2 columns; search input `font-size` raised to 16px |
| `≤ 480px` | All multi-column grids collapse to 1 column |
| `≤ 600px` | Tooltip font size reduces slightly; padding tightens |
| All sizes | Tooltip bubble never exceeds `100vw - 24px` |

---

## Import & Export

Navigate to **Glossary → Settings → Import / Export**.

### Export

Click **Download Excel (.xlsx)** to download all published glossary terms. The file has two columns:

| word | explanation |
|---|---|
| Asana | A physical posture in yoga… |
| Pranayama | Breath control… |

### Import

Prepare an `.xlsx` file with `word` and `explanation` as column headers. Then:

1. Click **Choose File** and select your `.xlsx`
2. Click **Import Excel**
3. The plugin reports how many terms were **created** and how many were **updated**

**Rules:**
- Existing terms with the same title are **updated** (tooltip text replaced)
- New terms are **created** and published immediately
- The `word` column is required; `explanation` can be empty
- Declined forms are automatically regenerated for all imported terms

**Accepted header names** (case-insensitive):

- Word column: `word`, `title`, `term`, `name`
- Explanation column: `explanation`, `tooltip_text`, `definition`, `description`, `meaning`

If no recognised headers are found, column A = word, column B = explanation (no header row assumed).

---

## REST API

Base URL: `https://yoursite.com/wp-json/wpgt/v1/`

All endpoints are public and read-only.

### `GET /terms`

Returns a paginated list of all published glossary terms.

| Parameter | Default | Description |
|---|---|---|
| `per_page` | `20` | Number of terms per page |
| `page` | `1` | Page number |
| `category` | — | Filter by letter taxonomy slug (e.g. `letter-a`) |

Response headers: `X-WP-Total`, `X-WP-TotalPages`

### `GET /terms/{id}`

Returns a single term by WordPress post ID.

### `GET /search?q={query}`

Searches terms by relevance. Minimum query length: 2 characters (counted by Unicode character, so Georgian is handled correctly). Returns up to 10 results, ranked so that titles **starting with** the query appear before titles that merely **contain** it.

### Response Schema

```json
{
  "id": 123,
  "title": "ასანა",
  "slug": "asana",
  "tooltip_text": "A physical posture in yoga…",
  "excerpt": "Plain text excerpt…",
  "url": "https://yoursite.com/glossary/asana/",
  "synonyms": ["pose", "posture"],
  "categories": ["letter-a"],
  "is_loanword": false,
  "date": "2025-01-15 10:30:00",
  "modified": "2025-03-01 14:22:00"
}
```

---

## Georgian Language Support

The plugin includes a morphological engine (`class-georgian-stemmer.php`) that handles all three Georgian noun declension classes:

| Class | Description | Example |
|---|---|---|
| Class 1 | Consonant-final (ends in -ი) | სტრესი, ბჰაკტი, ასანა |
| Class 2 | Truncating vowel-final (ends in -ა or -ე) | მთა, გზა, ღმერთე |
| Class 3 | Non-truncating vowel-final (ends in -ო or -უ) | სიბრძნო, ბარდო |

For each term, the engine generates all declined forms — nominative, ergative, dative, genitive, instrumental, adverbial, vocative — plus all postposition combinations (-ში, -ზე, -თან, -იდან, -სთვის, -ისგან, -ამდე, -ივით, etc.) and plural forms. This produces **78–131 unique forms per term**, stored in the database at save time.

During content parsing, a tokeniser splits text into Georgian word tokens and looks each one up in a hash map (token → term ID) in O(1) time. **Performance does not degrade as the glossary grows.**

### Syncope

Some native Georgian words syncopate in certain forms (e.g. **წყალი** → **წყლის**). The engine handles this automatically. Sanskrit loanwords (e.g. **ბჰაკტი**, **ასანა**) do not syncopate — mark them as **Loanword** in the term editor and the engine uses a special path: no syncope, limited truncation for -ა stems.

### Minimum Stem Length

A minimum stem length of **3 characters** is enforced. Terms shorter than this will not be indexed. For very short terms, add a longer synonym.

---

## File Structure

```
wp-glossary-tooltip/
├── wp-glossary-tooltip.php            # Plugin bootstrap, main class, asset enqueuing, frontend style output
├── includes/
│   ├── class-post-type.php            # CPT, letter taxonomy, group taxonomy; declined form generation; parsing cache
│   ├── class-settings.php             # Settings option wrapper (get/update/defaults) + category rules
│   ├── class-georgian-stemmer.php     # Morphological engine: form generation + syncope
│   ├── class-tooltip-parser.php       # DOM walker: injects tooltip spans; Elementor scope guard
│   ├── class-shortcodes.php           # [wpgt_glossary], [wpgt_letter_grid], [wpgt_term], [wpgt_search]
│   └── class-rest-api.php             # REST endpoints: /terms, /terms/{id}, /search
├── admin/
│   ├── class-admin.php                # Settings UI, Styles tab, meta boxes, import/export, category rules
│   ├── admin.css                      # Admin page styles
│   └── admin.js                       # Tab navigation, colour picker init, drag-to-reorder
└── public/
    ├── css/public.css                 # All frontend styles (tooltip, index, search, term box, letter grid)
    └── js/public.js                   # Tooltip show/hide, positioning, search widget logic
```

---

## Changelog

### 2.0.19
- **Elementor scope guard rewrite** — replaced broken `is_built_with_elementor()` call (caused fatal error) with a clean two-flag approach using the official `elementor/frontend/before_render` action hook; tooltips are now correctly injected only inside the Post Content widget on Elementor pages, and work without restriction on classic/block-theme pages
- **Google Fonts loading fix** — replaced raw `echo` statements in `build_style_overrides()` with proper `wp_enqueue_style()` calls; `echo` inside `wp_enqueue_scripts` fired before WordPress output any HTML, corrupting page output in REST/AJAX contexts
- **`$post` undefined notice fix** — `[wpgt_term]` shortcode initialises `$post = null` before the `id`/`slug` branches so PHP never throws an "Undefined variable" notice when neither attribute is provided
- **Meta registration** — registered `_wpgt_declined_forms` and `_wpgt_skip_tooltips` with `register_post_meta()`; both were used in code but never registered; `_wpgt_skip_tooltips` now registered for all post types (`post_type = ''`)
- **`_wpgt_is_loanword` security fix** — added missing `auth_callback` to prevent unauthenticated REST API updates to the loanword flag

### 2.0.18 – 2.0.15
- **Glossary Groups taxonomy** — new flat tag-style taxonomy (`wpgt_group`) for grouping terms into logical sub-glossaries; group checkboxes added to the term editor meta box
- **Category Rules** — new Settings tab maps WordPress post categories to Glossary Groups; only terms whose groups overlap with the active groups for a post are highlighted (strict mode); terms with no group assigned are never injected
- **`[wpgt_letter_grid]` shortcode** — new shortcode renders the full Georgian alphabet as a clickable grid; letters with terms link to their archive page; current letter highlighted on archive pages
- **`[wpgt_glossary]` letter detection** — shortcode auto-detects the current `wpgt_letter` taxonomy term when placed inside an Elementor letter archive template
- **Per-post opt-out** — "Glossary Tooltips" sidebar meta box added to all public post types; checking "Skip tooltips" disables injection for that specific post without affecting others

### 2.0.14
- **Search ranking** — results now prioritise titles that start with the query before titles that merely contain it (two-pass SQL: `LIKE 'q%'` merged with `LIKE '%q%'`)

### 2.0.13
- **iOS Safari zoom fix** — search input uses `font-size: 16px` on screens ≤ 768px to prevent Safari auto-zoom on tap
- **Admin text fields** — Placeholder Text, "Read More" Text and all `wpgt-field-text` inputs no longer stretch full-width; they size to content (`width: auto; flex: 0 0 auto`)

### 2.0.12
- **Select dropdowns** — Style tab dropdowns (Transform, Shadow, Weight, etc.) no longer stretch full-width; removed from `flex: 1` group, set to `width: auto`

### 2.0.11 / 2.0.10 / 2.0.9
- Iterative fixes to admin select and text field widths (specificity cascade resolution)

### 2.0.8
- **Color picker z-index** — switched picker holder from `position: fixed` to `position: absolute` with `z-index: 99999` on the open container; pickers now open correctly in all accordion cards and no longer appear behind the save bar
- **Save bar z-index** — reduced from 99 to 9 so it no longer sits above open color pickers
- **Placeholder Text field** — added to Search Widget card in Styles tab; wired to live preview and frontend shortcode; overridable per-shortcode with `[wpgt_search placeholder="…"]`

### 2.0.7
- **Dotted border fix** — theme CSS was overriding `border-style` on `.wpgt-search-input`; added `border-style: solid !important` and `outline: none !important` to both `public.css` and the inline style override

### 2.0.6
- **Search widget styles now apply on frontend** — all search widget CSS rules use `!important` to beat theme CSS that globally targets `input` elements

### 2.0.5
- **Style overrides now always render** — moved from `wp_head` echo to `wp_add_inline_style()` (appended directly after `public.css`); removed early return when no styles saved so defaults always produce output; `build_style_overrides()` now receives merged `$s` array directly

### 2.0.4
- Styles tab JS selectors corrected (`#wpgt-panel-styles` throughout); Save bar pinned to panel bottom; Sort Terms tab removed from nav

### 2.0.3 / 2.0.2 / 2.0.1
- Layout fixes: WP adminbar and sidebar remain fully visible; plugin panels positioned with `left: 160px / 36px` (folded sidebar) rather than hiding WP chrome

### 2.0.0
- **Full Styles tab** — Elementor-style two-panel visual editor with live preview for all components (Trigger Word, Tooltip Bubble, Glossary Index, Term Box, Search Widget)
- **Global Font** selector with Google Fonts integration
- Admin UI rebuilt: fixed topbar navigation, accordion cards, drag-to-resize left panel

### 1.0.7
- Fixed syncopation for Sanskrit loanwords
- Added dative+სთვის postposition form
- Fixed CSS layout for Import/Export and Sort Terms panels

### 1.0.6
- Switched import/export to Excel (.xlsx)
- Solid tooltip backgrounds (removed transparency/blur)
- Replaced regex parsing with tokenisation + hash-map lookup
- Georgian declension-aware matching (78–131 forms per term)
- Fixed partial word matching with Unicode word boundaries

### 1.0.0
- Initial release

---

## License

GPLv2 or later — see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
