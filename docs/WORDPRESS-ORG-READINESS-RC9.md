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
| Pure PHP/JavaScript checks, lint, source audit | Pass in the development container (PHP 8.3). PHP 8.1 syntax and PHPCS/WPCS have not been re-run for RC9. |
| Table creation, SQL, scheduled purge, upgrade from RC8 on WordPress | Requires verification in the disposable fixture |
| Administrator and public browser review (desktop and 390px, keyboard order) | Requires verification |
| Plugin deletion with the option off and on | Requires verification |
| Plugin Check, package build, denylist audit and checksum | Requires verification |
| Live provider behaviour | Not tested by the agent; owner tests manually |
