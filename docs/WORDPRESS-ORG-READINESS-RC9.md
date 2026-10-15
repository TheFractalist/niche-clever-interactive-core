# RC9 WordPress.org readiness delta

This record supplements `docs/WORDPRESS-ORG-READINESS-RC8.md`, which is retained unchanged as the RC8 evidence. RC9 is a local-only candidate. It is not a WordPress.org submission, directory listing or public release, and nothing here is evidence that a reviewer will accept it.

## What changed that a reviewer would see

- **Stored data.** The plugin now creates a second prefixed table, `ncli_core_conversation_turns`, and can store visitor questions and answers. It does so only if the site owner turns on conversation history (off by default), keeps no identifier with a record, applies an owner-chosen retention period, and offers review and deletion in the administrator area. The readme (Description, FAQ), Help Centre, WordPress privacy-policy suggestion and `docs/PRIVACY-LIFECYCLE.md` were rewritten to say so. The RC8 wording that conversation context is sent to OpenAI was inaccurate and is corrected: only the current question is sent.
- **Uninstall.** `uninstall.php` removes nothing unless the owner has enabled "Delete data on uninstall"; then it removes only the exact NCI options, tables and scheduled event.
- **Front-end credit.** "Powered by NC Interactive" is opt-in and off by default, consistent with the directory expectation that credits are not shown without the site owner's explicit choice.
- **Personal-data tools.** An exporter and eraser are registered; both state that no identifier is stored and so records cannot be matched to a person.
- **Naming.** The administrator menu reads "NC Interactive"; the plugin name remains "Niche Clever Interactive".

## Defect corrected in this candidate

Since RC5 `Plugin` passed the budget gate as a sixth argument to a five-parameter `ChatService` constructor, which PHP silently ignores, so the public route applied no daily visitor or provider limits. RC9 binds the gate correctly and adds a regression test. Candidates RC5 to RC8 should not be exposed to the public. The reviewer-facing claim of "daily request safeguards" was true of the code paths tested in isolation but not of the installed public route in those candidates.

## Evidence status

| Item | State |
| --- | --- |
| Pure PHP/JavaScript checks, lint, source audit | Pass in the development container (PHP 8.3). |
| PHPCS 3.13.6 / WPCS 3.4.1 with the repo's `phpcs.xml.dist`, run on the extracted verification package (bootstrap, `src/`, `uninstall.php`) | Zero findings in the bootstrap and `src/`. One warning in `uninstall.php:36`: `DirectDatabaseQuery.NoCaching` on the `DROP TABLE` (the ignore comment omits that code). Benign, not yet changed; Plugin Check will likely report it too. |
| PHPCompatibilityWP, `testVersion 8.1-` | Clean. This is static analysis; the code has not been executed on PHP 8.1. |
| Informational WordPress-Extra security/i18n/prefix sniffs | Three `json_encode()` warnings, all in RC8-era files unchanged by RC9 (`PolicyFingerprint`, `ConnectionFingerprint`, `OpenAiResponsesFileSearchTransport`). Not a Plugin Check result. |
| Verification package | Built from a clean `git archive` of `9d5b0d8` by a throwaway Python port of `package-core.ps1` (PowerShell is unavailable in the development container): 76 files (the 66 RC8 files plus 10 new), file list identical to the git allowlist, every file byte-identical to the committed source, no denylisted path or token, portable paths, logo/banner SHA-1 match the approved values, `php -l` clean on all 64 PHP files. SHA-256 `3736a14dccf18336ca4a3c6829090ae46c8a0e87393cf27563dbfacbdae1e24d`. **Not the canonical artefact:** it lives only in the ephemeral container and will not match a Windows build's checksum. |
| Canonical package from `scripts/package-core.ps1` | Requires verification (run on the Windows machine) |
| Table creation, SQL, scheduled purge, upgrade from RC8 on WordPress | Requires verification in the disposable fixture |
| Administrator and public browser review (desktop and 390px, keyboard order) | Requires verification |
| Plugin deletion with the option off and on | Requires verification |
| Plugin Check 2.1.0 on the canonical ZIP | **Not run.** The development container cannot reach wordpress.org or GitHub downloads and has no running Docker daemon. Requires the Windows fixture. |
| Live provider behaviour | Not tested by the agent; owner tests manually |
