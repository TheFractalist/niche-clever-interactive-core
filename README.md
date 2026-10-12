# Niche Clever Interactive Core

This public repository holds the newly authored, non-production foundation for Niche Clever Interactive Core. It is deliberately not a functional plugin release and must not be installed, packaged or represented as NCI Core 1.0.0.

## Current scope

- PHP 8.1-compatible, provisional API-v1 contracts for six future Core/Pro seams.
- Pure PHP contract tests that verify bounded value objects, one-registration-per-seam behaviour and declared fail-closed rules.
- A nonfunctional WordPress plugin header/bootstrap boundary with no routes, provider client, settings, UI, storage or external calls.

Core's future functional responsibilities remain the sole public chat route, request validation/order, provider/credential access, cost and safety controls, evidence filtering and public response construction. Pro integration is not implemented or tested here.

## Start here

Read AGENTS.md, docs/ARCHITECTURE.md, SOURCE-ALLOWLIST.md and progress.md.

Run the pure local checks:

    & 'C:\xampp\php\php.exe' tests/contract.php
    Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName }

The foundation has no Composer dependencies, WordPress runtime, database, provider configuration, assets, build command, release package or CI workflow.

## Licence

Newly authored repository material is licensed under GPL-2.0-or-later; see LICENSE. That notice does not assert any right to reuse AskFolio, third-party, Niche Clever or other external material.
