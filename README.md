# WP Glossary Tooltip

A powerful WordPress glossary plugin that automatically adds hover tooltips to defined terms throughout your content. Built with full Georgian language support including declension-aware matching.

**Author:** [Guram Zhgamadze](https://github.com/guramzhgamadze)  
**Repository:** [https://github.com/guramzhgamadze/glossary](https://github.com/guramzhgamadze/glossary)  
**License:** GPLv2 or later  
**Requires WordPress:** 6.0+  
**Requires PHP:** 8.0+  
**Tested up to:** 6.7  

---

## Features

- **Custom Glossary Post Type** — manage terms like any other WordPress content, with categories and tags
- **Automatic Tooltip Injection** — scans post content and wraps matching terms with accessible tooltip triggers; uses DOM parsing to avoid corrupting existing HTML
- **Georgian Declension Matching** — automatically matches all declined forms of Georgian words (e.g. სტრესი, სტრესს, სტრესისგან, სტრესებში all match the same term)
- **Synonyms / Aliases** — assign multiple trigger words per term
- **Tooltip Themes** — dark, light, or branded; solid background, auto-width with min/max constraints
- **Hover or Click** — configure how tooltips open
- **Glossary Index** — `[wpgt_glossary]` shortcode renders a full A–Z index with alphabet navigation
- **Live Search Widget** — `[wpgt_search]` renders an AJAX search box powered by the REST API
- **Single Term Box** — `[wpgt_term id="123"]` renders an inline definition card
- **Excel Import/Export** — bulk manage terms via `.xlsx` files; commas in definitions are handled correctly
- **REST API** — full read-only API at `/wp-json/wpgt/v1/`
- **Elementor Compatible** — tooltips work inside Elementor widgets; shortcodes render via the Elementor Shortcode widget
- **Accessible** — `role="tooltip"`, `aria-describedby`, full keyboard navigation (Tab, Escape, Arrow keys)
- **Mobile Friendly** — tooltip width clamps to viewport on small screens
- **Performance** — terms cached with the WordPress object cache; one DB query per page load

---

## Installation

1. Clone or download this repository
2. Upload the `wp-glossary-tooltip` folder to `/wp-content/plugins/`
3. Activate the plugin via **Plugins** in the WordPress admin
4. Go to **Glossary → Add New Term** to create your first term
5. Add `[wpgt_glossary]` to any page to display the full index
6. Adjust options under **Glossary → Settings**

---

## Shortcodes

### `[wpgt_glossary]`
Renders a full A–Z glossary index with alphabet navigation bar.

| Attribute | Default | Description |
|-----------|---------|-------------|
| `columns` | `3` | Number of columns (1–4) |
| `show_alphabet` | `true` | Show A–Z navigation bar |
| `category` | — | Filter by category slug (comma-separated for multiple) |
| `orderby` | `title` | Sort by `title` or `date` |

```
[wpgt_glossary columns="2" show_alphabet="true" category="yoga"]
```

### `[wpgt_term]`
Renders an inline definition card for a single term.

| Attribute | Description |
|-----------|-------------|
| `id` | Post ID of the glossary term |
| `slug` | Alternatively, the term's post slug |

```
[wpgt_term id="123"]
[wpgt_term slug="asana"]
```

### `[wpgt_search]`
Renders a live AJAX search widget.

| Attribute | Default | Description |
|-----------|---------|-------------|
| `placeholder` | `Search glossary…` | Input placeholder text |

```
[wpgt_search placeholder="Search terms…"]
```

---

## REST API

Base URL: `/wp-json/wpgt/v1/`

| Endpoint | Description |
|----------|-------------|
| `GET /terms` | Paginated list of published terms. Accepts `per_page`, `page`, `category`. |
| `GET /terms/{id}` | Single term by post ID |
| `GET /search?q={query}` | Search terms (minimum 2 characters) |

---

## Import / Export

Terms can be bulk-managed via Excel files:

- **Export** — go to **Glossary → Settings → Import/Export** and click **Download Excel (.xlsx)**
- **Import** — upload an `.xlsx` file with two columns: `word` and `explanation`; existing terms are updated, new ones are created

The Excel format handles commas, quotes, and special characters in definitions without any issues.

---

## Georgian Language Support

The plugin includes a dedicated stemmer (`class-georgian-stemmer.php`) that strips case endings, postpositions, plural markers, and the nominative `-ი` to extract the bare stem of a Georgian noun.

This means if you define the term **სტრესი**, it will automatically highlight all declined forms in your content:

| Form | Case |
|------|------|
| სტრესი | Nominative |
| სტრესს | Dative |
| სტრესმა | Ergative |
| სტრესის | Genitive |
| სტრესად | Adverbial |
| სტრესისგან | Ablative |
| სტრესებში | Plural locative |

A minimum stem length of 3 characters is enforced to prevent over-matching short words.

---

## File Structure

```
wp-glossary-tooltip/
├── wp-glossary-tooltip.php        # Plugin bootstrap
├── includes/
│   ├── class-post-type.php        # Custom post type & taxonomy registration
│   ├── class-settings.php         # Settings API wrapper
│   ├── class-georgian-stemmer.php # Georgian noun declension stemmer
│   ├── class-tooltip-parser.php   # DOM-based tooltip injection
│   ├── class-shortcodes.php       # Shortcode handlers
│   └── class-rest-api.php         # REST API endpoints
├── admin/
│   ├── class-admin.php            # Admin UI, settings, import/export
│   ├── admin.css
│   └── admin.js
└── public/
    ├── css/public.css             # Frontend tooltip styles
    └── js/public.js               # Frontend tooltip behaviour
```

---

## Changelog

### 1.0.6
- Switched import/export to Excel (.xlsx) format — no more comma issues in definitions
- Removed transparency/blur from tooltip — solid background only
- Tooltip width is now auto-sizing (`max-content`) with min 200px / max `min(500px, 100vw - 24px)`
- Mobile: tooltip capped to viewport width with 12px edge margin
- Georgian declension-aware matching via stem extraction
- Fixed partial word matching for Georgian text using Unicode word boundaries (`\pL`, `\pN`)
- Fixed HTML leaking into rendered content (collect-then-replace approach in DOM walker)
- Elementor: switched from `add_action` to `add_filter` for `elementor/frontend/the_content`

### 1.0.0
- Initial release

---

## License

GPLv2 or later — see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
