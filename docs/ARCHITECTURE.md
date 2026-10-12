# Core API-v1 and C1 security foundation

## Status and limits

This document records a provisional API scaffold and C1–C5 runtime/security boundary. It does not freeze API v1 or establish Pro compatibility. The OpenAI Responses/File Search adapter is inert unless an explicit server-only fixture constant enables it. Core package versioning and API-major versioning are separate.

Future Pro registration must occur only after Core bootstrap, validate the Core API major and declared minimum Core package version, and fail closed when absent or incompatible. It must then register no public route and make no provider call. Standalone Core remains usable.

## Core-owned request boundary

Core owns the sole public POST /ncli/v1/chat route, credential resolution, transport selection, rate/budget ordering, profile readiness, completed-File-Search validation and public errors. No seam receives an API key, raw provider payload, retrieved source text, secret resolver, provider client, route registrar or mutable Core request object.

C1 registers an NCI-only activation/deactivation lifecycle. Activation creates fresh ncli_core_settings and a schema marker, then creates the prefixed ncli_core_rate_buckets table. Deactivation deliberately preserves NCI settings and rate data.

The only supported API-key source is the server-side NCLI_OPENAI_API_KEY_DEFAULT constant. Core never reads a key from an option, settings request or API request. Readiness remains unready until the default profile is enabled, that constant is present, the configured vector-store reference and corpus version are non-empty, and the current effective configuration has a passing administrator connection test. The stored diagnostic contains only `never`, `passed` or `failed`, a UTC test time and an opaque HMAC fingerprint; it never stores a secret, provider payload, vector-store reference, response, citation or retrieved text.

Public-safe error payloads are closed to six codes: profile unavailable, invalid request, policy refusal, allowance exceeded, provider-safe failure and evidence failure. They are deliberately generic.

The Core default profile is the only C1 profile. A disabled profile and every named non-default profile resolve to no profile; no request may fall back to Core from an unknown name.

## C2 workspace and presentation boundary

C2 adds a one-profile administrator workspace: Setup, Profile, AI and Corpus, Conversation, Appearance and Limits, Placement, Diagnostics and Help. It uses the Core capability/nonce guard, CoreSettings validation and escaped output. The settings page never renders a key, provider payload, retrieved text, file ID or vector-store identifier.

Core registers the ncli_interactive and ncli_limit_notice shortcodes and ncli/interactive block. It has Classic, Minimal and Dark panel styles. A historical `editorial` setting value is normalised to `dark` when settings are read or saved; it is not presented as a selectable style. The Core-only presentation fields are bounded title, headline, introduction, placeholder and footer labels, with a shortcode/block title taking precedence for one render. C3 sends a JSON request only to Core's route and keeps the browser transcript in page memory only; no transcript or turn is persisted.

The administrator workspace is a top-level, one-profile Core screen with NCI-specific masthead, bounded setup/readiness guidance, Core-safe tabs and a sticky save action. It never adds profile switching, authored question collections, contextual placement, cache or archive controls: those remain Pro-only. The public frame supplies only a session reset and loading/disabled states around the existing Core route and bounded answer output; it does not alter request payloads, transport ordering or validation.

The Setup tab reconstructs the bounded four-step Core workflow: server-only `NCLI_OPENAI_API_KEY_DEFAULT` confirmation, vector-store/corpus configuration guidance, an administrator-only nonce/capability-protected File Search connection test, and published shortcode/block placement guidance. The test has no REST route or visitor output; it uses the selected Core transport with a fixed internal request, discards its answer/citations and passes only on a completed File Search result. It reserves only the existing global provider allowance. A reservation is released only before dispatch; attempted failures and timeouts remain accounted for and are not automatically retried.

## C3 mock retrieval boundary

The route first rejects malformed request shapes and attempts to override Core's default profile/preset. It then resolves the default profile without fallback, applies fixed prompt policy, checks readiness and calls its Core-owned transport. `DeterministicMockTransport` is enabled only by the boolean server constant `NCLI_CORE_MOCK_TRANSPORT`. `OpenAiResponsesFileSearchTransport` is selected only by the separate boolean `NCLI_CORE_LIVE_TRANSPORT`; it reads the key solely from `NCLI_OPENAI_API_KEY_DEFAULT`, sends a fixed Responses request to the OpenAI endpoint with File Search, and uses the current Core vector-store setting. Both values are supplied only to a disposable fixture environment. Without one of those explicit modes, Core selects an unavailable transport.

Every historical answer needs a completed File Search result. The adapter deliberately does not request File Search result text and ignores provider citation metadata. The result must carry current policy and corpus fingerprints. Public output is limited to the bounded answer. Provider IDs, citation metadata, retrieved text, provider payloads, secrets and diagnostics are never emitted. A policy refusal, invalid request, unavailable profile, provider failure and retrieval-validation failure use the closed public error set.

## C4 budget, privacy and resilience boundary

Before Core dispatches a transport request it reserves one visitor and one global provider allowance in the InnoDB-backed `ncli_core_rate_buckets` table. It locks the exact `(kind, scope_hash, period_start)` row in a transaction. The visitor scope is an HMAC under the server-only `NCLI_CORE_SCOPE_SECRET`; Core never persists the address itself. Forwarded-client headers are ignored unless `REMOTE_ADDR` is in the explicit `NCLI_CORE_TRUSTED_PROXY_CIDRS` CIDR allowlist.

The day boundary is UTC midnight. A reservation released before provider dispatch restores allowance. Once dispatch is attempted, Core retains the reservation on timeout/ambiguous failure and never retries automatically. A returned answer that fails retrieval validation remains accounted for because provider work was attempted. There is no cache in this phase.

Core emits no public source links or citation metadata, so its public panel cannot turn a retrieved citation into a browser navigation or internal-network request path. The detailed lifecycle and privacy record is `docs/PRIVACY-LIFECYCLE.md`.

## Six provisional seams

| Seam | Contract | Bounded intent | Fail-closed rule |
| --- | --- | --- | --- |
| Profile resolution | ProfileResolverV1 | Resolve an explicit non-default profile identifier. | Empty, malformed, unknown or disabled identifiers never fall back to Core's corpus. |
| Supplementary chronology | ChronologyProviderV1 | Offer at most 20 bounded chronology facts. | Invalid facts are rejected; chronology cannot set corpus/provider/instructions. |
| Eligible cache | AnswerCacheV1 | Lookup/store only a fully fingerprinted eligible answer. | Missing, malformed or mismatched values are cache misses. |
| Completed public turn | CompletedTurnRecorderV1 | Receive only a successfully validated cache/provider turn. | Core does not invoke it for refusals, deterministic responses, diagnostics or failed delivery. |
| Workspace extension | WorkspaceExtensionV1 | Supply fixed permitted workspace screens only. | Unknown screens and missing Core capability/nonce checks are rejected. |

The registry permits one implementation for each known seam and rejects unknown, mismatched and duplicate registrations. It is a pure contract mechanism, not WordPress integration.

## Value boundaries

- ProfileIdV1 accepts only explicit lower-case IDs matching ^[a-z0-9][a-z0-9_-]{0,63}$.
- CacheKeyV1 includes the question digest, profile ID, policy fingerprint, corpus fingerprint, access-state fingerprint and response preset. Every field participates in identity.
- CompletedTurnV1 carries the question, answer, Core version, optional Pro version, corpus version and non-secret fingerprints as a contract payload. It does not store anything.

Pro archive storage is not present. The owner-approved policy remains documented only in the canonical NCI context pack: disabled until explicitly configured; 30/90/365 days or deliberate indefinite retention; question/answer plus per-turn Core/app, Pro-if-active and corpus versions; scoped erasure; and disclosure that restored backups may reintroduce erased records and require scoped re-erasure.
