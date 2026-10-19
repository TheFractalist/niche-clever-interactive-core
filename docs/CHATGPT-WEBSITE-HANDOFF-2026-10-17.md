# NCI website build spec for ChatGPT (v2, 18 October 2026)

Supersedes the 17 October handoff. Authority: Jacob's instructions plus this file. Product facts come from the NCI Core and NCI Premium repositories and the 17 October live verification; do not add claims that are not here.

---

## PROMPT (paste into the ChatGPT task that has the Niche Clever Branding context pack)

Use this file as the authority. You are building the public web presence for **Niche Clever Interactive** (free plugin, "Core") and **NCI Premium** (separate companion plugin) on nicheclever.co.uk, replacing the retired AskFolio product page. Two pages already exist as plain pages with the right text but without the site's product layout:

| Page | WordPress ID | URL | Status |
| --- | --- | --- | --- |
| Core (free) | 1343 | /nc-interactive/ | published |
| Premium | 1344 | /nc-interactive-pro/ | draft (keep as a draft until Jacob says publish) |
| Case study | 1345 | /work/niche-clever-interactive/ | published; update to match |
| Retired | 1248 | /askfolio/ and /askfolio/readme/ | to be redirected, not deleted |

The target look and layout is exactly the live AskFolio page (/askfolio/, page 1248). Section "HOW THE LAYOUT WORKS" below says how it is produced. Do not change the theme, plugin files or settings, or WordPress.org state.

Work in two stages and stop after Stage 1 for Jacob's approval.

**Stage 1 (read-only):** (a) Open page 1248 in the editor and read its "Niche Clever editorial presentation" box and body; open 1343 and 1344 and confirm their editorial boxes are empty. (b) Compare the rendered /askfolio/ against /nc-interactive/ as a visitor and list every visual and structural difference (hero, eyebrow, standfirst, numbered "On this page", At a glance facts, Availability note, Explore further, gallery, Readme link). (c) Check this file's PAGE SPECS against what you see, and list any difference between them and the theme's actual behaviour. (d) Report the case study's current text and every statement needing change, the redirects needed for /askfolio/ and /askfolio/readme/, and every internal link, menu entry or sitemap entry that points at the old pages. (e) List anything you need from Jacob. Make no edits.

**Stage 2 (after approval):** Apply the PAGE SPECS exactly: paste the JSON into each page's editorial box, make the stated body edits, update the case study, and add the redirects. Do not change anything else. Keep page 1344 a draft. Purge only available caches for the changed URLs, verify as an unauthenticated visitor on desktop and at 375px (no horizontal overflow; 1343 shows the hero, numbered On this page list, At a glance, Availability and Explore further; heading numbers are not doubled), and report the exact fields changed and a rollback route (page revisions). Writing rules: British English; plain and specific; Premium is a separate companion plugin, never a locked or trial version; no WordPress.org approval or listing claim until Jacob confirms; no credentials, vector-store identifiers, prices or private corpus content.

---

## HOW THE LAYOUT WORKS (verified on 18 October 2026)

- Page 1248's body is plain WordPress content: `h2` headings, paragraphs, lists, one table. There is no layout markup in it.
- The product layout comes from the **Niche Clever editorial presentation** box on the page edit screen (field name `nicheclever_editorial_data`, a JSON text area saved with the page). From it the theme builds: the hero (eyebrow, page title, standfirst); the **On this page** list (one numbered link per `h2` in the body, numbered by the theme as 01, 02...); **At a glance** (the `facts` list); **Availability** (the `aside` text, which may contain simple links); and **Explore further**.
- Because the theme numbers headings itself, **body headings must not carry typed numbers**. Page 1343's headings currently read "01 / ...", "02 / ..." and must be changed to plain text.
- The page body must not repeat the hero: it starts at the first `h2`. (1248's first `h2` is the product headline; 1343's first heading serves the same role.)
- The editorial box text area stores `&` as `&amp;` (page 1248 shows `Q&amp;A`). Copy that convention.
- **Galleries are chosen by the editorial JSON.** The theme now (code ready, release pending) reads `presentation.gallery`: `"nci-core"` shows the four Core screenshots (Setup, Profile, AI & Corpus, Appearance & Limits; title "Niche Clever Interactive 1.0.0"), `"nci-premium"` shows the four Premium screenshots (Profiles hub, Setup tab of a profile workspace, Conversation tab, profile workspace header with switcher; title "NCI Premium"). The images ship inside the theme, so **do not add screenshots to the page body**. Two optional keys: `presentation.documentation` (`{"label","url"}`; leave it out when there is no documentation page, because the "Documentation: Readme" row otherwise appears only for AskFolio) and `presentation.case_study` (`{"label","url"}`, or `false` to omit the link).
- **The theme release that adds this is Jacob's manual job and may not be live yet.** Before pasting the JSON, check the theme version in Appearance > Themes against what Jacob tells you; if the release is not installed, the extra keys are ignored and the page shows no gallery. Tell Jacob if so, and do not treat it as an error.

---

## PAGE SPECS

### Page 1343 /nc-interactive/ (Core)

Body edits: remove the "NN / " prefix from each of the eight `h2` headings (keep the `id` attributes). Replace the paragraph under "Inside the interface" (it promises a gallery that does not exist) with:

> The administrator screens follow the order of setup: Setup (a guided checklist and connection test), Profile (the visitor-facing wording), AI & Corpus (vector store, model, retrieval depth, answer length and timeout) and Appearance & Limits (style, width, accent colour and daily limits). Each setting carries plain-language guidance, and the Help Centre explains how they affect cost and speed.

Keep the existing OpenAI Terms of Use and Privacy Policy links in section "Operational safeguards and privacy". Add to the end of the "Requirements and download" section: "Optional extras are supplied separately as NCI Premium (link to /nc-interactive-pro/)."

Editorial box (paste whole):

```json
{
  "version": 2,
  "layout": "page",
  "eyebrow": "Plugin",
  "standfirst": "A free WordPress plugin for grounded questions and answers drawn from your own OpenAI File Search material.",
  "aside": "Niche Clever Interactive 1.0.0 is being resubmitted to WordPress.org for plugin review. Directory approval and listing are not yet confirmed.\n\nThe release ZIP and its checksum will be published here once verified.\n\nOptional extras are supplied separately as <a href=\"/nc-interactive-pro/\">NCI Premium</a>.",
  "presentation": {
    "facts": [
      { "key": "Edition", "value": "Core 1.0.0 (free)" },
      { "key": "Requires", "value": "WordPress 6.5 or later, PHP 8.1 or later" },
      { "key": "Licence", "value": "GPL-2.0-or-later" },
      { "key": "You also need", "value": "An OpenAI API key and a File Search vector store" },
      { "key": "Publisher", "value": "Niche Clever" }
    ],
    "variant": "product-e",
    "gallery": "nci-core",
    "case_study": { "label": "The case study", "url": "/work/niche-clever-interactive/" }
  }
}
```

(When a verified ZIP and SHA-256 exist, Jacob will give them to you; then change the second aside paragraph to a download link, as /askfolio/ does, and add the checksum to the body under "Requirements and download".)

### Page 1344 /nc-interactive-pro/ (Premium, draft)

Body: keep the current text for now. Replace the two visible italic placeholder paragraphs only if Jacob supplies screenshots, pricing, licence and delivery details. Never invent them. Describe the 12 capabilities from FACTS.

Editorial box (paste whole):

```json
{
  "version": 2,
  "layout": "page",
  "eyebrow": "Plugin",
  "standfirst": "A separate companion to Niche Clever Interactive: several profiles, approved evidence, authored questions and conversation governance.",
  "aside": "NCI Premium requires the free Niche Clever Interactive plugin, which remains complete without it.\n\nPricing, licence terms and download will be added here when approved.\n\n<a href=\"/nc-interactive/\">Read about the free Core plugin</a>.",
  "presentation": {
    "facts": [
      { "key": "Edition", "value": "NCI Premium (companion plugin)" },
      { "key": "Requires", "value": "Niche Clever Interactive Core, WordPress 6.5 or later, PHP 8.1 or later" },
      { "key": "You also need", "value": "An OpenAI API key and a File Search vector store" },
      { "key": "Publisher", "value": "Niche Clever" }
    ],
    "variant": "product-e",
    "gallery": "nci-premium",
    "case_study": { "label": "The case study", "url": "/work/niche-clever-interactive/" }
  }
}
```

Add a Licence fact only when Jacob states it.

### Publishing order

Premium page 1344 stays a draft until it has real purchase details. Until it is published, the Premium link in the Core page's Availability note and in the plugin (readme, Help Centre, settings footer) lands on a missing page, so tell Jacob this when you report, and do not publish it yourself.

### Case study (page 1345) and the old pages

Update the case study so that it names the product Niche Clever Interactive (formerly AskFolio is acceptable once), describes the free Core and the separate Premium companion as in FACTS, and links to /nc-interactive/ rather than /askfolio/. Propose 301 redirects from /askfolio/ to /nc-interactive/ and from /askfolio/readme/ to /nc-interactive/; do not delete page 1248 (retire by redirect, keeping its revisions).

---

## FACTS

### Naming and status
- Free plugin: **Niche Clever Interactive** ("NCI Core"), slug and text domain `niche-clever-interactive`, admin menu **NC Interactive**, block and shortcode `[ncli_interactive]`. Version 1.0.0 (Stable tag 1.0.0). Licence GPLv2 or later. Requires WordPress 6.5+, PHP 8.1+; tested to WordPress 7.1. Plugin URI: https://nicheclever.co.uk/nc-interactive/. The plugin links to /nc-interactive-pro/ from its readme, its Help Centre and a discreet link beside Save settings (only when Premium is not installed).
- Companion: **NCI Premium**, private, separate plugin, requires Core. Core works fully without it; deactivating Premium leaves Core exactly as it was.
- WordPress.org: Core is being resubmitted (replacing the earlier AskFolio submission pended on 10 October 2026). Not approved or listed yet.

### What Core does (free; nothing locked, limited or time-restricted)
- One public question-and-answer panel, placed with a block or shortcode on any pages. Visitors ask in ordinary language and get answers grounded in the owner's own material through **OpenAI's Responses API and File Search**.
- The owner brings their own OpenAI account, API key and an existing File Search vector store. Core does not create, upload to or manage the store or documents.
- The API key lives in server configuration (`NCLI_OPENAI_API_KEY_DEFAULT` in wp-config.php), never in WordPress settings.
- Admin tabs: Setup (guided checklist), Profile, AI & Corpus, Conversation, Appearance & Limits, Diagnostics, Help Centre.
- Controls: panel title, headline, introduction, unavailable message; three panel styles, width and accent colour; optional "Powered by NC Interactive" credit (off by default); owner response guidance (concise, balanced or detailed, bounded); model choice, reasoning effort, retrieval depth, answer length, timeout; administrator-only connection test, required after any relevant change before public answers are available.
- Safeguards: per-visitor and whole-site daily question limits (UTC day; visitors counted by a privacy-bounded code, no address stored); fail-closed behaviour; a server switch to stop all provider requests.
- Conversation history is **off by default**; when on it stores question, answer, random conversation id and time (no IP, account or browser identifier), with retention of 30 days, 90 days, one year or indefinite, and screens to review and delete one, several or all. Optional "Delete data on uninstall".
- Answers render as a safe Markdown subset; Core shows no provider citations or links. Each question is answered separately (no earlier turns) and nothing is cached.
- Accessibility: labelled controls, keyboard operation, visible focus, announced answers, reduced motion respected; translation-ready.
- Cost: each question is billed by OpenAI to the owner's account; no prices shown in the plugin.

### What NCI Premium adds (separate plugin; 12 capabilities, each exercised in real WordPress with a live OpenAI connection on 17 October 2026)
1. Several knowledge profiles (create, edit, delete from one Profiles hub, each with its own connection test and status).
2. Access control per profile (public, signed-in, selected roles or named users).
3. More placements per profile: panel, single authored question and question collection shortcodes, shareable links that prefill a question.
4. Approved evidence links: titles and https links the owner has approved appear beneath answers; unsafe addresses are refused.
5. Chronology records so answers can use dates the owner has verified.
6. Earlier turns sent as context (0 to 10, per profile) for coherent follow-up questions.
7. Cached answers: owner-approved question and answer pairs served instantly (about 0.3 s against 7 to 11 s live in testing) without a provider call.
8. Authored questions with permanent keys.
9. Ordered question collections.
10. Conversation export (CSV and Markdown, per profile or all) with audit entries.
11. Governance: per-profile recording and retention, erasure with typed confirmation, audit log.
12. Fine colour control for the response panel, per profile.

Premium is integrated into Core's own screens (Profiles, Conversations > Governance, Settings), so the interface looks the same.

### External service wording (Guideline 6)
The plugin sends each visitor question to OpenAI (the owner's account) to generate the answer; File Search may use material in the owner's vector store. Nothing is sent to Niche Clever. Mirror OpenAI's Terms of Use (https://openai.com/policies/terms-of-use/) and Privacy Policy (https://openai.com/policies/privacy-policy/) on the Core page, and say that conversation history is optional and off by default.

### Do not claim
Hosted or managed service; document upload or indexing by the plugin; citations in Core; follow-up context or caching in Core; any price; WordPress.org listing or approval; any figure not in this file.
