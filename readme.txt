=== Niche Clever Interactive ===
Contributors: jacobfractalist, nicheclever
Tags: question and answer, openai, file search, knowledge base, interactive
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a configurable question-and-answer panel grounded in material you prepare in an OpenAI File Search vector store.

== Description ==

Niche Clever Interactive adds one configurable public question-and-answer panel to a WordPress site. Visitors can ask questions in ordinary language and receive answers generated through the site administrator's own OpenAI Responses API and File Search configuration.

The plugin is designed for organisations and practitioners who have prepared material that is suitable for visitors to explore. It provides one knowledge profile, three panel styles with a choice of panel width and accent colour, configurable visitor-facing copy and unavailable message, bounded owner response guidance, a connection test, daily request safeguards and an optional, off-by-default conversation history that you can review and delete.

Niche Clever Interactive does not create or manage a vector store, upload documents, store an API key in WordPress options, or publish provider citations or source links. It keeps no record of visitor questions or answers unless you switch on the optional conversation history.

Niche Clever is the creator of this plugin, including its administrator artwork and screenshots, and releases them under the same GPLv2 or later licence as the code.

More information, setup guidance and the optional NCI Premium companion plugin are on the plugin page: https://nicheclever.co.uk/nc-interactive/

== External services ==

This plugin connects to the OpenAI API (api.openai.com) to generate answers. It needs your own OpenAI account and API key, and an OpenAI File Search vector store that you create and manage yourself.

* **What is sent:** the visitor's question each time the public panel is used, together with the instructions and settings you have configured. OpenAI File Search may use the material in your vector store to generate the answer. The connection test sends a short test request. Nothing is sent until you add an API key, run a successful connection test and enable public answers.
* **When:** only when a visitor asks a question in the panel, or when an administrator runs the connection test.
* **Your account:** requests are made with your API key and billed to your OpenAI account. The plugin does not send data to Niche Clever.
* **OpenAI Terms of Use:** https://openai.com/policies/terms-of-use/
* **OpenAI Privacy Policy:** https://openai.com/policies/privacy-policy/

You are responsible for telling your visitors, in your own privacy notice, that questions are processed by OpenAI and, if you switch on conversation history, that questions and answers are stored on your site.

== Installation ==

1. Install the plugin ZIP through the WordPress Plugins screen.
2. Activate Niche Clever Interactive.
3. Define `NCLI_OPENAI_API_KEY_DEFAULT` in `wp-config.php` or equivalent secure server configuration. Do not enter an API key into WordPress settings.
4. In **NC Interactive**, enter the reference for an existing OpenAI File Search vector store and a corpus version.
5. Save the settings and run the administrator-only connection test.
6. Enable public answers once the current connection test has passed.
7. Add the **NC Interactive** block or `[ncli_interactive]` shortcode to a published page.

== Frequently Asked Questions ==

= Does Niche Clever Interactive upload or index documents? =

No. Create and manage the vector store through your own OpenAI account, then enter its existing reference in the plugin.

= Where does the API key go? =

Keep it in `NCLI_OPENAI_API_KEY_DEFAULT` in server configuration. The plugin does not store or display the key in WordPress. Defining the key is all the server setup needed; there is no separate switch for live answers.

= Can I switch the provider off without removing the key? =

Yes. Set Visitor availability to Unavailable in the Setup tab, or untick Enable the Core profile in the Profile tab. A server administrator can also define `NCLI_CORE_LIVE_TRANSPORT` as `false` in `wp-config.php` to stop all provider requests while the key stays in place.

= What information is sent to OpenAI? =

Each visitor question is sent to OpenAI when the enabled public panel is used. Earlier questions in the same conversation are not sent with it by Core itself; an installed extension such as NCI Premium can add a limited number of earlier turns. File Search may use material in the configured vector store to generate the answer.

= Does it record what visitors ask? =

Not unless you turn it on. Conversation history is off by default. When you enable it in the Conversation tab, each successfully answered question and its answer are stored in your WordPress database with a random conversation identifier and the time. No IP address, account or browser identifier is stored with them. You choose how long they are kept (30 days, 90 days, one year or until you delete them), and you can review them and delete one, several or all of them under NC Interactive > Conversations. Tell your visitors in your privacy notice if you keep questions and answers.

= What happens to my data if I delete the plugin? =

By default nothing is removed, so a reinstall can pick up your settings, request counters and stored conversations. If you turn on "Delete data on uninstall" in the Conversation tab, deleting the plugin from the Plugins screen removes all of them.

= Can I show a credit link? =

There is an optional "Powered by NC Interactive" link under the panel. It is off by default and appears only if you turn it on in Appearance & Limits.

= Why is the panel unavailable after a settings change? =

Changing the key, transport mode, vector-store reference, corpus version, response guidance, response preset, model, reasoning effort, retrieval depth, answer length or timeout requires a fresh successful connection test before public answers are available again.

= What do I need before I start? =

An OpenAI API account with billing controls and an API key, an OpenAI File Search vector store with your approved files indexed, WordPress 6.5 or later and PHP 8.1 or later.

= Who pays for the answers, and how do I control cost? =

Each public question is billed by OpenAI to your own account. The AI & Corpus tab lets you choose the model, reasoning effort, retrieval depth, maximum answer length and request timeout, and the Help Centre explains how each affects cost and speed. No prices are shown, so check OpenAI pricing and set a usage limit in your OpenAI dashboard as well as the plugin's daily request limits.

= What is the corpus version? =

A label you choose, such as v10, for the material in your vector store. Changing it requires a fresh connection test, so change it whenever you change the files in the store.

= Does it show sources or citations? =

Not on its own: the plugin never publishes provider citations or raw source links. NCI Premium can show titles and links that you have approved yourself.

= Can visitors ask follow-up questions? =

Yes, but on its own Core answers each question separately: earlier questions in the same conversation are not sent with it. NCI Premium can send a limited number of earlier turns as context.

= Are answers cached? =

Core itself does not cache: each question is a fresh request to OpenAI. NCI Premium can reuse answers you have approved.

= Can visitors ask in other languages? =

They can ask in any language the model understands. The interface text is translation-ready, and wording you write in the Profile and Conversation tabs appears exactly as you wrote it.

= Why does the panel say the question allowance has been reached? =

Either one visitor has used their daily questions or the whole site has used its daily allowance. Both limits are set in Appearance & Limits, count by UTC day and start again at UTC midnight. Visitors are counted with a privacy-bounded code and no address is stored.

= Why does the panel say the service is temporarily unavailable? =

The provider did not return a usable answer in time, most often because the reasoning effort is high. Diagnostics shows the latest provider problem in plain words, with a suggestion such as lowering the reasoning effort.

= How do I review or delete stored conversations? =

If you have turned history on, open NC Interactive > Conversations to view conversations and delete one, several or all. Records are also removed automatically after the retention period you choose.

= How do I put the panel on a page, and can I use more than one? =

Add the NC Interactive block or the `[ncli_interactive]` shortcode. You can place it on several pages, but every placement uses the same single profile.

= Does it work with a keyboard and a screen reader? =

The panel and the settings screens are built with labelled controls, keyboard operation, visible focus and announcements when an answer arrives, and they respect reduced-motion settings.

= Does Niche Clever Interactive have a Premium edition? =

NCI Premium is a separately supplied edition from Niche Clever. Details, purchase and download are at https://nicheclever.co.uk/nc-interactive-pro/. It adds several knowledge profiles, approved evidence links, authored questions, conversation export and governance, answer reuse and finer colour control. This plugin contains no Premium code, and nothing in it is locked, limited or time-restricted.

== Screenshots ==

1. Setup tab with the guided checklist: server-side key, vector store, connection test and panel placement.
2. Profile tab: panel title, headline, introduction and the unavailable message.
3. AI & Corpus tab: vector store reference, model, retrieval depth, maximum answer length and request timeout, each with accessible guidance.
4. Appearance & Limits tab: panel style and width, accent colour, optional credit line and daily request limits.

== Changelog ==

= 1.0.1 =

* Corrects WordPress.org review findings for translation extraction, administrator request sanitisation, extension-markup handling and direct block-render access.

= 1.0.0 =

* First public release of Niche Clever Interactive. Adds a link to the NCI Premium page in the Help Centre and the settings footer, an External services section in the readme and a Plugin URI.

= 1.0.0-rc.10 =

* Adds an extension handshake (API minor revision and a list of the extension capabilities this build offers) so a separately supplied edition can confirm compatibility before it registers. Nothing changes for sites without such an edition.

= 1.0.0-rc.9 =

* The Help Centre now has Overview, Frequently asked questions and NCI Premium sections, with a fuller comparison of Core and NCI Premium.
* Diagnostics show the latest provider problem as a plain reason (for example a timeout) with a suggestion, without storing any question, answer or provider message.
* The visitor request allowance needs no extra setup: its server-side secret is derived from WordPress's own salts unless the optional NCLI_CORE_SCOPE_SECRET constant is defined.
* Local-only candidate: optional conversation history (off by default) with a Conversations screen, delete-one, delete-selected and delete-all controls, configurable retention and an owner-controlled uninstall routine; panel width, accent colour and an opt-in credit line; an editable unavailable message; the administrator menu is now "NC Interactive". Corrects a wiring defect from RC5 that left the public route without its daily request limits.
* Local-only model-cost configuration: curated model choice, reasoning effort, retrieval depth, maximum answer length and request timeout in AI & Corpus, with qualitative Help Centre cost guidance and no prices. All new settings require a fresh connection test.

= 1.0.0-rc.8 =

* Submission-readiness candidate: public Niche Clever Interactive identity, translation-ready interface strings, expanded Help Centre, accessible technical-field guidance, privacy-policy suggestion and a transparent Core and NCI Premium comparison.

= 1.0.0-rc.7 =

* Local-only bounded-response-guidance correction: saved owner response guidance now affects only response style and format, while Core retrieval, safety and disclosure rules remain non-negotiable. Concise, Balanced and Detailed now provide Core-defined response-detail baselines. Changing either requires a fresh administrator connection test.

= 1.0.0-rc.6 =

* Local-only presentation correction: answers now render a constrained, DOM-built Markdown subset without accepting provider HTML or creating provider-derived links.

= 1.0.0-rc.5 =

* Local-only Core boundary correction. Core now answers directly from the configured File Search corpus after a completed retrieval. The approved evidence map, citation metadata and public source links have been removed from Core.
