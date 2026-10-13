# Changelog

## 1.0.0-rc.7 — 13 October 2026

Local-only bounded-response-guidance correction. The saved owner response guidance now composes into the OpenAI Responses instruction for public chat and administrator connection tests, where it can shape response style and format only. Concise, Balanced and Detailed now supply a Core-defined response-detail baseline, with specific owner presentation guidance taking priority where it does not conflict with Core requirements.

Core retrieval, safety, privacy, disclosure, provider-tool and route rules remain non-negotiable. The policy and connection fingerprints now derive from the exact composed instruction supplied to the transport, so changing response guidance or the preset requires a fresh connection test. The Core-owned Responses transport now allows a completed File Search call up to 60 seconds, after a local live request showed that the earlier 20-second bound expired before a valid response returned. Opaque inline provider file-citation markers are removed before a public answer is returned. This candidate is not published, deployed, tagged or submitted to WordPress.org.

## 1.0.0-rc.6 — 13 October 2026

Local-only Core presentation correction. Public answers now render a constrained, DOM-built Markdown subset: headings, paragraphs, lists, quotations, code blocks and basic emphasis. Provider-returned HTML is never parsed, and Markdown links remain non-clickable text so Core still emits no public provider-derived navigation.

The Core route, connection/readiness gate, provider transport, File Search validation, rate reservations and stored data boundary are unchanged. This candidate is not published, deployed, tagged or submitted to WordPress.org.

## 1.0.0-rc.5 — 12 October 2026

Local-only Core boundary-correction candidate. Core now returns the bounded answer directly from the configured File Search corpus after a completed retrieval and current policy/corpus fingerprint checks. The approved-evidence map, its configuration screen, citation-token parsing, evidence-metadata extension seam and public source links have been removed. Legacy stored map data is ignored and dropped on the next settings save.

The provider request, secret boundary, readiness/connection-test gate, rate reservations and generic failure behaviour remain unchanged. This candidate is not published, deployed, tagged or submitted to WordPress.org. No Pro implementation is included.

## 1.0.0-rc.4 — 12 October 2026

Local-only Core Setup-workflow parity candidate. It reconstructs the four Core-safe setup steps: confirm the server-only key constant, save the vector-store reference and corpus version, run a nonce/capability-protected File Search connection test, then place the public panel. A passing test stores only a UTC time, pass/fail state and opaque configuration fingerprint; it stores no secret, vector-store value, provider payload, response, citation or retrieved text.

An enabled profile is now unavailable for public chat until its current configuration has a passing test. Test calls reserve only the existing global provider budget and retain attempted-call accounting without retries. This candidate is not published, deployed, tagged or submitted to WordPress.org.

## 1.0.0-rc.3 — 12 October 2026

Local-only Core administrator Dashboard-chrome parity correction candidate. It reconstructs the standalone banner framing, guided checklist, tabbed workspace, table-like form rows, notices and blue save treatment in new NCI code.

The eight Core-safe tabs, one-profile model, C5 transport/evidence/security/budget behaviour and two approved visual binaries are unchanged. No AskFolio source, Premium profile-management feature or other legacy asset is included. This candidate is not published, deployed, tagged or submitted to WordPress.org.

## 1.0.0-rc.2 — 12 October 2026

Local-only Core visual-parity correction candidate. It reconstructs the established single-profile workspace and the Classic, Minimal and Dark public panel styles in new NCI code, while retaining the C5 route, evidence, reservation and provider boundaries unchanged.

Only two owner-authorised visual assets are included: the Niche Clever logo and the administrator banner recorded in `provenance/nci-source-manifest.json`. No other legacy artwork, source or Pro capability is included. This candidate is not published, deployed, tagged or submitted to WordPress.org.

## 1.0.0-rc.1 — 12 October 2026

Local-only, technically accepted Core release candidate. It includes the C0–C4 newly authored Core foundation: single-profile administration and panel, the sole Core chat route, deterministic fixture-only retrieval/evidence controls, and privacy-bounded daily reservations.

The C5 quality increment adds the complete GPL v2 text, WordPress package metadata, direct-file guards and an external-tool-only WPCS/PHPCS ruleset. The candidate package is tested with the official Plugin Check plugin in a disposable fixture.

The C5 live increment adds a Core-owned, server-constant-gated OpenAI Responses/File Search transport with opaque citation-token derivation. Its segregated live canary passed completed retrieval, approved/unapproved/stale evidence handling, provider-safe failure and timeout accounting. No credential, provider identifier, corpus content or payload is packaged.

This is not a public release. It has no tag, GitHub release, deployment or WordPress.org submission. Pro is not included.
