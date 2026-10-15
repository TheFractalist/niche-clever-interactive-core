# Core privacy and lifecycle boundary

## Request accounting

Core's public route uses only a server-secret HMAC of the canonical client address for its daily visitor bucket. It never stores a raw IP address, visitor identifier, cookie, question, answer, retrieved text, provider payload or credential in the rate table.

A server-only secret is needed to reserve a visitor allowance. It is automatic: `NCLI_CORE_SCOPE_SECRET` in server configuration is used if defined, otherwise a domain-separated value derived from WordPress's authentication salt. Neither is stored in a WordPress option or request. If a deployment is behind a proxy, `NCLI_CORE_TRUSTED_PROXY_CIDRS` may contain an explicit comma-separated CIDR allowlist. Core reads `X-Forwarded-For` only when `REMOTE_ADDR` is within that allowlist; otherwise it ignores the header. This prevents a client from choosing its own rate-bucket identity.

The `ncli_core_rate_buckets` table uses InnoDB transactions and a unique `(kind, scope_hash, period_start)` key. A reservation increments `reserved_count`; a settled request moves it to `committed_count`; a pre-dispatch cancellation releases it. UTC midnight creates a new period. Core does not auto-retry an ambiguous attempted provider request, and it retains that accounting outcome.

The administrator-only connection test also reserves the global provider bucket, but it never creates a visitor bucket. It stores only a pass/fail/never state, UTC test time and opaque configuration fingerprint. The test deliberately discards its fixed verification prompt, answer, provider payload, citation metadata and retrieved text. Its diagnostic record is invalidated whenever the effective server key, transport mode, vector-store reference, corpus version, prompt policy or Core version changes.

## Retrieval output

Core emits the bounded answer only. It does not configure, store, validate or publish an approved-evidence map, source link or citation metadata. The browser therefore never follows provider-derived source URLs.

The C5-only OpenAI Responses/File Search transport is selected solely by a server constant in a disposable fixture. It sends the owner-selected model, optional reasoning effort, retrieval depth and output limit as non-personal request parameters, sends no retrieved File Search result text in its request, retains no provider payload and ignores provider file-citation metadata. The public response never contains a provider citation, vector-store reference, provider payload or credential.

## Conversation history (RC9)

Off by default and enabled only by the site owner in the Conversation tab. When on, each successfully validated answer is stored as one turn in `ncli_core_conversation_turns`: displayed question, displayed answer, an opaque conversation identifier generated in the visitor's browser per page session, the profile identifier, Core version, corpus version, policy fingerprint and UTC timestamp. Nothing else is stored: no IP address (raw or hashed), user or account identifier, cookie, header, nonce, provider payload, citation, retrieved text, credential or administrator test. Refusals, invalid requests and failures are not stored.

Retention is the owner's choice of 30, 90 or 365 days or indefinite (90 by default once enabled). A daily scheduled event deletes older turns, including after recording has been switched off. The owner can review conversations and delete one, several or all of them under NC Interactive > Conversations. Deleted rows may persist in database backups; restoring an older backup can reintroduce them, so scoped deletion should be repeated after a restore.

Because visitors can type personal information into a question and no identifier is stored, a stored conversation cannot be matched to a person automatically. The registered WordPress exporter and eraser therefore return an explanatory message and nothing else. Owners should state in their own privacy notice that questions and answers are kept, why, and for how long.

## Lifecycle

Activation creates only fresh NCI settings, the NCI schema marker, the prefixed rate-bucket table and the prefixed conversation table, and schedules a daily retention purge. Deactivation preserves all data for recoverability and stops only the scheduled purge. `uninstall.php` deletes nothing by default; if the owner has enabled "Delete data on uninstall" it removes exactly the NCI options, the two NCI tables and the purge event. This Core release has no visitor-record export. Existing AskFolio/WPIP options, transients, constants and tables are neither read nor changed.

These controls do not replace a site's own privacy notice. A later Pro extension, if separately authorised, adds governance (export, retention schedules, scoped erasure) over Core's conversation table; it does not create a second archive.

## Provider diagnostics

Core keeps one small, non-autoloaded option, `ncli_core_last_failure`, holding only the most recent provider problem: a closed reason code (for example `timeout`, `http_429`, `empty_answer`), whether it came from a public question or the connection test, a UTC time and a duration in seconds. It stores no question, answer, address, provider message, payload, header, credential or identifier, and visitors never see the reason. It is shown on the Diagnostics tab and removed by `uninstall.php` only when the owner has enabled "Delete data on uninstall".

## WordPress privacy-policy suggestion

RC9 registers a suggested privacy-policy section through WordPress's privacy-policy helper. It states the actual Core boundary: each public question is sent to OpenAI and earlier questions in the conversation are not; File Search may use material in the administrator-selected vector store; Core does not store the API key in WordPress options; daily visitor limits use a server-secret HMAC rather than a raw IP address; and conversation history is off by default but, if enabled, stores questions and answers with a random conversation identifier until deleted or the retention period ends. The earlier RC8 statement that conversation context is sent was inaccurate (the client sends only the current question) and has been corrected.

The suggested text is not a legal determination or a replacement for the site owner's privacy notice, OpenAI account terms, billing, retention decisions or applicable data-protection obligations.
