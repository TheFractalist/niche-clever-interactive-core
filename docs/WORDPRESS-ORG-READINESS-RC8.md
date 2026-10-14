# RC8 WordPress.org readiness record

## Candidate

- Candidate: `1.0.0-rc.8`, local Test-Site only.
- Package: `C:\Software Dev\NCI Core Candidate Artifacts\2026-10-14-rc8\niche-clever-interactive-1.0.0-rc.8.zip`.
- SHA-256: `852daa2fca09c2c6d7f31a3aed4531f55cc79735e920ff3a6829e7dd444c8cbc`.
- Package audit: 66 portable runtime files; package script denylist and source allowlist checks passed. `wordpress-org-assets/` is not in the ZIP.
- Test-Site runtime: the 66 installed files matched the ZIP byte-for-byte after activation. The immediately previous RC8 runtime is recoverable at `C:\xampp\backup\Test-Site-ncli-runtime-pre-rc8-repackage-20261014-010724`; RC7 remains independently recoverable at `C:\xampp\backup\Test-Site-ncli-core-runtime-pre-rc8-public-identity-20261014-191500`.

## Implemented in RC8

- Public identity and metadata use **Niche Clever Interactive** with the planned `niche-clever-interactive` text domain. `Core` remains architectural terminology only.
- The expanded Help Centre covers purpose, prerequisites, secure server-side API-key configuration, corpus suitability, existing File Search vector stores, public-use limits, troubleshooting, FAQs and the planned NCI Pro boundary. It links only to current OpenAI documentation.
- Administrator technical and operational fields use keyboard-operable question-mark help controls with associated `aria-controls`, `aria-expanded`, focus behaviour and Escape close behaviour.
- The Help Centre comparison table names only confirmed Core and planned-Pro boundaries. It contains no availability statement, commercial destination, Pro code or licence gate.
- The public panel and administrator strings use the NCI translation domain. New-install panel defaults are product-neutral rather than AskFolio-branded.
- A WordPress privacy-policy suggestion explains the public question/OpenAI File Search disclosure, absence of stored API keys and HMAC-based rate-control scope.

## Directory assets — draft only

The following original assets are staged for a future directory-asset channel and are not plugin files:

| File | SHA-256 |
| --- | --- |
| `wordpress-org-assets/banner-1544x500.png` | `f4f4f30b25674acbb2f6debf996d80e2c2d10605480e460e2efbaf08eff37226` |
| `wordpress-org-assets/banner-772x250.png` | `0aaccaf86b252689054bdcddde2fd0d0f8ce9408a5b52ccada2ff4fca7d64f66` |
| `wordpress-org-assets/icon-256x256.png` | `3dbd97ed072524d8d6ca07b049f9bea95e570407c59a6f71489240588485448f` |
| `wordpress-org-assets/icon-128x128.png` | `822c090da006a3a6fcebb43eeb7813b723994173dfeab894b44389f48a4234bc` |

The source graphics were newly generated and locally typeset on 14 October 2026; no existing Niche Clever, AskFolio or WordPress.org asset was copied. The in-plugin administrator logo and banner remain unchanged. Test-Site browser screenshot capture timed out, so no screenshot asset is claimed. Capture truthful RC8 screenshots from a synthetic, non-secret configuration before any directory upload.

## Validation

- PASS: pure contracts; provenance and source audits; C1--C5 deterministic suites; JavaScript syntax and public-client boundary check; complete PHP syntax lint; package policy; `git diff --check`.
- PASS: focused WPCS/PHPCS scan of the RC8 runtime source produced no output.
- PASS: Test-Site Help Centre, help-control activation and Escape close, configuration read-back, connection test, Classic/Minimal/Dark public styles, responsive administrator/public layout and active RC8 identity were manually observed.
- NOT RUN in RC8: a new public provider answer. The RC8 changes did not alter the request/transport path; the administrator-only connection test passed. This is not evidence of a new public-answer round trip.

## Plugin Check record

Plugin Check 2.1.0 on the installed RC8 runtime recorded one error and five warnings, separate from browser/provider evidence:

- Error `trunk_stable_tag`: `readme.txt` deliberately retains `Stable tag: trunk`, as directed for the release-candidate period. Plugin Check recommends matching the main-header version; this is a final-submission gate.
- Four warnings flag the existing prepared, row-locked `$wpdb` transaction implementation because static analysis cannot trace the prefixed table identifier and prepared SQL through the transaction helpers.
- One warning flags the deliberate direct OpenAI integration and recommends the newer WordPress AI Client. No transport migration is included in RC8.

## Submission gates

No WordPress.org upload, slug allocation, account submission, directory-asset upload, public release or `1.0.0` tag has occurred. Before any submission, resolve the stable-tag decision, review the static-analysis warnings, capture approved truthful screenshots, re-run Plugin Check against the final package, and obtain external WordPress.org acceptance.
