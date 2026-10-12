# Core API-v1 and C1 security foundation

## Status and limits

This document records a provisional API scaffold and C1–C3 runtime/security boundary. It does not freeze API v1, establish Pro compatibility or implement a live provider call. Core package versioning and API-major versioning are separate.

Future Pro registration must occur only after Core bootstrap, validate the Core API major and declared minimum Core package version, and fail closed when absent or incompatible. It must then register no public route and make no provider call. Standalone Core remains usable.

## Core-owned request boundary

Core owns the sole public POST /ncli/v1/chat route, credential resolution, transport selection, rate/budget ordering, profile readiness, final evidence filtering and public errors. No seam receives an API key, raw provider payload, retrieved source text, secret resolver, provider client, route registrar or mutable Core request object.

C1 registers an NCI-only activation/deactivation lifecycle. Activation creates fresh ncli_core_settings and a schema marker, then creates the prefixed ncli_core_rate_buckets table. Deactivation deliberately preserves NCI settings and rate data.

The only supported API-key source is the server-side NCLI_OPENAI_API_KEY_DEFAULT constant. Core never reads a key from an option, settings request or API request. Readiness remains unready until the default profile is enabled, that constant is present, and the configured vector-store reference and corpus version are non-empty.

Public-safe error payloads are closed to six codes: profile unavailable, invalid request, policy refusal, allowance exceeded, provider-safe failure and evidence failure. They are deliberately generic.

The Core default profile is the only C1 profile. A disabled profile and every named non-default profile resolve to no profile; no request may fall back to Core from an unknown name.

## C2 workspace and presentation boundary

C2 adds a one-profile administrator workspace: Setup, Profile, AI and Evidence, Conversation, Appearance and Limits, Placement, Diagnostics and Help. It uses the Core capability/nonce guard, CoreSettings validation and escaped output. The settings page never renders a key, provider payload, retrieved text, file ID or vector-store identifier.

Core registers the ncli_interactive and ncli_limit_notice shortcodes and ncli/interactive block. It has classic, minimal and editorial panel styles. C3 sends a JSON request only to Core's route and keeps the browser transcript in page memory only; no transcript or turn is persisted.

## C3 mock retrieval and evidence boundary

The route first rejects malformed request shapes and attempts to override Core's default profile/preset. It then resolves the default profile without fallback, applies fixed prompt policy, checks readiness and calls its Core-owned transport. The only current transport is `DeterministicMockTransport`, enabled solely by the boolean server constant `NCLI_CORE_MOCK_TRANSPORT` in a disposable fixture. Without that constant, Core selects an unavailable transport; no live provider client, credential, vector-store value or provider call exists in this phase.

Every historical answer needs a completed File Search result. The result must carry current policy and corpus fingerprints, and every current annotation token must map to Core's current approved evidence map. Public output is limited to the answer and approved title/credential-free HTTPS URL. Annotation tokens, IDs, retrieved text, provider payloads, secrets and diagnostics are never emitted. A policy refusal, invalid request, unavailable profile, provider failure and evidence failure use the closed public error set.

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
