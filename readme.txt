=== WP Glossary Tooltip ===
Contributors: yourname
Tags: glossary, tooltip, dictionary, definitions, terms
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful glossary plugin that automatically adds hover tooltips to defined terms throughout your content.

== Description ==

**WP Glossary Tooltip** lets you build a full glossary of terms and automatically highlights those terms anywhere they appear in your posts and pages — with a beautiful tooltip showing the definition on hover or click.

= Key Features =

* **Custom Glossary Post Type** — manage your terms like any other WordPress content, with categories and tags.
* **Automatic Tooltip Injection** — the plugin scans your post content and wraps matching terms with accessible tooltip triggers. Uses DOM parsing to avoid corrupting existing HTML.
* **Synonyms / Aliases** — assign multiple trigger words per term (e.g. "API" and "Application Programming Interface").
* **Tooltip Customisation** — choose dark, light, or branded themes; set position (top/bottom/left/right); configure hover vs. click behaviour.
* **Glossary Index Shortcode** — `[wpgt_glossary]` renders a beautiful A–Z index page with alphabet navigation.
* **Search Widget** — `[wpgt_search]` renders a live AJAX search box powered by the REST API.
* **Single Term Box** — `[wpgt_term id="123"]` renders an inline definition card.
* **REST API** — full read-only REST API at `/wp-json/wpgt/v1/` for headless or decoupled use.
* **Accessibility** — all tooltips use `role="tooltip"`, `aria-describedby`, and full keyboard navigation (Tab, Escape, Arrow keys).
* **Performance** — terms are cached with the WordPress object cache; only one DB query per page load.

= Shortcodes =

`[wpgt_glossary]`
Full A–Z glossary index.

Attributes:
* `columns` — number of columns (1–4, default: 3)
* `show_alphabet` — show A–Z navigation bar (true/false, default: true)
* `category` — filter by category slug (comma-separated for multiple)
* `orderby` — `title` (default) or `date`

`[wpgt_term id="123"]`
Inline definition box for a single term.

Attributes:
* `id` — post ID of the glossary term
* `slug` — alternatively, the term's post slug

`[wpgt_search]`
Live AJAX search widget.

Attributes:
* `placeholder` — input placeholder text

= REST API =

* `GET /wp-json/wpgt/v1/terms` — paginated list of published terms
* `GET /wp-json/wpgt/v1/terms/{id}` — single term by ID
* `GET /wp-json/wpgt/v1/search?q={query}` — search terms (min 2 characters)

== Installation ==

1. Upload the `wp-glossary-tooltip` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu.
3. Go to **Glossary → Add New Term** to create your first term.
4. Add `[wpgt_glossary]` to any page to display the full index.
5. Adjust options under **Glossary → Settings**.

== Frequently Asked Questions ==

= Can I control which post types get tooltips? =
Yes. Under **Settings → General**, check or uncheck any registered public post type.

= Will it break my existing links or HTML? =
No. The parser uses PHP's DOMDocument to walk text nodes only, and skips content inside `<a>`, `<code>`, `<pre>`, `<script>`, and `<style>` tags by default.

= Can I show the tooltip on click instead of hover? =
Yes. Go to **Settings → Tooltip** and change "Open On" to "Click".

= How do synonyms work? =
In the term editor, enter a comma-separated list of synonyms in the "Synonyms / Aliases" field. All of those words will also trigger the tooltip.

= Is it compatible with page builders? =
The frontend script exposes `window.wpgtRebind()` which you can call after dynamic content is injected to bind tooltips to newly added triggers.

== Screenshots ==

1. Tooltip displayed on hover over a defined term in a post.
2. A–Z glossary index rendered by the `[wpgt_glossary]` shortcode.
3. Glossary term editor with tooltip text and synonyms fields.
4. Settings panel showing tooltip theme and behaviour options.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
