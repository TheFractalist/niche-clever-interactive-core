# Provisional Core API-v1 foundation

## Status and limits

This document records a **provisional** contract scaffold. It does not freeze API v1, establish Pro compatibility, or implement a functional feature. Core package versioning and API-major versioning are separate. This foundation publishes API major 1 as a contract constant only.

Future Pro registration must occur only after Core bootstrap, validate the Core API major and declared minimum Core package version, and fail closed when absent or incompatible. It must then register no public route and make no provider call. Standalone Core remains usable.

## Core-owned request boundary

Only future Core may own the public POST /ncli/v1/chat route, credential resolution, provider transport, rate/budget ordering, profile readiness, final evidence filtering and public errors. No seam receives an API key, raw provider payload, retrieved source text, secret resolver, provider client, route registrar or mutable Core request object.

No route, client, provider call, settings, UI, storage or WordPress hook exists in this foundation.

## Six provisional seams

| Seam | Contract | Bounded intent | Fail-closed rule |
| --- | --- | --- | --- |
| Profile resolution | ProfileResolverV1 | Resolve an explicit non-default profile identifier. | Empty, malformed, unknown or disabled identifiers never fall back to Core's corpus. |
| Supplementary chronology | ChronologyProviderV1 | Offer at most 20 bounded chronology facts. | Invalid facts are rejected; chronology cannot set corpus/provider/instructions. |
| Eligible cache | AnswerCacheV1 | Lookup/store only a fully fingerprinted eligible answer. | Missing, malformed or mismatched values are cache misses. |
| Evidence metadata | EvidenceMetadataProviderV1 | Map current annotation tokens to approved public title/HTTPS link metadata. | Unapproved, stale or malformed metadata is omitted. |
| Completed public turn | CompletedTurnRecorderV1 | Receive only a successfully validated cache/provider turn. | Core does not invoke it for refusals, deterministic responses, diagnostics or failed delivery. |
| Workspace extension | WorkspaceExtensionV1 | Supply fixed permitted workspace screens only. | Unknown screens and missing Core capability/nonce checks are rejected. |

The registry permits one implementation for each known seam and rejects unknown, mismatched and duplicate registrations. It is a pure contract mechanism, not WordPress integration.

## Value boundaries

- ProfileIdV1 accepts only explicit lower-case IDs matching ^[a-z0-9][a-z0-9_-]{0,63}$.
- CacheKeyV1 includes the question digest, profile ID, policy fingerprint, corpus fingerprint, approval-map revision, access-state fingerprint and response preset. Every field participates in identity.
- ApprovedEvidenceV1 can contain a bounded title and optional credential-free absolute HTTPS URL only.
- CompletedTurnV1 carries the question, answer, Core version, optional Pro version, corpus version and non-secret fingerprints as a contract payload. It does not store anything.

Pro archive storage is not present. The owner-approved policy remains documented only in the canonical NCI context pack: disabled until explicitly configured; 30/90/365 days or deliberate indefinite retention; question/answer plus per-turn Core/app, Pro-if-active and corpus versions; scoped erasure; and disclosure that restored backups may reintroduce erased records and require scoped re-erasure.
