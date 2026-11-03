# WordPress.org resubmission pack — 1.0.4 (3 November 2026)

Local readiness record. Nothing here has been pushed, tagged, uploaded or sent to WordPress.org. Each of those steps needs Jacob's explicit authorisation.

## Candidate

| Item | Value |
| --- | --- |
| Branch / commits | `release/1.0.4`: `564b3d4` (analytics contract), `f2811c5` (version 1.0.4), plus tooling fixes listed below |
| Archive | `C:\Software Dev\NCI Core Candidate Artifacts\2026-11-03-release-1.0.4\niche-clever-interactive-1.0.4.zip` |
| SHA-256 | `b03b25e12eea9b73b1c0febcc0e5cbe5d5e0c0a3724396d6ff6d187abaaf7f3b` |
| Files | 118 |
| Manifest | `niche-clever-interactive-1.0.4.install-manifest.sha256` (generated from the verified expansion) |
| POT | `niche-clever-interactive-1.0.4.pot`, 258 strings, `Project-Id-Version: Niche Clever Interactive 1.0.4` |

Changes since 1.0.3: the browser-local `ncli:analytics` `CustomEvent`, a guard against repeat submission while a request is in flight, and a `try`/`catch` so a failed dispatch cannot fail an answer that has already been shown. The readme FAQ and changelog disclose the event. "External services" is unchanged because nothing is sent.

## Evidence (3 November 2026)

- **Source:** 34 non-live PHP tests pass under PHP 8.1.33 and 8.2.12. 155 PHP files lint under both. `c5-public-js.mjs` and `c7-admin-help.mjs` pass. `git diff --check` is clean.
- **WPCS (`phpcs.xml.dist`):** zero findings on the plugin file, `uninstall.php`, `src` and `blocks`, and zero on the expanded archive. The whole-checkout scan still reports 32 errors and 16 warnings, all in `tests/`, `fixtures/` and `scripts/verify-install.php`. None of those files ship in the package.
- **Archive:** expanded to 118 files. Lints under both PHP versions, WPCS clean, no denylisted paths, header and Stable tag both 1.0.4. `verify-install.php` reports MATCH against the manifest.
- **Test-Site install:** the backup is at `C:\xampp\backups\Test-Site\nci-core-1.0.3-final-preinstall-20261103-211149`: the Core folder, a 1.67 MB SQL dump and `wp-config.php.bak`. The installed folder matches the manifest. WP-CLI lists Core 1.0.4 as active. Premium is not installed.
- **Plugin Check 2.1.0 (WP-CLI):** installed folder; General, Plugin Repo, Security, Performance and Accessibility categories; no errors and no warnings.
- **Public panel (stubbed `fetch`, no provider call):**
  - `public.js?ver=1.0.4` loads.
  - Processing message is `Receiving response…`, with `progressiveStreaming: true`.
  - Events: `nci_custom_question_submit`, then `nci_answer_success` (live/cache), `nci_answer_error` (`allowance_exceeded`) or `nci_answer_error` (`network_failure`).
  - Payloads contain only `page_path`, `question_type`, `response_source` and `error_category`. No question text was present.
  - A second submit while pending makes no second request.
  - At 375px there is no overflow and the controls are labelled.
- **Admin (authenticated, Test-Site):**
  - All eight workspace tabs (Setup through Help) and the Conversations screen return 200 with no PHP warning or notice.
  - Every visible field has a label.
  - The Help FAQ has 36 entries. "Can answers appear progressively?" opens with Enter.
- **Live provider (approved by Jacob, 3 November 2026):**
  - The connection test passed, and all four setup checklist items are now complete.
  - One live question on `/ask/` streamed: text appeared about 7 s after submission and the answer completed about 1.5 s later.
  - The events were `nci_custom_question_submit`, then `nci_answer_success` (`response_source: live`). No question text was present.
  - The cached repeat was skipped. Core has no answer cache (only Premium reuses answers), so a repeat would be a second paid live call.
- **Debug log:** `WP_DEBUG_LOG` was on through install, Plugin Check, the admin checks and the live question, and no `debug.log` was written. `wp-config.php` has been restored and is byte-identical to its backup.
- **WP 6.5.5 / PHP 8.1.29 / MariaDB clean-install fixture (Core phase, deterministic mock):**
  - Evidence is in `C:\Software Dev\NCI Core Candidate Artifacts\2026-11-03-wp65-php81-fixture-1.0.4\core`; the receipt is `receipt.json`.
  - Installed manifest: MATCH.
  - Assertions passed: activation tables, REST refusal, mock answer, rate limit, history, SSE and JSON fallback, progressive on and off, and the absent, incompatible, malformed and throwing extension cases (route owner stays Core, no provider bypass). Uninstall passed in both retain and delete modes.
  - Plugin Check: 0 errors, 0 warnings.
  - No debug log was written. The fixture was removed after the run.
  - The earlier attempt, which hit the runner quoting bug, is kept at `…-attempt2-runner-quoting`.

## Tooling fixes made during this cycle

- `scripts/generate-install-manifest.ps1` used `-Encoding utf8NoBOM`, which Windows PowerShell 5.1 rejects. It now writes BOM-free UTF-8 through .NET.
- `fixtures/Run-NciCandidateWp65Fixture.ps1`: `Save-NativeLog` aborted on Docker's stderr progress output under 5.1. It now relies only on the exit code.
- `fixtures/Run-NciCandidateWp65Fixture.ps1`: 5.1 stripped the embedded double quotes in the `sh -lc` debug-log check, so the shell failed before it read the log. The check now uses single-quoted shell strings. A new `-CandidateLabel` parameter replaces the hard-coded `1.0.3-r4` receipt label.

## Independent review checklist

For Jacob or a second reviewer, before authorising the upload:

1. Read the `public.js` diff from `1146f1d` to `release/1.0.4`. Confirm the event payload cannot carry question or answer text, query strings or identifiers beyond the validated `[a-z0-9_-]` IDs.
2. Confirm the readme FAQ wording matches that behaviour.
3. Skim `docs/WORDPRESS-ORG-OCCURRENCE-REGISTER-2026-10-25.md` against the current source. `tests/wporg-occurrence-inventory.php` passes, so counts are unchanged.
4. Install the ZIP on a clean site and confirm it activates without notices. The Docker fixture covers this, but a manual Playground check is cheap.
5. Check the readme changelog. The `1.0.3` entry still begins "Local recovery candidate." Decide whether to reword it for the public listing.
6. Confirm the slug and the display name `Niche Clever Interactive` match what was submitted.

## Reviewer reply draft

Check this against the actual review email thread before sending. That email is not in this repository. Reply in the same thread.

> Hello,
>
> Thank you for the review. I have uploaded version 1.0.4, which addresses the reported issues:
>
> - **Translations:** all fixed strings use literal gettext calls with the `niche-clever-interactive` text domain, and the POT extracts cleanly.
> - **Input handling:** administrator request values are unslashed and sanitised at a single boundary before capability and nonce checks. Server values are reduced to a small validated request environment before reaching any service.
> - **Output escaping:** broad sniff suppressions have been removed in favour of direct contextual escaping. Markup from an optional extension passes through one restrictive `wp_kses` policy at every output point.
> - **Direct file access:** the block renderer has the standard `ABSPATH` guard.
> - **HTTP:** provider requests use only the WordPress HTTP API. There are no direct cURL calls and no process-wide `ini_set` or `set_time_limit` calls.
>
> I tested 1.0.4 with Plugin Check 2.1.0 (no errors or warnings) and on a clean WordPress 6.5 / PHP 8.1 install.
>
> Since the last upload, the panel also dispatches a browser-only `ncli:analytics` event that a site owner's own analytics tool can listen for. The plugin itself sends nothing, stores nothing and includes no question or answer text. This is disclosed in the readme.
>
> Thank you,
> Jacob

## Open items

- **Decision: Help Centre FAQ.** The readme discloses the `ncli:analytics` event, but none of the 36 in-plugin Help Centre FAQ entries does. Adding a matching entry changes runtime code (the Help renderer) and needs a rebuilt archive, new hashes and a repeat of this cycle.
- **Decision: 1.0.3 changelog wording** (checklist item 5).
- **Independent review** using the checklist above.
- **Jacob's authorisation** to push `release/1.0.4`, tag, upload the ZIP and send the reply.
