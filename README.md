# Niche Clever Interactive Core

This public repository holds Niche Clever Interactive Core, a non-production Core-first programme. Its current local-only candidate is `1.0.0-rc.1`; it is not a public release and must not be represented as accepted NCI Core 1.0.0.

## Current scope

- PHP 8.1-compatible, provisional API-v1 contracts for six Core/Pro seams.
- Pure PHP contract/provenance/source-audit checks.
- C0 source provenance, ownership, UX and test records plus a generated-data-only Docker fixture topology.
- C1 NCI-only lifecycle, settings/schema marker, rate-table creation, server-constant secret resolver, readiness/error/validation/policy boundaries and default-profile fail-closed resolution.
- C2 Core-only settings workspace, settings save boundary, shortcodes, block metadata and a responsive public panel.
- C3's sole public route, POST /ncli/v1/chat, validates a request before the Core-owned transport/evidence boundary. The only present transport is a server-constant-gated deterministic mock for disposable fixtures; there is no provider client, provider value or live request.
- C4's Core-only daily visitor/provider reservations, server-secret scope hashing, trusted-proxy boundary and safe public-evidence URL policy. See `docs/PRIVACY-LIFECYCLE.md`.

Core's future functional responsibilities remain the sole public chat route, request validation/order, provider/credential access, cost and safety controls, evidence filtering and public response construction. Pro integration is not implemented or tested here.

## Start here

Read AGENTS.md, docs/ARCHITECTURE.md, SOURCE-ALLOWLIST.md and progress.md.

Run the pure local checks:

    & 'C:\xampp\php\php.exe' tests/contract.php
    & 'C:\xampp\php\php.exe' tests/provenance.php
    & 'C:\xampp\php\php.exe' tests/source-audit.php
    & 'C:\xampp\php\php.exe' tests/c1-domain.php
    & 'C:\xampp\php\php.exe' tests/c2-render.php
    & 'C:\xampp\php\php.exe' tests/c3-retrieval.php
    & 'C:\xampp\php\php.exe' tests/c3-route.php
    & 'C:\xampp\php\php.exe' tests/c4-budget.php
    & 'C:\xampp\php\php.exe' tests/c5-package.php
    node --check assets/js/public.js
    node tests/c5-public-js.mjs
    Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName }

The C0 fixture topology has no database dump or provider configuration. C1–C5 still require their respective implementation and acceptance evidence; a live File Search canary remains blocked until segregated test resources are supplied outside chat.

## Licence

Newly authored repository material is licensed under GPL-2.0-or-later; see LICENSE. That notice does not assert any right to reuse AskFolio, third-party, Niche Clever or other external material.
