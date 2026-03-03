# WP Glossary Tooltip

A WordPress glossary plugin that automatically adds hover tooltips to defined terms throughout your content. Built with full Georgian language support including declension-aware matching across all 7 grammatical cases.

**Author:** [Guram Zhgamadze](https://github.com/guramzhgamadze)  
**Repository:** [https://github.com/guramzhgamadze/glossary](https://github.com/guramzhgamadze/glossary)  
**License:** GPLv2 or later  
**Requires WordPress:** 6.0+  
**Requires PHP:** 8.0+  
**Tested up to:** 6.7

---

## Table of Contents

1. [Features](#features)
2. [Installation](#installation)
3. [Creating Glossary Terms](#creating-glossary-terms)
4. [Settings Reference](#settings-reference)
5. [Shortcodes](#shortcodes)
6. [Styling & Customisation](#styling--customisation)
7. [Import & Export](#import--export)
8. [REST API](#rest-api)
9. [Georgian Language Support](#georgian-language-support)
10. [File Structure](#file-structure)
11. [Changelog](#changelog)

---

## Features

- **Automatic Tooltip Injection** — scans post content and wraps matching terms with tooltip triggers; uses DOM parsing so existing HTML is never corrupted
- **Georgian Declension Matching** — matches all declined forms of a Georgian word automatically (nominative, dative, genitive, ergative, instrumental, adverbial, vocative + postpositions + plural)
- **Synonyms / Aliases** — assign multiple trigger words per term; all forms of each synonym are also matched
- **Three Tooltip Themes** — Dark, Light, and Branded; solid background, auto-width
- **Hover or Click** — choose whether tooltips open on mouse hover or on click
- **"Read More" Link** — optional link inside the tooltip pointing to the full term page
- **Glossary Index** — `[wpgt_glossary]` shortcode renders a full A–Z index with alphabet navigation
- **Live Search Widget** — `[wpgt_search]` shortcode renders an AJAX-powered search box
- **Single Term Card** — `[wpgt_term]` shortcode renders an inline definition card for one term
- **Excel Import / Export** — bulk-manage all terms via `.xlsx` files
- **REST API** — full read-only JSON API at `/wp-json/wpgt/v1/`
- **Elementor Compatible** — tooltips work inside Elementor widgets; shortcodes work via the Elementor Shortcode widget
- **Accessible** — `role="tooltip"`, `aria-describedby`, full keyboard support (Tab, Escape, Arrow keys)
- **Mobile Friendly** — tooltip width clamps to viewport width on small screens
- **Performant** — all terms loaded once and cached; tokenisation-based matching with O(1) hash-map lookup

---

## Installation

1. Download or clone the repository
2. Upload the `wp-glossary-tooltip` folder to `/wp-content/plugins/`
3. Activate the plugin in **WordPress Admin → Plugins**
4. Navigate to **Glossary → Add New Term** to create your first term
5. Visit **Glossary → Settings** to configure appearance and behaviour
6. Add `[wpgt_glossary]` to any page to show the full glossary index

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
| **Synonyms / Aliases** | Comma-separated list of alternative words that should also trigger this tooltip. Example: `API, Application Programming Interface`. Each synonym is also morphologically declined (for Georgian). |
| **Related Terms (IDs)** | Comma-separated post IDs of related terms. Currently stored for API use. |
| **Categories** | Assign the term to one or more Glossary Categories. Used to filter `[wpgt_glossary]` output. |

### Tips

- Keep Tooltip Text short — 1–2 sentences. The tooltip bubble is small.
- If a term is very common (like "of" or "the"), it will not be indexed because the minimum stem length filter (3 characters) prevents over-matching.
- For Georgian terms, enter the nominative form (e.g. **სტრესი**). The plugin automatically generates and matches all other cases.
- After saving a term, its declined forms are automatically pre-computed and cached. If you change the title or synonyms, just save again.

---

## Settings Reference

Navigate to **Glossary → Settings** to find all options, organised into five tabs.

---

### General Tab

| Setting | Default | Description |
|---|---|---|
| **Enable Tooltips** | On | Master switch. Uncheck to disable all tooltip injection site-wide without deactivating the plugin. |
| **Parse Post Types** | Post, Page | Which post types the plugin should scan for glossary terms. Tick any custom post types you want included. |
| **First Occurrence Only** | On | When on, each term is highlighted only the first time it appears on a page. Subsequent occurrences are left as plain text. Turn off to highlight every occurrence. |
| **Case Sensitive** | Off | When off, "Yoga", "yoga", and "YOGA" all match the same term. Turn on if you need exact capitalisation matching. |
| **Exclude Headings** | On | When on, terms inside `<h1>`–`<h6>` tags are not highlighted. Recommended to keep headings clean. |
| **Exclude Links** | On | When on, terms that are already inside an `<a>` tag are not highlighted again, preventing nested links. |

---

### Tooltip Tab

#### Behaviour

| Setting | Default | Description |
|---|---|---|
| **Open On** | Hover | `Hover` — tooltip appears when the mouse moves over the word. `Click` — tooltip appears only when the word is clicked (better for mobile-heavy sites). |
| **Position** | Top | Whether the tooltip bubble prefers to appear above (`top`) or below (`bottom`) the trigger word. The plugin will flip automatically if there is not enough space. |
| **Show "Read More" Link** | On | Shows a "Read more →" link at the bottom of the tooltip pointing to the full glossary term page. |
| **Open Link in New Tab** | On | The "Read more" link opens in a new browser tab. |

#### Appearance

| Setting | Default | Description |
|---|---|---|
| **Theme** | Dark | Base colour palette. `Dark` = dark navy background with light text. `Light` = white background with dark text. `Branded` = uses your Brand Color as the background. |
| **Brand / Underline Color** | `#2563eb` | Controls the dashed underline under trigger words, hover colour, and the background of the Branded theme. |
| **"Read More" Link Color** | _(theme default)_ | Override the colour of the "Read more" link inside the tooltip. Leave blank to use the theme default. |

---

### Index Page Tab

| Setting | Default | Description |
|---|---|---|
| **Glossary Slug** | `glossary` | The URL path for the glossary archive, e.g. `yoursite.com/glossary/`. After changing this, go to **Settings → Permalinks** and click Save to flush rewrite rules. |
| **Index Columns** | `3` | How many columns the `[wpgt_glossary]` shortcode uses for its term grid. Options: 1–4. |
| **Show A–Z Bar** | On | Whether the alphabet navigation bar is shown above the `[wpgt_glossary]` output. |

---

### Advanced Tab

Shows the REST API endpoint URLs for your installation and a button to flush WordPress rewrite rules manually if needed.

---

### Import / Export Tab

See the [Import & Export](#import--export) section below.

---

## Shortcodes

### `[wpgt_glossary]` — Full Glossary Index

Renders a full A–Z glossary index with an alphabet navigation bar and a grid of term cards.

```
[wpgt_glossary]
```

**Attributes:**

| Attribute | Default | Accepts | Description |
|---|---|---|---|
| `columns` | `3` (from Settings) | `1` `2` `3` `4` | Number of columns in the term grid. Collapses to fewer columns automatically on mobile. |
| `show_alphabet` | `true` (from Settings) | `true` `false` | Show or hide the A–Z navigation bar at the top. |
| `category` | _(all)_ | Category slug | Show only terms belonging to this Glossary Category. Use comma-separated slugs for multiple categories. |
| `orderby` | `title` | `title` `date` | Sort terms alphabetically by title, or by date (newest first). |

**Examples:**

```
[wpgt_glossary]

[wpgt_glossary columns="2"]

[wpgt_glossary show_alphabet="false" columns="1"]

[wpgt_glossary category="yoga-basics"]

[wpgt_glossary category="yoga-basics,philosophy" columns="3"]

[wpgt_glossary orderby="date" columns="2"]
```

**Output structure:**

```html
<div class="wpgt-glossary-index" data-columns="3">

  <!-- A–Z navigation bar -->
  <nav class="wpgt-alphabet-bar">
    <a href="#wpgt-letter-A" class="wpgt-az-link">A</a>
    <a href="#wpgt-letter-B" class="wpgt-az-link">B</a>
    <!-- ... -->
  </nav>

  <!-- One section per starting letter -->
  <section class="wpgt-letter-group" id="wpgt-letter-A">
    <h3 class="wpgt-letter-heading">A</h3>
    <ul class="wpgt-term-list wpgt-columns-3">
      <li class="wpgt-term-item">
        <a href="/glossary/asana/" class="wpgt-term-link">Asana</a>
        <p class="wpgt-term-excerpt">A physical posture in yoga…</p>
      </li>
      <!-- more terms -->
    </ul>
  </section>

</div>
```

---

### `[wpgt_term]` — Single Term Card

Renders an inline definition card for one specific term. Useful for sidebar widgets, callout boxes, or highlighting a key term inside an article without interrupting the reading flow.

```
[wpgt_term id="123"]
[wpgt_term slug="asana"]
```

**Attributes:**

| Attribute | Description |
|---|---|
| `id` | The WordPress post ID of the glossary term. Find it in the URL when editing the term: `post.php?post=123`. |
| `slug` | The term's URL slug. Slower than using `id` (requires a database query by name). |

Either `id` or `slug` must be provided. If both are given, `id` takes priority.

**Examples:**

```
[wpgt_term id="456"]

[wpgt_term slug="pranayama"]
```

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

Renders a search input box. As the user types (after 2 characters, with a 280ms debounce), results are fetched from the REST API and shown in a dropdown. Results are clickable links to the full term page.

```
[wpgt_search]
[wpgt_search placeholder="Search yoga terms…"]
```

**Attributes:**

| Attribute | Default | Description |
|---|---|---|
| `placeholder` | `Search glossary…` | The placeholder text shown inside the search input. |

**Keyboard navigation:**

- `↓` Arrow — move focus from the input into the results list
- `↑` / `↓` — navigate between results
- `Escape` — close the dropdown and return focus to the input
- `Enter` — follow the focused result link

**Output structure:**

```html
<div class="wpgt-search-widget">
  <input type="search" class="wpgt-search-input" placeholder="Search glossary…" />
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

### Method 1: Settings Panel (No Code)

The quickest way to customise the look is via **Glossary → Settings → Tooltip tab**:

- Choose a **Theme**: Dark, Light, or Branded
- Set a **Brand Color** — this controls the underline on trigger words, the hover colour, and the Branded theme background
- Set a custom **"Read More" link color** to override the theme default

### Method 2: CSS Custom Properties

The plugin exposes CSS custom properties on `:root` that you can override in your theme's stylesheet or in **Appearance → Customize → Additional CSS**:

```css
:root {
    --wpgt-brand:      #e85d4a;   /* underline + hover colour on trigger words */
    --wpgt-radius:     10px;      /* border-radius of the tooltip bubble */
    --wpgt-shadow:     0 4px 16px rgba(0,0,0,.25); /* tooltip drop shadow */
    --wpgt-transition: 0.25s ease; /* fade/slide animation speed */
}
```

### Method 3: Targeting CSS Classes Directly

Every element the plugin outputs has a stable CSS class. Add overrides in **Appearance → Customize → Additional CSS** or your child theme's `style.css`.

#### Trigger Words (highlighted terms in content)

```css
/* The underlined term in body text */
.wpgt-tooltip-trigger {
    border-bottom-color: #e85d4a;
    font-weight: 600;
}

/* Trigger word when hovered/focused */
.wpgt-tooltip-trigger:hover,
.wpgt-tooltip-trigger:focus {
    color: #e85d4a;
    background: rgba(232, 93, 74, 0.08);
    border-radius: 2px;
}
```

#### Tooltip Bubble

```css
/* The popup bubble itself */
.wpgt-tooltip-bubble {
    border-radius: 10px;
    font-size: 0.9rem;
}

/* Term title inside the tooltip */
.wpgt-tooltip-bubble .wpgt-tooltip-title {
    font-size: 1rem;
    letter-spacing: 0.01em;
}

/* Definition text inside the tooltip */
.wpgt-tooltip-bubble .wpgt-tooltip-text {
    opacity: 0.9;
}

/* "Read more →" link */
.wpgt-tooltip-bubble .wpgt-tooltip-see-more {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
```

#### Per-Theme Overrides

Target a specific theme without affecting the others:

```css
/* Override dark theme only */
.wpgt-theme-dark {
    background: #0f172a !important;  /* darker background */
}
.wpgt-theme-dark .wpgt-tooltip-title {
    color: #fbbf24;  /* gold title */
}

/* Override light theme only */
.wpgt-theme-light {
    border: 2px solid #e85d4a;
}

/* Override branded theme only */
.wpgt-theme-branded .wpgt-tooltip-see-more {
    text-decoration: underline;
    font-weight: 700;
}
```

#### Glossary Index (`[wpgt_glossary]`)

```css
/* The outer wrapper */
.wpgt-glossary-index { margin: 2em 0; }

/* A–Z navigation bar */
.wpgt-alphabet-bar {
    background: #fff;
    border: 2px solid #e2e8f0;
    padding: 12px 16px;
}

/* Individual letter buttons in the A–Z bar */
.wpgt-az-link {
    width: 36px;
    height: 36px;
    font-size: 0.9rem;
    border-radius: 50%;  /* make them circular */
}

.wpgt-az-link:hover,
.wpgt-az-link:focus {
    background: #e85d4a;
}

/* Section heading (A, B, C…) */
.wpgt-letter-heading {
    font-size: 2rem;
    color: #e85d4a;
    border-bottom-color: #e85d4a;
}

/* Individual term card */
.wpgt-term-item {
    background: #fff;
    border-left: 3px solid #e85d4a;
    border-top: none;
    border-right: none;
    border-bottom: none;
    border-radius: 0;
    padding: 10px 14px;
}

/* Term name link */
.wpgt-term-link {
    color: #1a1a1a;
    font-size: 1rem;
}

/* Short description below term name */
.wpgt-term-excerpt {
    color: #888;
}
```

#### Single Term Card (`[wpgt_term]`)

```css
/* The card container */
.wpgt-term-box {
    border-left-color: #e85d4a;
    border-left-width: 6px;
    background: #fffbf5;
    border-radius: 6px;
}

/* Term title */
.wpgt-term-box__title a {
    color: #1a1a1a;
    font-size: 1.1rem;
}

/* Definition text */
.wpgt-term-box__definition {
    color: #555;
    font-size: 0.95rem;
}
```

#### Search Widget (`[wpgt_search]`)

```css
/* Search input field */
.wpgt-search-input {
    border-radius: 30px;      /* pill-shaped input */
    padding: 12px 20px;
    font-size: 1rem;
    border-color: #d1d5db;
}

.wpgt-search-input:focus {
    border-color: #e85d4a;
    box-shadow: 0 0 0 3px rgba(232, 93, 74, 0.15);
}

/* Dropdown results panel */
.wpgt-search-results {
    border-radius: 12px;
    border-color: #e2e8f0;
    box-shadow: 0 8px 32px rgba(0,0,0,.12);
}

/* Individual result row */
.wpgt-search-result-item:hover,
.wpgt-search-result-item:focus {
    background: #fff5f3;
    color: #e85d4a;
}

/* Result title */
.wpgt-search-result-title {
    font-size: 0.95rem;
}

/* Result excerpt */
.wpgt-search-result-excerpt {
    color: #999;
}
```

### Responsive Breakpoints

The plugin's built-in responsive rules:

| Breakpoint | Behaviour |
|---|---|
| `≤ 768px` | 3-column and 4-column grids collapse to 2 columns |
| `≤ 480px` | All multi-column grids collapse to 1 column |
| `≤ 600px` | Tooltip font size reduces slightly; padding tightens |
| All sizes | Tooltip bubble never exceeds `100vw - 24px` |

---

## Import & Export

Navigate to **Glossary → Settings → Import / Export**.

### Export

Click **Download Excel (.xlsx)** to download all published glossary terms as a spreadsheet. The file has two columns:

| word | explanation |
|---|---|
| Asana | A physical posture in yoga… |
| Pranayama | Breath control… |

Open in Microsoft Excel, Google Sheets, or LibreOffice Calc.

### Import

Prepare an `.xlsx` file with two columns in row 1 as headers: `word` and `explanation`. Then:

1. Click **Choose File** and select your `.xlsx`
2. Click **Import Excel**
3. The plugin reports how many terms were **created** and how many were **updated**

**Rules:**
- If a term with the same title already exists, it is **updated** (tooltip text replaced)
- If no matching term exists, a new one is **created** and published immediately
- The `word` column is required; `explanation` can be empty
- After import, declined forms are automatically regenerated for all Georgian terms

**Accepted column header names** (case-insensitive):

For the word column: `word`, `title`, `term`, `name`  
For the explanation column: `explanation`, `tooltip_text`, `definition`, `description`, `meaning`

If no recognised headers are found, the plugin assumes column A = word, column B = explanation (no header row).

---

## REST API

Base URL: `https://yoursite.com/wp-json/wpgt/v1/`

All endpoints are public and read-only (no authentication required).

### `GET /terms`

Returns a paginated list of all published glossary terms.

**Query parameters:**

| Parameter | Default | Description |
|---|---|---|
| `per_page` | `20` | Number of terms per page |
| `page` | `1` | Page number |
| `category` | — | Filter by Glossary Category slug |

**Example:**
```
GET /wp-json/wpgt/v1/terms?per_page=10&page=2
GET /wp-json/wpgt/v1/terms?category=yoga-basics
```

**Response headers:**
- `X-WP-Total` — total number of matching terms
- `X-WP-TotalPages` — total number of pages

---

### `GET /terms/{id}`

Returns a single term by its WordPress post ID.

**Example:**
```
GET /wp-json/wpgt/v1/terms/123
```

---

### `GET /search?q={query}`

Searches terms by relevance. Minimum query length: 2 characters. Returns up to 10 results.

**Example:**
```
GET /wp-json/wpgt/v1/search?q=yoga
```

---

### Response Schema

All endpoints return terms in this format:

```json
{
  "id": 123,
  "title": "Asana",
  "slug": "asana",
  "tooltip_text": "A physical posture in yoga…",
  "content": "<p>Full HTML content of the term page…</p>",
  "excerpt": "Plain text excerpt…",
  "url": "https://yoursite.com/glossary/asana/",
  "synonyms": ["pose", "posture"],
  "categories": ["Yoga Basics", "Sanskrit"],
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

For each term, the engine generates all declined forms — nominative, ergative, dative, genitive, instrumental, adverbial, vocative — plus all postposition combinations (-ში, -ზე, -თან, -იდან, -სთვის, -ისგან, -ამდე, -ივით, etc.) and plural forms with their full postposition set. This produces **78–131 unique forms per term**, all stored in the database at save time.

During content parsing, a tokeniser splits the page text into Georgian word tokens and looks each one up in a hash map (token → term ID) in O(1) time. This means **performance does not degrade** as the glossary grows.

### Example: ბჰაკტი

Defining the term **ბჰაკტი** will automatically highlight all of these forms wherever they appear:

| Form | Description |
|---|---|
| ბჰაკტი | Nominative |
| ბჰაკტმა | Ergative |
| ბჰაკტს | Dative |
| ბჰაკტის | Genitive |
| ბჰაკტით | Instrumental |
| ბჰაკტად | Adverbial |
| ბჰაკტო | Vocative |
| ბჰაკტში | Locative (dative + -ში) |
| ბჰაკტზე | Superessive (dative + -ზე) |
| ბჰაკტსთან | Comitative |
| ბჰაკტსთვის | Benefactive (dative + -სთვის) |
| ბჰაკტისგან | Ablative |
| ბჰაკტისთვის | Benefactive (genitive + -თვის) |
| ბჰაკტიდან | Elative |
| ბჰაკტები | Plural nominative |
| ბჰაკტებში | Plural locative |
| ბჰაკტებისგან | Plural ablative |
| … | All other plural postposition forms |

### Syncope

Some native Georgian words syncopate (drop a vowel) in certain forms, e.g. **წყალი** → **წყლის** (genitive). The engine handles this automatically. Sanskrit loanwords like **ბჰაკტი** or **ასანა** do not syncopate — the engine detects this by checking whether the syncopated stem would produce 4 or more consecutive consonants, which is not a valid Georgian stem.

### Minimum Stem Length

A minimum stem length of **3 characters** is enforced. Terms shorter than this (e.g. 2-character abbreviations) will not be indexed to prevent over-matching common syllables. If you have very short terms, add a longer synonym.

### After Saving Terms

When you save or update a glossary term, its declined forms are automatically regenerated. If you make bulk changes via Import, the forms are regenerated for every imported term immediately.

If you ever need to regenerate all declined forms manually (e.g. after a plugin update that improves the morphology engine), go to **Glossary → Settings → Sort Terms** and click **Regenerate All Declined Forms**.

---

## File Structure

```
wp-glossary-tooltip/
├── wp-glossary-tooltip.php            # Plugin bootstrap, main class, asset enqueuing
├── includes/
│   ├── class-post-type.php            # Custom post type & taxonomy; declined form generation
│   ├── class-settings.php             # Settings option wrapper (get/update/defaults)
│   ├── class-georgian-stemmer.php     # Morphological engine: form generation + syncope
│   ├── class-tooltip-parser.php       # DOM walker: injects tooltip spans into page content
│   ├── class-shortcodes.php           # [wpgt_glossary], [wpgt_term], [wpgt_search]
│   └── class-rest-api.php             # REST endpoints: /terms, /terms/{id}, /search
├── admin/
│   ├── class-admin.php                # Settings UI, meta boxes, columns, import/export
│   ├── admin.css                      # Admin page styles
│   └── admin.js                       # Tab navigation, WP colour picker init
└── public/
    ├── css/public.css                 # All frontend styles (tooltip, index, search, term box)
    └── js/public.js                   # Tooltip show/hide, positioning, search widget logic
```

---

## Changelog

### 1.0.7
- Fixed syncopation for Sanskrit loanwords — terms like ბჰაკტი no longer generate phantom ბჰკტ- forms
- Added dative+სთვის postposition form (e.g. ბჰოგასთვის, ბჰაკტსთვის) to form generation for Class 1 and Class 2 nouns
- Fixed CSS layout: Import/Export and Sort Terms tab panels now correctly render inside the settings container

### 1.0.6
- Switched import/export to Excel (.xlsx) format — no more comma issues in definitions
- Removed transparency/blur from tooltip — solid background only
- Tooltip width is now auto-sizing (`max-content`) with min 200px / max `min(500px, 100vw - 24px)`
- Mobile: tooltip capped to viewport width with 12px edge margin
- Replaced regex-based content parsing with tokenisation + hash-map lookup — scales to any glossary size without performance degradation
- Georgian declension-aware matching: generates 78–131 forms per term at save time
- Fixed partial word matching using Unicode word boundaries
- Fixed HTML leaking into rendered content
- Elementor: switched from `add_action` to `add_filter` for `elementor/frontend/the_content`

### 1.0.0
- Initial release

---

## License

GPLv2 or later — see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
