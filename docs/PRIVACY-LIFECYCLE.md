# Core privacy and lifecycle boundary

## Request accounting

Core's public route uses only a server-secret HMAC of the canonical client address for its daily visitor bucket. It never stores a raw IP address, visitor identifier, cookie, question, answer, retrieved text, provider payload or credential in the rate table.

`NCLI_CORE_SCOPE_SECRET` is required in server configuration before an enabled route can reserve a visitor allowance. The key is never stored in a WordPress option or request. If a deployment is behind a proxy, `NCLI_CORE_TRUSTED_PROXY_CIDRS` may contain an explicit comma-separated CIDR allowlist. Core reads `X-Forwarded-For` only when `REMOTE_ADDR` is within that allowlist; otherwise it ignores the header. This prevents a client from choosing its own rate-bucket identity.

The `ncli_core_rate_buckets` table uses InnoDB transactions and a unique `(kind, scope_hash, period_start)` key. A reservation increments `reserved_count`; a settled request moves it to `committed_count`; a pre-dispatch cancellation releases it. UTC midnight creates a new period. Core does not auto-retry an ambiguous attempted provider request, and it retains that accounting outcome.

The administrator-only connection test also reserves the global provider bucket, but it never creates a visitor bucket. It stores only a pass/fail/never state, UTC test time and opaque configuration fingerprint. The test deliberately discards its fixed verification prompt, answer, citation tokens, provider payload and retrieved text. Its diagnostic record is invalidated whenever the effective server key, transport mode, vector-store reference, corpus version, prompt policy or Core version changes.

## Evidence links

Approved evidence links must be bounded, credential-free HTTPS URLs. Core rejects URL credentials, IP-literal hosts, `localhost`, `.local` and `.internal` hosts before a public response can include them. It does not fetch or probe those links.

The C5-only OpenAI Responses/File Search transport is selected solely by a server constant in a disposable fixture. It sends no retrieved File Search result text in its request, retains no provider payload, and converts provider file-citation IDs into opaque annotation tokens before Core evaluates the approved evidence map. The public response never contains the raw provider citation, vector-store reference, provider payload or credential.

## Lifecycle

Activation creates only fresh NCI settings, the NCI schema marker and the prefixed rate-bucket table. Deactivation preserves them for recoverability. This Core release has no uninstall deletion behaviour, archive, transcript persistence or visitor-record export. Existing AskFolio/WPIP options, transients, constants and tables are neither read nor changed.

These controls do not replace a site's own privacy notice. A later Pro archive, if separately authorised after Core technical acceptance, owns its opt-in archive and scoped erasure policy.
