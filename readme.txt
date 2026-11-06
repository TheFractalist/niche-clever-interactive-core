=== Niche Clever Interactive ===
Contributors: jacobfractalist, nicheclever
Tags: question and answer, openai, file search, knowledge base, interactive
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a configurable question-and-answer panel grounded in material you prepare in an OpenAI File Search vector store.

== Description ==

Niche Clever Interactive adds one configurable public question-and-answer panel to a WordPress site. Visitors can ask ordinary-language questions and receive answers generated through the site administrator's own OpenAI Responses API and File Search configuration.

Core provides one knowledge profile, three panel styles, configurable visitor wording, a connection test, daily request safeguards and optional, off-by-default conversation history. It does not create or manage vector stores, upload documents, store an API key in WordPress options, or publish provider citations or source links.

NCI Premium is a separate informational companion. This plugin contains no Premium code and nothing in Core is locked, limited or time-restricted. Its page records intended scope only; it makes no purchase, download, pricing or availability promise: https://www.nicheclever.co.uk/nc-interactive-premium/.

== External services ==

This plugin connects to the OpenAI API (`api.openai.com`) to generate answers. You need your own OpenAI account, API key and File Search vector store.

* **What is sent:** the current visitor question, together with your configured instructions and settings. File Search may use material in your vector store. The connection test sends a short test request.
* **When:** only when a visitor asks a question in an enabled panel or an administrator runs the connection test.
* **Your account:** requests use and are billed to your OpenAI account. The plugin does not send data to Niche Clever.
* **Terms and privacy:** https://openai.com/policies/terms-of-use/ and https://openai.com/policies/privacy-policy/

Tell visitors in your own privacy notice that questions are processed by OpenAI and, if enabled, that conversation history is stored on your site.

== Installation ==

1. Install the plugin ZIP and activate it.
2. Define `NCLI_OPENAI_API_KEY_DEFAULT` in `wp-config.php` or equivalent secure server configuration. Do not enter an API key into WordPress settings.
3. In **NC Interactive**, enter an existing File Search vector-store reference and corpus version, save, and run the administrator-only connection test.
4. Enable public answers once the current test passes, then add the **NC Interactive** block or `[ncli_interactive]` shortcode to a published page.

== Frequently Asked Questions ==

= Does it upload or index documents? =

No. Create and manage the vector store in your own OpenAI account, then enter its existing reference in Core.

= Where does the API key go? =

Keep it in `NCLI_OPENAI_API_KEY_DEFAULT` in server configuration. Core does not store or display it in WordPress.

= Does it record visitor questions? =

Not unless you enable optional conversation history. It is off by default; if enabled, you choose retention and can review or delete records under **NC Interactive > Conversations**. No IP address, account or browser identifier is stored with a record.

= Can I show a credit link? =

The optional “Powered by NC Interactive” link is off by default and appears only if you enable it in Appearance & Limits.

= Does it send analytics anywhere? =

No. The panel can announce milestones in the visitor's browser as an `ncli:analytics` event for a consent-managed listener you add yourself. Core sends nothing to an analytics service, stores nothing, and never includes question text, answer text, page paths, query values or browser identifiers. Events carry only validated profile/authored identifiers, question type, response source and a general error category.

= Can visitors ask follow-up questions? =

On its own, Core sends each question separately. The NCI Premium information page records a possible companion scope for limited earlier-turn context.

= Does it show citations? =

No. Core never publishes provider citations or raw source links. The NCI Premium information page records a possible companion scope for owner-approved evidence links.

= What do I need before I start? =

An OpenAI API account with billing controls and an API key, an indexed File Search vector store, WordPress 6.5 or later and PHP 8.1 or later.

= Where can I get support? =

Support information is at https://nicheclever.co.uk/plugin-support/. Do not include API keys, vector-store references, visitor questions, answers or conversation records in a support request.

== Screenshots ==

1. Setup tab with server-side key, vector store, connection test and placement.
2. Profile tab with visitor-facing wording.
3. AI & Corpus tab with model and retrieval settings.
4. Appearance & Limits tab with panel style, width, credit and daily limits.

== Changelog ==

= 1.0.6 =

* Deletes daily request-allowance counters once their day is over, so the counter table no longer grows indefinitely.
* Tidies the administrator screen code for readability; screens and behaviour are unchanged.

= 1.0.5 =

* Removes analytics page-path data; browser-local events remain opt-in for site-owned listeners and never send or store data.
* Links only to the NCI Premium information page and adds support guidance.
* Keeps this directory readme concise; detailed history is in `CHANGELOG.md`.

= 1.0.4 =

* Added the browser-local `ncli:analytics` event, repeat-submission guard and translatable administrator text.
