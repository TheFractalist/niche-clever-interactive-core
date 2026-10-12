# Progress

## Current objective

**12 October 2026 — C5 local mock-verified Core candidate.** C1–C4 are tested. C5's GPL package, deterministic security/static checks, Plugin Check and exact-package rollback evidence are complete. The candidate remains local-only and Pro remains blocked until the live File Search canary technically accepts Core.

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
- C5 candidate controls: a package script builds only the explicit Core runtime allowlist outside the repository, rejects prohibited paths/content and emits a SHA-256 manifest. JavaScript syntax/boundary and package-policy checks are deterministic and dependency-free.
- C5 quality completion: the package now includes the complete official GPL v2 text and WordPress readme metadata; direct PHP files reject browser access. PHPCS 3.13.6/WPCS 3.4.1 completed with zero findings against the committed Core security/database ruleset. The external Composer audit reported no advisory.
- C5 Plugin Check completion: official Plugin Check 2.1.0 ran against the exact ZIP-derived candidate in the disposable current fixture. It reported no errors after package metadata and direct-file guards were added. The remaining warnings are reviewed: the temporary candidate directory changes the expected text-domain slug, and the rate store deliberately uses prepared, uncached, row-locked SQL with a `$wpdb`-prefix table name.

## Requires verification

- Foundation validation recorded on 12 October 2026: PASS — C:\xampp\php\php.exe tests\contract.php completed 33 pure contract checks; PASS — PHP syntax lint completed without errors for all 20 PHP files; PASS — git diff --check completed without whitespace errors.
- Source/asset audit recorded on 12 October 2026: PASS — executable source contains no AskFolio or WPIP references; credential-shaped value scan found no matches; changed-path inspection found only the 26 newly authored foundation files.
- C5 candidate package, checksum, denylist/source audit and rollback rehearsal are complete. The toolchain, ZIP and fixture evidence are external to the repository and context pack; no vendor or third-party source is committed.

## Remaining conditions

- C5 is **mock-verified, live-canary deferred**. It is not technically accepted because the live File Search canary is blocked on segregated provider resources.
- Pro remains outside active work. Its archive policy is documented in the canonical NCI context pack only.

## Next authorised continuation

Do not create Pro. Run the segregated live File Search canary only when Jacob supplies its test credential and vector store outside chat.
