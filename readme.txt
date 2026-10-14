=== Niche Clever Interactive ===
Contributors: nicheclever
Tags: question and answer, openai, file search, knowledge base, interactive
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a configurable question-and-answer panel grounded in material you prepare in an OpenAI File Search vector store.

== Description ==

Niche Clever Interactive adds one configurable public question-and-answer panel to a WordPress site. Visitors can ask questions in ordinary language and receive answers generated through the site administrator's own OpenAI Responses API and File Search configuration.

The plugin is designed for organisations and practitioners who have prepared material that is suitable for visitors to explore. It provides one knowledge profile, three panel styles, configurable visitor-facing copy, bounded owner response guidance, a connection test and daily request safeguards.

Niche Clever Interactive does not create or manage a vector store, upload documents, store an API key in WordPress options, publish provider citations or source links, or maintain a server-side public conversation archive.

This RC8 package is a local release candidate. It is not a WordPress.org submission, directory listing or public 1.0.0 release.

== Installation ==

1. Install the plugin ZIP through the WordPress Plugins screen.
2. Activate Niche Clever Interactive.
3. Define `NCLI_OPENAI_API_KEY_DEFAULT` in `wp-config.php` or equivalent secure server configuration. Do not enter an API key into WordPress settings.
4. In **NCI Interactive**, enter the reference for an existing OpenAI File Search vector store and a corpus version.
5. Save the settings and run the administrator-only connection test.
6. Enable public answers once the current connection test has passed.
7. Add the **NCI Interactive** block or `[ncli_interactive]` shortcode to a published page.

== Frequently Asked Questions ==

= Does Niche Clever Interactive upload or index documents? =

No. Create and manage the vector store through your own OpenAI account, then enter its existing reference in the plugin.

= Where does the API key go? =

Keep it in `NCLI_OPENAI_API_KEY_DEFAULT` in server configuration. The plugin does not store or display the key in WordPress.

= What information is sent to OpenAI? =

A visitor question and relevant current-browser conversation context are sent to OpenAI when the enabled public panel is used. File Search may use material in the configured vector store to generate the answer.

= Why is the panel unavailable after a settings change? =

Changing the key, transport mode, vector-store reference, corpus version, response guidance or response preset requires a fresh successful connection test before public answers are available again.

= Does Niche Clever Interactive have a Premium edition? =

NCI Pro is planned as a separately supplied extension. This package includes no Pro implementation and makes no availability claim.

== Screenshots ==

1. Niche Clever Interactive Setup tab and readiness checklist.
2. AI & Corpus configuration with accessible field guidance.
3. Help Centre with the Core and planned NCI Pro comparison.
4. Classic, Minimal and Dark public panel styles.

== Changelog ==

= 1.0.0-rc.8 =

* Submission-readiness candidate: public Niche Clever Interactive identity, translation-ready interface strings, expanded Help Centre, accessible technical-field guidance, privacy-policy suggestion and a transparent Core/planned-Pro comparison without an availability claim.

= 1.0.0-rc.7 =

* Local-only bounded-response-guidance correction: saved owner response guidance now affects only response style and format, while Core retrieval, safety and disclosure rules remain non-negotiable. Concise, Balanced and Detailed now provide Core-defined response-detail baselines. Changing either requires a fresh administrator connection test.

= 1.0.0-rc.6 =

* Local-only presentation correction: answers now render a constrained, DOM-built Markdown subset without accepting provider HTML or creating provider-derived links.

= 1.0.0-rc.5 =

* Local-only Core boundary correction. Core now answers directly from the configured File Search corpus after a completed retrieval. The approved evidence map, citation metadata and public source links have been removed from Core.
