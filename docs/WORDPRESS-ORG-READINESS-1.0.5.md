# NCI Core 1.0.5 WordPress.org readiness review — 4 November 2026

## Decision

This is a local readiness record for `1.0.5-r1`, not a submission, release, tag, upload or deployment. The candidate is technically stronger than 1.0.4, but the **pre-upload gate fails**: `https://nicheclever.co.uk/plugin-support/` returned HTTP 404 on 4 November 2026. The intended support URL must return HTTP 200 with the published support content before any WordPress.org upload.

| Candidate item | Value |
| --- | --- |
| ZIP | `C:\Software Dev\NCI Core Candidate Artifacts\2026-11-04-release-1.0.5-r1\niche-clever-interactive-1.0.5.zip` |
| SHA-256 | `3a84acabee49f2482c5de8ab914449d8ab0851e2a7f317e3143e375bd91030fe` |
| Package / manifest | 118 files; verified expanded and installed copies both match |
| Directory readme | 5,484 bytes; stable tag and runtime header are `1.0.5` |
| Local runtime | `http://localhost/Test-Site/`, Core active at `1.0.5` |
| Runtime recovery | `C:\xampp\backup\Test-Site-ncli-core-pre-1.0.5-r1-20261104-000248` (prior plugin folder and database dump) |

## Privacy decision: remove page paths

The browser-only `ncli:analytics` event used to include the page path. Core did not send that event anywhere itself, but any analytics listener added by a site owner could learn which page a visitor used. A path can reveal a page subject and, on some sites, may be unique or contain meaningful identifiers. It was not needed for Core to work, rate limits or broad event measurement.

`1.0.5` removes it completely. The event permits only validated profile/authored identifiers, question type, live-or-cached response source and a closed error category. It contains no path, query string, URL, question, answer, browser identifier or storage/network forwarding. The JavaScript regression test rejects those additions. In plain English: the panel works the same, but an optional site-owned analytics listener receives less information about a visitor.

The decision is disclosed in `CHANGELOG.md`, `readme.txt`, the administrator Help Centre and this record.

## Ranked findings

### Implemented

- **Request validation and REST:** the sole public `ncli/v1/chat` POST route validates bounded JSON, policy refusal, profile and preset before provider work, and returns closed public errors.
- **Capabilities and nonces:** administration requires `manage_options`; administrative actions use a single capability-and-nonce guard.
- **Output and extension markup:** public values are text nodes; approved links are HTTPS-only and bounded; optional extension markup passes through restrictive `wp_kses` at every output boundary.
- **Rate/concurrency:** server-secret scope hashing, trusted-proxy handling, transactional reservations and daily visitor/provider limits are implemented.
- **Lifecycle/privacy:** history is off by default; retention, administrator deletion and opt-in uninstall deletion are implemented. Core itself does not forward its browser-local analytics event or store it.
- **External service and package boundaries:** OpenAI Responses/File Search and server-key responsibilities are disclosed. GPL text, provenance, gettext coverage, a package allowlist and no remote executable-code mechanism are present.
- **Premium boundary:** Core contains no Premium implementation and has no locked functionality. Its link is informational only, without purchase, download, pricing or availability claims.

### Verified

- All 35 PHP contract checks, i18n coverage, provenance, source audit, standalone, occurrence and recovery checks passed under PHP 8.2.12; both JavaScript suites passed.
- All 156 repository PHP files linted under PHP 8.2.12. WPCS passed on source runtime paths and the expanded candidate.
- ZIP, SHA-256, 118-file inventory and expanded manifest were generated and verified. The Test-Site installed folder also matches the manifest.
- Test-Site Core is active at 1.0.5. The standard Core connection-test service passed in 8.5 seconds and persisted its normal record. One benign public POST returned HTTP 200 with the `live` outcome category in 11.1 seconds. No question, answer, credential, vector-store reference, provider identifier or provider payload is recorded here.
- Test-Site home and `/ask/` returned HTTP 200, supplied panel markup and loaded `public.js?ver=1.0.5`.
- Plugin Check 2.1.0 reported no errors or warnings on installed Core in General, Plugin Repo, Security, Performance and Accessibility.
- A fresh isolated WordPress 6.5.5 / PHP 8.1.29 / MariaDB 10.11 fixture verified installation, manifest, Plugin Check, activation tables, REST refusal, mock answer, rate limit, history, JSON/SSE delivery, progressive-off fallback, extension isolation, retain/delete uninstall, final reinstallation and a clean debug log. Its containers and volumes were removed.
- Locally, `http://nicheclever.test/plugin-support/` and `http://nicheclever.test/nc-interactive/` return HTTP 200. Page `#1343` and new support page `#9994` are published; Premium draft `#1344` was not changed.

### Remaining verification

- Supported-browser review remains: Settings/Help/Conversations rendering, keyboard focus/order, screen-reader announcements, responsive public-panel geometry and the changed product/support-page presentation. Browser automation was unavailable, so HTTP and fixture checks are not presented as visual or browser-accessibility evidence.
- The direct Core service test and public REST smoke prove the workflow, but do not replace an authenticated administrator-browser check of the nonce-bearing connection-test control.
- One successful OpenAI call does not prove provider uptime, every model configuration, owner corpus quality, billing configuration or production behaviour.
- Directory acceptance remains an external WordPress.org decision.

### External gate — fail before upload

`https://nicheclever.co.uk/plugin-support/` returned **404** on 4 November 2026. Publish and verify the production support page at that exact HTTPS URL, then repeat HTTP and browser checks. Until then, do not upload this candidate to WordPress.org.

## Corrected reviewer-reply draft

Use only after checking it against the actual review email. It corrects two inaccurate 1.0.4 draft statements.

> Hello,
>
> Thank you for the review. I have prepared version 1.0.5, which addresses the reported issues:
>
> - **Translations:** every fixed administrator string, including settings help, the Help Centre and diagnostics, is a literal gettext call with the `niche-clever-interactive` text domain. A regression check covers rendered administrator strings.
> - **Input handling and administration:** administrator request values are unslashed and sanitised at one boundary, then protected by `manage_options` and action-specific nonces. Server values are reduced to a bounded request environment before services use them.
> - **Output handling:** public values are rendered as text or through restrictive contextual policies. Optional extension markup passes through restrictive `wp_kses` at each output boundary.
> - **HTTP:** provider dispatch uses WordPress’s HTTP API (`wp_remote_post`). For progressive delivery only, a temporary documented `http_api_curl` callback configures WordPress’s own cURL handle for the fixed Core provider request and is removed immediately afterwards. Core makes no standalone cURL request and does not use `curl_init` or `curl_exec`.
> - **Scoped checks:** justified direct-database and provider-related PHPCS suppressions remain only where WordPress APIs cannot express a plugin-owned table transaction, fixed plugin-owned table identifier or the temporary WordPress streaming hook. Each has an adjacent rationale; they have not been represented as removed wholesale.
> - **Privacy:** the browser-local `ncli:analytics` event sends and stores nothing itself. Version 1.0.5 removes page-path data completely; the bounded event contains no URL component, question, answer or browser identifier.
>
> I tested the candidate with Plugin Check 2.1.0 (no errors or warnings), a clean WordPress 6.5 / PHP 8.1 fixture and a local WordPress runtime.
>
> Thank you,
> Jacob

## WordPress.org criteria cross-check

The readme has a numeric stable tag, GPL-compatible licence declaration, concise current changelog and 5,484-byte size. WordPress’s handbook warns that readmes above 10 KB may error and recommends moving older changelog history out; that is why detail stays in `CHANGELOG.md`. The Directory guidelines require GPL-compatible included material, no locked Core functionality, documented substantive external services, no non-consensual tracking and no remote executable-code delivery. The candidate’s static/package/fixture evidence supports those points, subject to the external support-page gate and Directory review.
