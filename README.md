# Niche Clever Interactive

This public repository holds the Niche Clever Interactive WordPress plugin. The current `1.0.0-rc.9` candidate is local-only and is not a WordPress.org submission, directory listing or public 1.0.0 release. The repository's internal architecture remains Core-first; no NCI Pro implementation is included.

## Current scope

- PHP 8.1-compatible, provisional API-v1 contracts for five Core/Pro seams.
- Pure PHP contract/provenance/source-audit checks.
- C0 source provenance, ownership, UX and test records plus a generated-data-only Docker fixture topology.
- C1 NCI-only lifecycle, settings/schema marker, rate-table creation, server-constant secret resolver, readiness/error/validation/policy boundaries and default-profile fail-closed resolution.
- C2 Core-only settings workspace, settings save boundary, shortcodes, block metadata and a responsive public panel.
- A visual-parity correction reconstructs the single-profile workspace and Classic, Minimal and Dark public panels in NCI code. Only the manifest-recorded NCI logo and administrator banner are directly incorporated; all other visual work is reconstruction.
- C3's sole public route, POST /ncli/v1/chat, validates a request before the Core-owned completed-File-Search boundary. A newly authored OpenAI Responses/File Search adapter is available only when an explicit server constant enables it in a disposable fixture; no credential, vector-store reference or provider payload is committed.
- RC9 adds an optional, off-by-default conversation history (new prefixed table, Conversations screen, delete one/selected/all, retention purge), panel width, accent colour, an opt-in credit line, an editable unavailable message, the "NC Interactive" menu and an owner-controlled `uninstall.php`. See `docs/ARCHITECTURE.md` and `docs/PRIVACY-LIFECYCLE.md`.
- C4's Core-only daily visitor/provider reservations and server-secret scope hashing with a trusted-proxy boundary. See `docs/PRIVACY-LIFECYCLE.md`.

Core's future functional responsibilities remain the sole public chat route, request validation/order, provider/credential access, cost and safety controls, completed-File-Search validation and public answer construction. Pro integration is not implemented or tested here.

## Start here

Read AGENTS.md, docs/ARCHITECTURE.md, SOURCE-ALLOWLIST.md and progress.md.

Run the pure local checks:

    & 'C:\xampp\php\php.exe' tests/contract.php
    & 'C:\xampp\php\php.exe' tests/provenance.php
    & 'C:\xampp\php\php.exe' tests/source-audit.php
    & 'C:\xampp\php\php.exe' tests/c1-domain.php
    & 'C:\xampp\php\php.exe' tests/c2-render.php
    & 'C:\xampp\php\php.exe' tests/c2-admin-chrome.php
    & 'C:\xampp\php\php.exe' tests/c3-retrieval.php
    & 'C:\xampp\php\php.exe' tests/c3-route.php
    & 'C:\xampp\php\php.exe' tests/c4-budget.php
    & 'C:\xampp\php\php.exe' tests/c4-connection-test.php
    & 'C:\xampp\php\php.exe' tests/c5-live-transport.php
    & 'C:\xampp\php\php.exe' tests/c5-package.php
    & 'C:\xampp\php\php.exe' tests/c6-conversation-log.php
    & 'C:\xampp\php\php.exe' tests/c6-admin-conversations.php
    & 'C:\xampp\php\php.exe' tests/c6-appearance.php
    & 'C:\xampp\php\php.exe' tests/c6-uninstall.php
    node --check assets/js/public.js
    node tests/c5-public-js.mjs
    Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName }

The C0 fixture topology has no database dump or provider configuration. C1–C5 implementation and acceptance evidence is recorded in `progress.md`. C5 remains technically accepted as historical local-only technical evidence following a segregated live File Search canary. RC5 needs its own deterministic/package/fixture validation before it can replace RC4 as the current local-only candidate. Public release, deployment and WordPress.org submission remain separate decisions.

## Licence

Newly authored repository material is licensed under GPL-2.0-or-later; see LICENSE and the complete GPL v2 text in COPYING. Those files do not assert any right to reuse AskFolio, third-party, Niche Clever or other external material.
