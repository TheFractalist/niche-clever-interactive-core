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
| PHPCS 3.13.6 / WPCS 3.4.1 with the repo's `phpcs.xml.dist`, run on the extracted verification package (bootstrap, `src/`, `uninstall.php`) | Zero findings in the bootstrap and `src/`. The run on the `9d5b0d8` package reported one warning in `uninstall.php:36` (`DirectDatabaseQuery.NoCaching` on the `DROP TABLE`); the ignore comment now includes that code and a re-run on the working tree reports zero findings. |
| PHPCompatibilityWP, `testVersion 8.1-` | Clean. This is static analysis; the code has not been executed on PHP 8.1. |
| Informational WordPress-Extra security/i18n/prefix sniffs | Three `json_encode()` warnings, all in RC8-era files unchanged by RC9 (`PolicyFingerprint`, `ConnectionFingerprint`, `OpenAiResponsesFileSearchTransport`). Not a Plugin Check result. |
| Verification package | Rebuilt from a clean `git archive` of `02c24b6` (includes the `uninstall.php` annotation fix) by a throwaway Python port of `package-core.ps1` (PowerShell is unavailable in the development container): 76 files (the 66 RC8 files plus 10 new), file list identical to the git allowlist, every file byte-identical to the committed source, no denylisted path or token, portable paths, logo/banner SHA-1 match the approved values, `php -l` clean on all 64 PHP files, repo-ruleset PHPCS zero findings (bootstrap, `src/`, `uninstall.php`) and PHPCompatibilityWP 8.1+ clean on the extracted package. SHA-256 `720a283c7c5c3dc2b98ebed83ff9503f7cdc9c85996aba7dd4cc3347452698df`. The earlier `9d5b0d8` build (`3736a14d…`) is superseded. Later documentation-only commits do not change the package contents. **Not the canonical artefact:** it lives only in the ephemeral container and will not match a Windows build's checksum. |
| Canonical package from `scripts/package-core.ps1` | Requires verification (run on the Windows machine) |
| Table creation, SQL, scheduled purge, upgrade from RC8 on WordPress | Requires verification in the disposable fixture |
| Administrator and public browser review (desktop and 390px, keyboard order) | Requires verification |
| Plugin deletion with the option off and on | Requires verification |
| Plugin Check 2.1.0 on the installed RC9 runtime | Run by the owner on the Test-Site: 1 error (`trunk_stable_tag`), 5 warnings, none in RC9-only files. Package provenance and file-for-file match with `02c24b6` not yet confirmed. Not run in the development container (no wordpress.org/GitHub access, no Docker daemon). |
| Live provider behaviour | Not tested by the agent; owner tests manually |

## Plugin Check on the installed RC9 runtime (owner run, 15 October 2026)

Plugin Check was run by the owner on the Test-Site install (report generated 15 October 2026, 14:14:32). The owner's Plugins-screen screenshot shows `Version 1.0.0-rc.9` as the only installed version. Result: one error and five warnings, none in an RC9-only file.

| Finding | File | Status |
| --- | --- | --- |
| Error `trunk_stable_tag` | `readme.txt` | Deliberate for the release-candidate period; a final-submission gate |
| Warning `AIProvider.DirectIntegration` (`api.openai.com`) | `src/AI/Transport/OpenAiResponsesFileSearchTransport.php:13` | Deliberate: the Responses API is used directly; the WordPress AI Client is roadmap-only |
| Four warnings `DirectDB.UnescapedDBParameter` | `src/Budget/WordPressBucketStore.php` lines 23, 28, 35, 56 | Existing prepared, row-locked rate-limit SQL, as reviewed for RC5 to RC8 |

This is the same set as the RC8 record. An earlier version of this section called the report an RC8 baseline because of that match; that was an inference, and it was wrong. The owner confirms RC9 was installed.

Caveats that remain:

- The installed files have not been independently compared with commit `02c24b6`. The installed folder should contain exactly 76 files, including `uninstall.php` and `src/Conversation/`; this should be confirmed on the Test-Site, and the package's provenance (canonical `package-core.ps1` build or otherwise) is not recorded here.
- Plugin Check reported nothing for `WordPressConversationStore`, `ConversationsPage` or `uninstall.php`, although the conversation store builds SQL with an interpolated table name in the same way as the rate store. The reason is not established. An attempt to reproduce the sniff in the development container failed on a dependency conflict, so there is no local explanation. The absence of findings should be read as "none reported", not as proof the pattern is clean.
- If a reviewer later flags the conversation store or the rate store, the available remedy is the `%i` identifier placeholder (WordPress 6.2 and later; the plugin requires 6.5), tested against a real MariaDB first because the rate limiter is security-sensitive. No such change has been made.
