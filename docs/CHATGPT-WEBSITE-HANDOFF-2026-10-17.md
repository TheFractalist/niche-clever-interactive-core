# Handoff for ChatGPT: update the AskFolio page and case study for Niche Clever Interactive

Prepared 17 October 2026 from the NCI Core and NCI Premium repositories. Paste the PROMPT section into the ChatGPT task that has the Niche Clever Branding context pack, and attach this file plus the screenshots listed below. Everything under FACTS comes from the repositories and the 17 October live verification; ChatGPT should treat it as the authority for product claims and should not add claims that are not here.

---

## PROMPT

You are updating the Niche Clever website. The product formerly called AskFolio (a WordPress plugin, free and premium editions) has been rebuilt as **Niche Clever Interactive** ("NCI"): a free plugin, **Niche Clever Interactive** (Core), and a separate private companion, **NCI Premium**. The existing website page (WordPress page ID 1248, currently at `/askfolio/`, with a `/askfolio/readme/` sub-page) and the associated case study still describe the old AskFolio 1.0.4 Free and Premium editions. They are being replaced by two new pages, `/nc-interactive/` and `/nc-interactive-pro/`, and the old pages retired.

Do this in two stages and stop after stage 1 for Jacob's approval.

**Stage 1: analysis only (no edits).**
1. Read the current `/askfolio/` page, `/askfolio/readme/` and the AskFolio case study (find it in the context pack or the site; tell Jacob where it is).
2. Produce a table of every statement on those pages that is now wrong, outdated or risky, with the exact current wording, why, and the replacement drawn only from FACTS below. Pay particular attention to: the product name; the Free/Premium feature split (the old free edition had gated features; the new Core has none); "Free 1.0.4" version labels and the readme sub-page; download links and checksums; screenshots and their captions; setup instructions; the OpenAI/external-service explanation; and anything implying WordPress.org status.
3. State your plan for the new pages `https://nicheclever.co.uk/nc-interactive/` (free plugin) and `https://nicheclever.co.uk/nc-interactive-pro/` (Premium, payment and download), the retirement of `/askfolio/` and `/askfolio/readme/`, and every internal link or redirect that depends on them (see DECISIONS).
4. List anything you need that is not in FACTS (do not guess).

**Stage 2: after Jacob approves**, edit only the page content and presentation metadata for the page(s) and case study approved in stage 1. Do not touch plugin files, settings, profiles, API keys, corpus configuration, the theme, or WordPress.org state. Keep the existing page structure and Niche Clever editorial presentation. Purge only available caches for the changed URLs, verify publicly as an unauthenticated visitor on desktop and 375px mobile, and report the exact fields changed and a rollback route.

Writing rules: British English; plain, specific, no hype; describe the Premium plugin as a separate companion plugin that adds capabilities, never as a locked or trial version of the free plugin; do not say the plugin is "available on WordPress.org" until Jacob confirms approval; do not publish credentials, vector-store identifiers or any private corpus content; the screenshots supplied use synthetic configuration only.

---

## DECISIONS (Jacob to confirm before stage 2)

1. **URLs (decided by Jacob).** The Core plugin header now carries `Plugin URI: https://nicheclever.co.uk/nc-interactive/`, and the readme links to it, so that page must exist and be live at the point of submission. Build it as a new page at `/nc-interactive/` (reusing the structure and editorial presentation of page 1248). The old `/askfolio/` page and `/askfolio/readme/` are to be retired: propose a 301 from each to the new page (or to `/nc-interactive/` and its readme section), and list every internal link, menu entry, case-study link and sitemap entry that points at them.
2. **Premium page.** `/nc-interactive-pro/` is a separate page, built as part of this work or a follow-up, that carries NCI Premium's description, payment and download. The new `/nc-interactive/` page links to it, and the Core readme links to it as "details, purchase and download". Say clearly, on both pages, that Core is complete and unrestricted without Premium, and that Premium is a separate plugin (it needs Core). Do not describe payment or licence mechanics beyond what Jacob supplies, and do not put any Premium download on the Core page.
3. **Historic AskFolio.** Either retire AskFolio wording entirely, or keep one sentence on the case study ("formerly AskFolio"). The Plugins Team flagged the name AskFolio as confusingly like another product, so avoid presenting it as a current product name.
4. **Downloads.** Core may be offered as a GPL ZIP from the new page once built and approved (include the SHA-256 Jacob supplies); until WordPress.org approval, do not link to a directory listing.

## FACTS

### Naming and status
- Free plugin: **Niche Clever Interactive** ("NCI Core"), slug and text domain `niche-clever-interactive`, admin menu **NC Interactive**, block and shortcode `[ncli_interactive]`. Version in the repository: 1.0.0-rc.10 (final version to be set at release). Licence GPLv2 or later. Requires WordPress 6.5+, PHP 8.1+. Tested to WordPress 7.1.
- Companion plugin: **NCI Premium**, private, separate plugin, requires Core. Core works fully without it; deactivating it leaves Core exactly as it was.
- WordPress.org status: Core is being resubmitted to the Plugin Directory (replacing the earlier AskFolio submission, which the Plugins Team pended on 10 October 2026). Not approved or listed yet.

### What Core does (free, nothing locked, limited or time-restricted)
- One public question-and-answer panel, placed with a block or shortcode on any pages. Visitors ask in ordinary language and get answers grounded in the site owner's own material through **OpenAI's Responses API and File Search**.
- The owner brings their own OpenAI account, API key and an existing File Search vector store. Core does not create, upload to or manage the store or documents.
- The API key lives in server configuration (`NCLI_OPENAI_API_KEY_DEFAULT` in wp-config.php), never in WordPress settings.
- Profile (admin tabs): Setup (guided checklist), Profile, AI & Corpus, Conversation, Appearance & Limits, Diagnostics, Help Centre.
- Controls: panel title, headline, introduction, unavailable message; three panel styles, width and accent colour; optional "Powered by NC Interactive" credit (off by default); owner response guidance (concise/balanced/detailed, bounded); model choice, reasoning effort, retrieval depth, answer length, timeout; administrator-only connection test, required after any relevant change before public answers are available.
- Safeguards: per-visitor and whole-site daily question limits (UTC day; visitors counted by a privacy-bounded code, no address stored); fail-closed behaviour; a server switch to stop all provider requests.
- Conversation history is **off by default**; when on, stores question, answer, random conversation id and time (no IP, account or browser identifier), with retention of 30 days, 90 days, one year or indefinite, and screens to review and delete one, several or all. Optional "Delete data on uninstall".
- Answers are rendered as a safe Markdown subset; Core shows no provider citations or raw source links. Each question is answered separately (no earlier turns), and nothing is cached.
- Accessibility: labelled controls, keyboard operation, visible focus, announced answers, reduced-motion respected; translation-ready.
- Cost: each question is billed by OpenAI to the owner's account; no prices shown in the plugin.

### What NCI Premium adds (separate plugin; 12 capabilities, each exercised in a real WordPress with a live OpenAI connection on 17 October 2026)
1. Several knowledge profiles (create, edit, delete from one Profiles hub, each with its own connection test and status).
2. Access control per profile (public, signed-in, selected roles or named users).
3. More placements per profile: panel, single authored question and question collection shortcodes, shareable links that prefill a question.
4. Approved evidence links: titles and https links the owner has approved appear beneath answers; unsafe addresses are refused.
5. Chronology records so answers can use dates the owner has verified.
6. Earlier turns sent as context (0 to 10, per profile) for coherent follow-up questions.
7. Cached answers: owner-approved question and answer pairs are served instantly (about 0.3 s against 7 to 11 s live in testing) and without a provider call.
8. Authored questions with permanent keys.
9. Ordered question collections.
10. Conversation export (CSV and Markdown, per profile or all) with audit entries.
11. Governance: per-profile recording and retention, erasure with typed confirmation, audit log.
12. Fine colour control for the response panel, per profile.
Premium is integrated into Core's own screens (Profiles, Conversations > Governance, Settings), so the interface looks the same.

### External service wording (Guideline 6)
The plugin sends each visitor question to OpenAI (the owner's account) to generate the answer; File Search may use material in the owner's vector store. Nothing is sent to Niche Clever. The readme now has an "External services" section linking OpenAI's Terms of Use (https://openai.com/policies/terms-of-use/) and Privacy Policy (https://openai.com/policies/privacy-policy/); mirror those links on the page. The page should say the same, plainly, and that conversation history is optional and off by default.

### Screenshots available
In `niche-clever-interactive-core/wordpress-org-assets/`: `screenshot-1.png` Setup tab; `screenshot-2.png` Profile tab; `screenshot-3.png` AI & Corpus; `screenshot-4.png` Appearance & Limits (all captured 15 October 2026 from the local Test-Site, synthetic configuration); also `banner-1544x500.png`, `icon-256x256.png`. Premium screenshots (Profiles hub, profile workspace sections, Governance) are not yet captured; Jacob or Claude can capture them from the Test-Site with synthetic data on request.

### Do not claim
Hosted or managed service; document upload or indexing by the plugin; citations in Core; follow-up context or caching in Core; any price; WordPress.org listing or approval; any figure not in this file.
