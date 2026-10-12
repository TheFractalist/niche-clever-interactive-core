# Progress

## Current objective

**12 October 2026 — C4 Core budgets, privacy and resilience.** C1–C3 are tested. C4 adds Core-only daily reservation accounting, privacy boundaries and safe public evidence URLs. Pro remains blocked until Core C5 technical acceptance.

## Implemented

- Repository-local operating guidance, licensing notice and source/build allowlist for newly authored foundation material.
- Provisional six-seam API-v1 contract types and one-registration-per-seam registry.
- Nonfunctional plugin bootstrap boundary with no WordPress hook, route, settings, storage, provider or network behaviour.
- Pure contract suite for the declared foundation rules.
- C0 machine-readable source/asset manifest, ownership and UX ledgers, phase test matrix, source audit and disposable generated-data fixture topology.
- C0 validation: PASS — pure contracts (33 checks), manifest control test, executable source audit, complete PHP lint, Compose configuration check and image-manifest checks completed locally. No container, database, existing installation or provider resource was started or changed.
- C1 fresh NCI settings/schema lifecycle and prefixed rate-bucket table; no legacy setting/table access, migration or deletion.
- C1 server-constant-only secret resolver, readiness state, safe public error taxonomy, bounded settings/evidence configuration, fixed policy/fingerprint, capability/nonce guard, request validator and fail-closed default-profile resolver.
- C1 fixture validation: PASS — an isolated WordPress 6.5/PHP 8.1 installation activated and deactivated Core; NCI settings/schema marker and rate table existed, and settings remained after deactivation. No existing AskFolio installation was changed.
- C2 one-profile Settings workspace with capability/nonce save guard and escaped values; eight documented Core screens; Core shortcodes/block metadata; three responsive CSS styles and unavailable-safe disabled panel.
- C2 fixture validation: PASS — Core settings page returned HTTP 200 with all workspace labels; a nonce-protected save returned its expected safe redirect; the generated NCI-only public page rendered the panel, label, disabled controls and unavailable status in a browser.
- C3 Core-owned `POST /ncli/v1/chat` route, fail-closed default-profile resolution, request/preset validation, fixed prompt policy and closed public error responses.
- C3 deterministic mock transport is enabled only by the boolean server constant `NCLI_CORE_MOCK_TRANSPORT` in the disposable fixture. There is no live provider client, credential, vector-store value or provider call.
- C3 requires a completed File Search result whose current policy/corpus fingerprints and annotation tokens match the current Core evidence map before presenting a bounded answer and approved title/HTTPS URL.
- C3 fixture validation: PASS — WordPress 6.5/PHP 8.1 served a mocked enabled answer and approved public evidence through Core's route; a prompt-injection fixture returned only `policy_refusal`; a 390px viewport retained the usable panel; keyboard focus advanced from question to Ask.
- C4 visitor/provider daily reservations with explicit InnoDB transactions and row locking. Visitor scope is a server-secret HMAC; raw addresses are not stored. Forwarded headers are accepted only from configured trusted proxy CIDRs.
- C4 uses UTC daily rollover, releases failed pre-dispatch paired reservations, retains accounting after an attempted/ambiguous transport outcome and does not retry automatically. Evidence URLs now reject IP literals, localhost, `.local`, `.internal` and URL credentials without fetching them.
- C4 fixture validation: PASS — 20 concurrent mocked route requests on WordPress 6.5/PHP 8.1/MariaDB all returned HTTP 200; the two NCI-only bucket rows had 64-character scope hashes, zero reservations, committed count 20 and InnoDB engine.

## Requires verification

- Foundation validation recorded on 12 October 2026: PASS — C:\xampp\php\php.exe tests\contract.php completed 33 pure contract checks; PASS — PHP syntax lint completed without errors for all 20 PHP files; PASS — git diff --check completed without whitespace errors.
- Source/asset audit recorded on 12 October 2026: PASS — executable source contains no AskFolio or WPIP references; credential-shaped value scan found no matches; changed-path inspection found only the 26 newly authored foundation files.
- C4 deterministic domain/route checks, source/provenance audits, C1–C3 suites, full PHP lint and `git diff --check` require a final clean run before the C4 commit.
- C5 packaging, compatibility matrices and local candidate evidence remain absent.

## Remaining conditions

- C4 atomic budgets/privacy and C5 candidate evidence remain. A live canary with a segregated test credential/store is a C5 technical-acceptance blocker.
- Pro remains outside active work. Its archive policy is documented in the canonical NCI context pack only.

## Next authorised continuation

Commit the C4 budget/privacy increment after final source and changed-path audit, then begin C5 deterministic tooling and local-only candidate work. Do not create Pro.
